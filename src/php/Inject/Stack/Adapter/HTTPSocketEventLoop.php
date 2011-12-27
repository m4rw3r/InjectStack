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
 * Test implementation of persistent HTTP connections using libevent,
 * still supports multiple processes using the same socket.
 * 
 * Requires the libevent extension for PHP.
 */
class HTTPSocketEventLoop extends HTTPSocket
{
	/**
	 * Event code for PHP exception closing the bufferevent.
	 */
	const EVBUFFER_PHP_EXCEPTION = 4096;
	
	/**
	 * Event code for socket connection closed.
	 */
	const EVBUFFER_PHP_CONNECTION_CLOSE = 4096;
	
	protected $clients = array();
	
	/**
	 * The application instance to run.
	 * 
	 * @var Closure|MiddlewareInterface|ObjectImplementing__invoke
	 */
	protected $app = null;
	
	public function preFork()
	{
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
		
		$evloop = event_base_new();
		$evsock = event_new();
		
		// echo "Setting up eventConnection";
		// Register an event on socket connection
		event_set($evsock, $this->socket, EV_READ | EV_PERSIST, array($this, 'evConnect'), $evloop);
		event_base_set($evsock, $evloop);
		
		// Enable the event
		event_add($evsock);
		
		$this->app = $app;
		
		// echo "Running eventLoop";
		
		event_base_loop($evloop);
		
		//event_base_free($evloop);
	}
	
	public function evConnect($fdlisten, $events, $evloop)
	{
		if( ! ($events & EV_READ))
		{
			// TODO: Needed?
			throw new BaseException(sprintf("Unknown event type in %s: 0x%hx", __METHOD__, $events));
		}
		
		$fdconn = stream_socket_accept($fdlisten);
		
		if( ! $fdconn)
		{
			// TODO: What do I do here? Can happen sometimes apparently
			return;
		}
		
		stream_set_blocking($fdconn, 0);
		
		$client = new EventLoopClient();
		
		$this->clients[] = $client;
		
		// TODO: Replace evBufRead with a closure?
		$evbuf = event_buffer_new($fdconn, array($this, 'evBufRead'), null, array($this, 'evBufError'), $client);
		event_buffer_base_set($evbuf, $evloop);
		// Read and write timeout
		event_buffer_timeout_set($evbuf, 60, 60);
		// Set lower and upper bound where the read callback will be triggered (0 = immediate)
		event_buffer_watermark_set($evbuf, EV_READ, 1, 0xffffff);
		event_buffer_priority_set($evbuf, 10);
		
		$client->fdconn = $fdconn;
		$client->evbuf  = $evbuf;
		
		event_buffer_enable($evbuf, EV_READ | EV_PERSIST);
	}
	
	public function evBufRead($evbuf, $client)
	{
		// TODO: What about this number?
		// TODO: Replace requestBuffer with an in-memory php://temp resource?
		$client->requestbuffer .= event_buffer_read($evbuf, 4128);
	
		if(strlen($client->requestbuffer) > 4128)
		{
			$env = 414;
		}
		// TODO: Is this a good solution?
		elseif(($pos = strpos($client->requestbuffer, "\r\n\r\n")) === false)
		{
			// We have not yet got a complete request, wait for next part
			return;
		}
		else
		{
			$request = substr($client->requestbuffer, 0, $pos);
			$client->requestbuffer = substr($client->requestbuffer, $pos + 4);
		
			$env = $this->parseRequestHeader($request);
		}
		
		if( ! is_numeric($env))
		{
			// TODO: What to do here? We need to have this as a stream or file-descriptor
			$env['inject.input'] = $client->fdconn;
				
			list($env['REMOTE_ADDR'], $env['REMOTE_PORT']) = $this->getRemote($client->fdconn);
			
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
					// TODO: Can we write directly to the client socket, or do we *have*
					// to use a bufferevent? So far it looks like bufferevent is not needed
					// as we are not handling multiple requests on the same connection simultaneously
					$this->httpResponse($client->fdconn, $res);
				}
				
				// Connection: close in either request or response, terminate connection
				if(( ! empty($env['HTTP_CONNECTION'])) && $env['HTTP_CONNECTION'] === 'close' OR
				   ( ! empty($res[1]['Connection'])) && $res[1]['Connection'] === 'close')
				{
					$this->evBufError($evbuf, self::EVBUFFER_PHP_CONNECTION_CLOSE, $client);
				}
			}
			catch(BaseException $e)
			{
				$this->evBufError($evbuf, self::EVBUFFER_PHP_EXCEPTION, $client);
				
				throw $e;
			}
		}
		else
		{
			$status = Util::getHttpStatusText($env);
			
			fwrite($client->fdconn, "HTTP/1.1 $env ".$status."\r\nContent-Type: text/plain\r\nConnection: close\r\nContent-Length: ".strlen($status)."\r\n\r\n$status");
			
			$this->evBufError($evbuf, self::EVBUFFER_PHP_EXCEPTION, $client);
		}
	}
	
	public function evBufError($evbuf, $error, $client)
	{
		if($error & \EVBUFFER_EOF)
		{
			// echo "Remote host ".$id." disconnected";
		}
		elseif($error & \EVBUFFER_TIMEOUT)
		{
			// echo "Remote host ".$id." timed out");
		}
		elseif($error & (self::EVBUFFER_PHP_EXCEPTION | self::EVBUFFER_PHP_CONNECTION_CLOSE))
		{
			// TODO: Ignore?
		}
		else
		{
			// echo sprintf("A socket error occurred: 0x%hx", $error);
		}
		
		// Freeing stuff here:
		event_buffer_disable($client->evbuf, \EV_READ | \EV_WRITE);
		event_buffer_free($client->evbuf);
		
		// TODO: fclose() needed? Won't the event_buffer auto-clean it when it is no longer referenced?
		//fclose($client->fdconn);
		unset($client->evbuf, $client->fdconn, $client->requestbuffer);
		
		unset($this->clients[array_search($client, $this->clients)]);
	}
}

// TODO: Move to separate file, only here now as we're testing to see if it is viable to use libevent
class EventLoopClient
{
	/**
	 * The socket connection to this client.
	 * 
	 * @var resource (socket)
	 */
	public $fdconn;
	
	/**
	 * Bufferevent buffer from libevent which buffers the connection to this client.
	 * 
	 * @var resource (bufferevent)
	 */
	public $evbuf;
	
	/**
	 * String buffer for the received request string.
	 * 
	 * TODO: Maybe replace with a memory buffer per request and then free them on close?
	 * 
	 * @var string
	 */
	public $requestbuffer = '';
}



