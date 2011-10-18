<?php
/*
 * Created by Martin Wernståhl on 2011-04-25.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace Inject\Stack\Adapter;

use \Closure;
use \ZMQ;
use \ZMQContext;
use \ZMQException;
use \Inject\Stack\Util;

/**
 * Acts as an adapter between the Mongrel2 server and the application stack.
 * 
 * NOTE: Applications starting with this Adapter will be PERSISTENT!
 * 
 * Requires ZeroMQ <http://www.zeromq.org/> and its PHP extension to be installed.
 * 
 * Requires PCNTL <http://php.net/manual/en/book.pcntl.php> if you plan to use serve()
 * to spawn multiple worker processes, see AbstractDaemon for more information.
 */
class Mongrel2 extends AbstractDaemon
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
	 * Loop in run() while this is true.
	 * 
	 * @var boolean
	 */
	protected $do_run = true;
	
	/**
	 * Buffer size in bytes for the case when streaming from a resource handle.
	 * 
	 * Default: 8 KiB
	 * 
	 * @var int
	 */
	protected $buffer_size = 8192;
	
	/**
	 * Maximum size of the in-memory copy of the request, bytes.
	 * 
	 * Default: 2 MiB
	 * 
	 * @var int
	 */
	protected $maxmem = 2097152;
	
	/**
	 * @param  string
	 * @param  string
	 * @param  string
	 * @param  array(string => mixed)  Default $env data, use to set
	 *         SERVER_NAME, SERVER_PORT, SCRIPT_NAME and BASE_URI
	 * @param  boolean  If to print received requests
	 */
	public function __construct($uuid, $pull_addr, $pub_addr, array $default_env = array(), $debug = false)
	{
		$this->uuid  = $uuid;
		$this->debug = $debug;
		
		$this->pull_addr = $pull_addr;
		$this->pub_addr  = $pub_addr;
		
		$this->default_env = array_merge(array(
			'SERVER_NAME'    => 'localhost',
			'SERVER_PORT'    => 80,
			'BASE_URI'       => '',
			'inject.version' => \Inject\Stack\Builder::VERSION,
			'inject.adapter' => get_called_class(),
			'inject.get'     => array(),
			'inject.post'    => array()
		), $default_env);
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Sets the buffer size for streaming from a resource handle, number of bytes.
	 * 
	 * Default: 8 KiB
	 * 
	 * @param  int   bytes
	 * @return void
	 */
	public function setBufferSize($value)
	{
		$this->buffer_size = $value;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Sets the maximum size of the in-memory copy of the request in bytes,
	 * if the request exceeds this, it will instead be written to a temporary file.
	 * 
	 * Default: 2 MiB
	 * 
	 * @param  int   bytes
	 * @return void
	 */
	public function setMaxTempMemory($value)
	{
		$this->maxmem = $value;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Listens for requests from Mongrel2 and dispatches them to $app, and
	 * then returns the response to Mongrel2 if there is one.
	 * 
	 * Use serve() instead to create multiple children and a monitor process
	 * which will respawn any exited children.
	 * 
	 * NOTE:
	 * 
	 * If you use a \Inject\Stack\Builder instance, it is recommended to pass
	 * the value from \Inject\Stack\Builder->build() instead of the Builder
	 * instance itself. This will avoid the rebuilding of the stack for each
	 * request.
	 * 
	 * @param  \Inject\Stack\Builder|Closure|ObjectImplementing__invoke
	 * @return void
	 */
	public function run($app)
	{
		// Can't share ZeroMQ sockets, so we need one per child
		$zmq_context = new ZMQContext();
		
		$this->request  = $zmq_context->getSocket(ZMQ::SOCKET_PULL);
		$this->request->connect($this->pull_addr);
		
		$this->response = $zmq_context->getSocket(ZMQ::SOCKET_PUB);
		$this->response->connect($this->pub_addr);
		$this->response->setSockOpt(ZMQ::SOCKOPT_IDENTITY, $this->uuid);
		
		$this->debug && print("Listening on {$this->pull_addr} and responding on {$this->pub_addr}...\n");
		
		while($this->do_run)
		{
			try
			{
				// Open place to stash data in, we might get a lot
				// We can't reuse this as it might switch to a file and that is
				// strictly a one-way operation, no way to get a memory based temp again
				$fp = fopen('php://temp/maxmemory:'.$this->maxmem, 'rw');
				
				// Read data from ZeroMQ and stash in temporary memory
				fwrite($fp, $this->request->recv());
				rewind($fp);
				
				list($uuid, $conn_id, $path, $headers, $bodylen) = $this->parseRequest($fp);
				
				if($headers['METHOD'] == 'JSON' OR $path == '@*')
				{
					// TODO: Code
					continue;
				}
				
				$this->debug && print("Got request from $uuid: {$headers['METHOD']} {$headers['PATH']}");
				
				$env = $this->createEnv($path, $headers, $fp, $bodylen);
				
				// Call app, and if app returns != false, send to Mongrel2
				$response = $app($env);
				
				// Close temp-storage
				fclose($fp);
				
				if($response)
				{
					$this->debug && print(' responding');
					
					$this->httpResponse($uuid, $conn_id, $response);
				}
				
				$this->debug && print("\n");
			}
			catch(ZMQException $e)
			{
				// Close temp-storage
				@fclose($fp);
				
				// TODO: Is this an ok way of suicide?
				die("ZeroMQ: ".$e->getMessage()."\n");
			}
		}
	}
	
	// ------------------------------------------------------------------------
	
	protected function shutdownGracefully()
	{
		// Stop the run loop
		$this->do_run = false;
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
	public function createEnv($path, $headers, $msg, $bodylen)
	{
		$env = $this->default_env;
		
		$env['REMOTE_ADDR']       = $headers['x-forwarded-for'];
		$env['REQUEST_METHOD']    = strtoupper($headers['METHOD']);
		$env['REQUEST_URI']       = $headers['URI'];
		$env['SCRIPT_NAME']       = $headers['PATTERN'] == '/' ? '' : $headers['PATTERN'];
		$env['PATH_INFO']         = '/'.trim(substr($headers['PATH'], strlen($headers['PATTERN'])), '/');
		$env['QUERY_STRING']      = empty($headers['QUERY']) ? '' : $headers['QUERY'];
		$env['inject.url_scheme'] = 'http';  // TODO: Proper code
		$env['inject.input']      = $msg;
		
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
				parse_str(stream_get_contents($env['inject.input'], empty($env['CONTENT_LENGTH']) ? $bodylen : $env['CONTENT_LENGTH']), $env['inject.post']);
			}
		}
		
		return $env;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Parses a mongrel request.
	 * 
	 * @param  stream  The request string from ZeroMQ
	 * @return array(string, int, string, array)
	 */
	public function parseRequest($fp)
	{
		// Format:
		// UUID CONN_ID PATH headlen:header,bodylen:body,
		// TODO: Tweak the numeric values below so we won't read unnecessary data
		$uuid    = stream_get_line($fp, 255, ' ');
		$conn_id = stream_get_line($fp, 255, ' ');
		$path    = stream_get_line($fp, 1024, ' ');
		$headlen = stream_get_line($fp, 255, ':');
		$header  = fread($fp, (int) $headlen);
		
		if( ! fgetc($fp) == ',')
		{
			return false;
		}
		
		$bodylen = stream_get_line($fp, 255, ':');
		// TODO: This is probably not needed, not sure though:
		// Truncate down to the end of body, right before the ","
		// 5 = num delimiters - 1
		// ftruncate($fp, strlen($uuid) + strlen($conn_id) + strlen($path) + strlen($headlen) + strlen($header) + strlen($bodylen) + 5);
		
		return array($uuid, $conn_id, $path, json_decode($header, true), $bodylen);
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Sends a response back to the Mongrel2 server.
	 * 
	 * @param  string  The handle UUID
	 * @param  string  The connection id, or list of connection ids separated with spaces
	 * @param  string  Response string
	 * @return void
	 */
	public function sendResponse($uuid, $conn_id, $body)
	{
		$this->response->send(sprintf('%s %d:%s,', $uuid, strlen($conn_id), $conn_id).' '.$body);
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Creates a HTTP response to be sent to Mongrel2.
	 * 
	 * @param  string  The UUID of this handle
	 * @param  string  The connection id, or list of connection ids separated with spaces
	 * @param  array   array(response_code, array(header_title => header_content), content)
	 * @return string
	 */
	protected function httpResponse($uuid, $conn_id, array $response)
	{
		// If to use the chunked encoding
		$use_chunked = false;
		
		// Split the return array:
		$response_code = $response[0];
		$headers = $response[1];
		$content = $response[2];
		
		// Set Content-Length if it is missing:
		if(empty($headers['Content-Length']) && empty($headers['Transfer-Encoding']) && ! empty($content))
		{
			if( ! is_resource($content))
			{
				// Plain text response, no chance that it will differ in size once a string
				$content = (String) $content;
				$headers['Content-Length'] = strlen($content);
			}
			else
			{
				// Resources can be pretty strange, use chunked transfer encoding
				$use_chunked = true;
				$headers['Transfer-Encoding'] = 'chunked';
			}
		}
		
		$head = array();
		foreach($headers as $k => $v)
		{
			$head[] = $k.': '.$v;
		}
		
		// Create HTTP header
		$head = sprintf("HTTP/1.1 %s %s\r\n%s\r\n\r\n", $response_code, Util::getHttpStatusText($response_code), implode("\r\n", $head));
		
		// Send body
		if( ! is_resource($content))
		{
			$this->sendResponse($uuid, $conn_id, $head.$content);
		}
		else
		{
			$this->sendResponse($uuid, $conn_id, $head);
			
			// Write the stream to the other stream
			if($use_chunked)
			{
				// Chunked encoding
				while( ! feof($content))
				{
					$data = fread($content, $this->buffer_size);
					$this->sendResponse($uuid, $conn_id, sprintf('%X', strlen($data))."\r\n".$data."\r\n");
				}
				
				// Terminate
				$this->sendResponse($uuid, $conn_id, "0\r\n\r\n");
			}
			else
			{
				while( ! feof($content))
				{
					$this->sendResponse($uuid, $conn_id, fread($content, $this->buffer_size));
				}
			}
			
			fclose($content);
		}
	}
}


/* End of file Mongrel2.php */
/* Location: src/php/Inject/Stack/Adapter */