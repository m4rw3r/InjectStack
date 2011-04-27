<?php
/*
 * Created by Martin Wernståhl on 2011-04-25.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace InjectStack\Adapter;

use \ZMQ;
use \ZMQContext;
use \InjectStack\AdapterInterface;
use \InjectStack\Util;

/**
 * Acts as an adapter between the Mongrel2 server and the application stack.
 * 
 * NOTE: Applications starting with this Adapter will be PERSISTENT!
 * 
 * Requires ZeroMQ <http://www.zeromq.org/> and its PHP extension to be installed.
 */
class Mongrel2 implements AdapterInterface
{
	/**
	 * If this handler should output information about each request it receives.
	 * 
	 * @var boolean
	 */
	protected $debug = false;
	
	/**
	 * The application UUID.
	 * 
	 * @var string
	 */
	protected $uuid;
	
	/**
	 * The ZeroMQ PULL address.
	 * 
	 * @var string
	 */
	protected $pull_addr;
	
	/**
	 * The ZeroMQ PUB address.
	 * 
	 * @var string
	 */
	protected $pub_addr;
	
	/**
	 * The ZeroMQ PULL handler.
	 * 
	 * @var ZMQSocket
	 */
	protected $request;
	
	/**
	 * The ZeroMQ PUB handler.
	 * 
	 * @var ZMQSocket
	 */
	protected $response;
	
	/**
	 * Template for $env.
	 * 
	 * @var array(string => mixed)
	 */
	protected $default_env = array();
	
