<?php
/*
 * Created by Martin Wernståhl on 2011-12-19.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace Inject\Stack\Adapter;

use \Exception as BaseException;
use \Inject\Stack\Util;
use \Inject\Stack\Adapter\Exception as AdapterException;

/**
 * Fast non-blocking implementation of persistent HTTP connections using libevent,
 * functions in a similar way to the HTTPSocket adapter.
 * 
 * Supports multiple processes using the same socket.
 * 
 * Requires the libevent extension for PHP from <http://pecl.php.net/package/libevent>.
 */
class HTTPSocketEventLoop extends HTTPSocket
{
	/**
	 * List of added events, this to save them from the PHP garbage collector.
	 * 
	 * @var array(int => event)
	 */
	protected $clients = array();
	
	/**
	 * The application instance to run.
	 * 
	 * @var Closure|MiddlewareInterface|ObjectImplementing__invoke
	 */
	protected $app = null;
	
	public function preFork()
	{
		if( ! extension_loaded('libevent'))
		{
			throw AdapterException::libeventMissing(__CLASS__);
		}
		
		parent::preFork();
		
		// Seems like if no block is set (=0), errors might arise when multiple processes
		// are accessing the same sockets, for now this socket is blocking when accepting
		// new connections
		//stream_set_blocking($this->socket, 0);
	}
	
	public function run($app)
	{
		// If we don't have a socket already, we're not running this using serve()
		if( ! $this->socket)
		{
			// Make sure we init it anyway
			$this->preFork();
		}
		
		$this->app = $app;
		
		$evloop = event_base_new();
		$evsock = event_new();
		$evsign = event_new();
		
		// Register an event on socket connection
		event_set($evsock, $this->socket, EV_READ | EV_PERSIST, array($this, 'evConnect'), $evloop);
		event_base_set($evsock, $evloop);
		
		// Graceful shutdown on SIGUSR1 as defined in AbstractDaemon, pcntl signalhandling does
		// not seem to work properly with libevent
		event_set($evsign, SIGUSR1, EV_SIGNAL | EV_PERSIST, array($this, 'shutdownGracefully'));
		event_base_set($evsign, $evloop);
		
		// Enable the events
		event_add($evsock);
		event_add($evsign);
		
		// Save the loop instance so we can shutdown gracefully
		$this->evloop = $evloop;
		
		event_base_loop($evloop);
	}
	
	/**
	 * Triggered when a new client has connected to the socket.
	 * 
	 * Registers a new event which is is triggered when the client socket has
	 * data to read.
	 * 
	 * @param  resource  socket
	 * @param  int       event flags
	 * @param  resource  libevent event base
	 */
	public function evConnect($fdlisten, $events, $evloop)
	{
		$fdconn = stream_socket_accept($fdlisten);
		
		if( ! $fdconn)
		{
			// TODO: What do we do here? Can happen sometimes apparently
			return;
		}
		
		stream_set_blocking($fdconn, 0);
		
		// Add event for pending reads
		$event = event_new();
		event_set($event, $fdconn, EV_READ | EV_PERSIST, array($this, 'evRead'), $event);
		event_base_set($event, $evloop);
		event_add($event);
		
		// Save $event from the evil garbage collector
		$this->clients[(int) $fdconn] = $event;
	}
	
	/**
	 * Triggered when a client socket has data to read, attempts to read a HTTP header
	 * from it and then parse it and pass it on to an Inject\Stack app.
	 * 
	 * If it fails it will return quickly and let the event loop process other
	 * requests. If it fails with a closed socket (return value from strea_get_line()
	 * is an empty string) it will call closeConnection() to close the socket and
	 * free the event associated with the socket.
	 * 
	 * @param  resource  client socket
	 * @param  int       event flags
	 * @param  resource  libevent event
	 * @return void
	 */
	public function evRead($fdconn, $events, $event)
	{
		$str = stream_get_line($fdconn, 4128, "\r\n\r\n");
		
		if($str === false)
		{
			// Not an end to the request yet, waiting for more data
			return;
		}
		elseif(empty($str))
		{
			// Empty string = closed connection, close it and return
			$this->closeConnection($fdconn, $event);
			
			return;
		}
		elseif(strlen($str) === 4128)
		{
			// Request-URI Too Long
			// TODO: Can we use the "431 Request Header Fields Too Large"
			/// response code? or should it be 400 Bad Request instead?
			$env = 414;
		}
		else
		{
			$env = $this->parseRequestHeader($str);
		}
		
		if( ! is_numeric($env))
		{
			$env['inject.input'] = $fdconn;
				
			list($env['REMOTE_ADDR'], $env['REMOTE_PORT']) = $this->getRemote($fdconn);
			
			empty($env['QUERY_STRING']) OR parse_str($env['QUERY_STRING'], $env['inject.get']);
				
			// Rename HTTP_CONTENT_LENGTH -> CONTENT_LENGTH
			if( ! empty($env['HTTP_CONTENT_LENGTH']))
			{
				$env['CONTENT_LENGTH'] = (int)$env['HTTP_CONTENT_LENGTH'];
				unset($env['HTTP_CONTENT_LENGTH']);
			}
				
			// Rename HTTP_CONTENT_TYPE -> CONTENT_TYPE
			if( ! empty($env['HTTP_CONTENT_TYPE']))
			{
				$env['CONTENT_TYPE'] = $env['HTTP_CONTENT_TYPE'];
				unset($env['HTTP_CONTENT_TYPE']);
					
				// Do we have a form request? If so, parse POST-data
				if(stripos($env['CONTENT_TYPE'], 'application/x-www-form-urlencoded') === 0)
				{
					// Parse!
					parse_str(stream_get_contents($env['inject.input'], empty($env['CONTENT_LENGTH']) ? -1 : $env['CONTENT_LENGTH']), $env['inject.post']);
				}
			}
			
			try
			{
				$app = $this->app;
				
				// Run the application!
				if($res = $app($env))
				{
					$this->httpResponse($fdconn, $res);
				}
				
				// Connection: close in either request or response, terminate connection
				if(( ! empty($env['HTTP_CONNECTION'])) && $env['HTTP_CONNECTION'] === 'close' OR
				   ( ! empty($res[1]['Connection'])) && $res[1]['Connection'] === 'close')
				{
					$this->closeConnection($fdconn, $event);
				}
			}
			catch(BaseException $e)
			{
				$this->closeConnection($fdconn, $event);
				
				throw $e;
			}
		}
		else
		{
			$status = Util::getHttpStatusText($env);
			
			fwrite($fdconn, "HTTP/1.1 $env ".$status."\r\nContent-Type: text/plain\r\nConnection: close\r\nContent-Length: ".strlen($status)."\r\n\r\n$status");
			
			$this->closeConnection($fdconn, $event);
		}
	}
	
	/**
	 * Closes the given connection file descriptor and removes and frees the given
	 * associated event.
	 * 
	 * @param  resource  client socket
	 * @param  resource  libevent event
	 * @return void
	 */
	protected function closeConnection($fdconn, $event)
	{
		event_del($event);
		event_free($event);
		
		// Remove protection from garbage collection
		unset($this->clients[(int) $fdconn]);
		unset($fdconn);
	}
	
	protected function shutdownGracefully()
	{
		// Exit next loop iteration
		event_base_loopexit($this->evloop);
	}
}
