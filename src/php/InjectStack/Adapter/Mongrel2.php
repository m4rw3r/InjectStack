<?php
/*
 * Created by Martin Wernståhl on 2011-03-06.
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
 */
class Mongrel2 implements AdapterInterface
{
	/**
	 * The application instance to run on every request.
	 * 
	 * @var Closure|ObjectImplementing__invoke
	 */
	protected $app;
	
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
	 * @param  Closure|ObjectImplementing__invoke  InjectStack application
	 * @param  string
	 * @param  string
	 * @param  string
	 */
	public function __construct($app, $uuid, $pull_addr, $pub_addr)
	{
		$this->app  = $app;
		$this->uuid = $uuid;
		
		$zmq_context = new ZMQContext();
		
		$this->pull_addr = $pull_addr;
		$this->pub_addr  = $pub_addr;
		
		$this->request  = $zmq_context->getSocket(ZMQ::SOCKET_PULL);
		$this->request->connect($pull_addr);
		
		$this->response = $zmq_context->getSocket(ZMQ::SOCKET_PUB);
		$this->response->connect($pub_addr);
		$this->response->setSockOpt(ZMQ::SOCKOPT_IDENTITY, $this->uuid);
	}
	
	// ------------------------------------------------------------------------

	/**
	 * The whole listen loop.
	 * 
	 * @return void
	 */
	public function listen()
	{
		echo "Listening on {$this->pull_addr} and responding on {$this->pub_addr}...\n";
		
		$app = $this->app;
		
		while(true)
		{
			list($uuid, $conn_id, $path, $headers, $msg) = $this->parseRequest($this->request->recv());
			
			// TODO: Add silent switch?
			echo "Got request from $uuid: {$headers['METHOD']} {$headers['PATH']}\n";
			
			$env = $this->createEnv($path, $headers, $msg);
			
			$this->sendResponse($uuid, $conn_id, $this->httpResponse($app($env)));
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
		$env = array();
		
		$env['REQUEST_METHOD'] = $headers['METHOD'];
		$env['REQUEST_URI']    = $headers['URI'];
		$env['PATH_INFO']      = $headers['PATH'];
		$env['QUERY_STRING']   = empty($headers['QUERY']) ? '' : $headers['QUERY'];
		$env['SCRIPT_NAME']    = '';   // TODO: Needs config value
		$env['SERVER_NAME']    = 'localhost'; // TODO: Needs config value
		$env['SERVER_PORT']    = 80;   // TODO: Needs config value
		$env['BASE_URI']       = '';   // TODO: Needs config value
		
		$env['inject.version']    = \InjectStack\Builder::VERSION;
		$env['inject.adapter']    = get_called_class();
		$env['inject.url_scheme'] = 'http';  // TODO: Proper code
		$env['inject.input']      = $msg;  // TODO: Parse
		
		// TODO: Code for these:
		$env['inject.cookies']    = array();
		$env['inject.files']      = array();
		
		parse_str($env['QUERY_STRING'], $env['inject.get']);
		parse_str($env['inject.input'], $env['inject.post']);
		
		foreach($headers as $hkey => $hval)
		{
			$env['HTTP_'.strtoupper(strtr($hkey, '-', '_'))] = $hval;
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
		
		list($bodylen, $msg) = explode(':', $msg, 2);
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
		
		$head = '';
		foreach($headers as $k => $v)
		{
			$head .= $k.': '.$v."\r\n";
		}
		
		return sprintf("HTTP/1.1 %s %s\r\n%s\r\n%s", $response_code, Util::getHttpStatusText($response_code), $head, $content);
	}
	
	// ------------------------------------------------------------------------
	
	/**
	 * Runs the supplied application with values fetched from the server environment
	 * and sends the output to the browser.
	 * 
	 * @param  \InjectStack\Builder|Closure|ObjectImplementing__invoke
	 * @return void
	 */
	public static function run($app)
	{
		// TODO: Move this to some configuration method or similar
		$server = new self($app, "B2D9FFB2-4DF9-4430-8E07-93F342009FE9", "tcp://127.0.0.1:9989", "tcp://127.0.0.1:9988");
		$server->listen();
	}
}


/* End of file Mongrel2.php */
/* Location: src/php/InjectStack/Adapter */