	/**
	 * @param  Closure|ObjectImplementing__invoke  InjectStack application
	 * @param  string
	 * @param  string
	 * @param  string
	 * @param  array(string => mixed)  Default $env data, use to set
	 *         SERVER_NAME, SERVER_PORT, SCRIPT_NAME and BASE_URI
	 */
	public function __construct($uuid, $pull_addr, $pub_addr, array $default_env = array(), $debug = false)
	{
		$this->uuid  = $uuid;
		$this->debug = $debug;
		
		$zmq_context = new ZMQContext();
		
		$this->pull_addr = $pull_addr;
		$this->pub_addr  = $pub_addr;
		
		$this->request  = $zmq_context->getSocket(ZMQ::SOCKET_PULL);
		$this->request->connect($pull_addr);
		
		$this->response = $zmq_context->getSocket(ZMQ::SOCKET_PUB);
		$this->response->connect($pub_addr);
		$this->response->setSockOpt(ZMQ::SOCKOPT_IDENTITY, $this->uuid);
		
		$this->default_env = array_merge(array(
			'SCRIPT_NAME'    => '',
			'SERVER_NAME'    => 'localhost',
			'SERVER_PORT'    => 80,
			'BASE_URI'       => '',
			'inject.version' => \InjectStack\Builder::VERSION,
			'inject.adapter' => get_called_class(),
			'inject.get'     => array(),
			'inject.post'    => array()
		), $default_env);
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Listens for requests from Mongrel2 and dispatches them to $app, and
	 * then returns the response to Mongrel2 if there is one.
	 * 
	 * NOTE:
	 * 
	 * If you use a \InjectStack\Builder instance, it is recommended to pass
	 * the value from \InjectStack\Builder->build() instead of the Builder
	 * instance itself. This will avoid the rebuilding of the stack for each
	 * request.
	 * 
	 * @param  \InjectStack\Builder|Closure|ObjectImplementing__invoke
	 * @return void
	 */
	public function run($app)
	{
		echo "Listening on {$this->pull_addr} and responding on {$this->pub_addr}...\n";
		
		while(true)
		{
			list($uuid, $conn_id, $path, $headers, $msg) = $this->parseRequest($this->request->recv());
			
			if($headers['METHOD'] == 'JSON' OR $path == '@*')
			{
				// TODO: Code
				continue;
			}
			
			$this->debug && print("Got request from $uuid: {$headers['METHOD']} {$headers['PATH']}");
			
			$env = $this->createEnv($path, $headers, $msg);
			
			// Call app, and if app returns != false, send to Mongrel2
			$response = $app($env);
			
			if($response)
			{
				$this->debug && print(' responding');
				
				$this->sendResponse($uuid, $conn_id, $this->httpResponse($response));
			}
			
			$this->debug && print("\n");
		}
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Creates the $env variable from parsed Mongrel request.
	 * 
	 * @param  string
	 * @param  array
	 * @param  string
	 * @return array
	 */
	public function createEnv($path, $headers, $msg)
	{
		$env = $this->default_env;
		
		$env['REQUEST_METHOD']    = $headers['METHOD'];
		$env['REQUEST_URI']       = $headers['URI'];
		$env['PATH_INFO']         = $headers['PATH'];
		$env['QUERY_STRING']      = empty($headers['QUERY']) ? '' : $headers['QUERY'];
		$env['inject.url_scheme'] = 'http';  // TODO: Proper code
		$env['inject.input']      = $msg;
		
		// TODO: Code for cookies, implement in middleware
		
		empty($env['QUERY_STRING']) OR parse_str($env['QUERY_STRING'], $env['inject.get']);
		
		foreach($headers as $hkey => $hval)
		{
			$env['HTTP_'.strtoupper(strtr($hkey, '-', '_'))] = $hval;
		}
		
		if( ! empty($env['HTTP_CONTENT_LENGTH']))
		{
			$env['CONTENT_LENGTH'] = (int)$env['HTTP_CONTENT_LENGTH'];
			unset($env['HTTP_CONTENT_LENGTH']);
		}
		
		if( ! empty($env['HTTP_CONTENT_TYPE']))
		{
			$env['CONTENT_TYPE'] = $env['HTTP_CONTENT_TYPE'];
			unset($env['HTTP_CONTENT_TYPE']);
			
			// Do we have a form request?
			if(stripos($env['CONTENT_TYPE'], 'application/x-www-form-urlencoded') === 0)
			{
				// Parse!
				parse_str($env['inject.input'], $env['inject.post']);
			} 
		}
		
		return $env;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Parses a mongrel request.
	 * 
	 * @return array(string, int, string, array, string)
	 */
	public function parseRequest($msg)
	{
		list($uuid, $conn_id, $path, $msg) = explode(' ', $msg, 4);
		
		list($headlen, $msg) = explode(':', $msg, 2);
		$header  = substr($msg, 0, (int) $headlen);
		$msg     = substr($msg, (int) $headlen);
		
		if( ! $msg[0] == ',')
		{
			return false;
		}
		
		list($bodylen, $msg) = explode(':', substr($msg, 1), 2);
		$body    = substr($msg, 0, (int) $bodylen);
		$msg     = substr($msg, (int) $bodylen);
		
		if( ! $msg[0] == ',')
		{
			return false;
		}
		
		return array($uuid, $conn_id, $path, json_decode($header, true), $body);
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Sends a response back to the Mongrel2 server.
	 * 
	 * @param  string
	 * @param  string
	 * @param  string
	 * @return void
	 */
	public function sendResponse($uuid, $conn_id, $body)
	{
		$header = sprintf('%s %d:%s,', $uuid, strlen($conn_id), $conn_id);
		
		$this->response->send($header.' '.$body);
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Creates a HTTP response to be sent to Mongrel2.
	 * 
	 * @param  array  array(response_code, array(header_title => header_content), content)
	 * @return string
	 */
	protected function httpResponse(array $response)
	{
		$response_code = $response[0];
		$headers = $response[1];
		$content = $response[2];
		
		if( ! isset($headers['Content-Type']))
		{
			$headers['Content-Type'] = 'text/html';
		}
		
		$headers['Content-Length'] = strlen($content);
		
		$head = array();
		foreach($headers as $k => $v)
		{
			$head[] = $k.': '.$v;
		}
		
		return sprintf("HTTP/1.1 %s %s\r\n%s\r\n\r\n%s", $response_code, Util::getHttpStatusText($response_code), implode("\r\n", $head), $content);
	}
}


/* End of file Mongrel2.php */
/* Location: src/php/InjectStack/Adapter */