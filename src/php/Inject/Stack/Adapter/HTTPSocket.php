<?php
/*
 * Created by Martin Wernståhl on 2011-04-25.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace Inject\Stack\Adapter;

use \Exception;
use \Inject\Stack\Util;

/**
 * Class which acts as a HTTP server directly in PHP.
 * 
 * To change which host and port to listen on, change
 * $default_env[SERVER_NAME] and $default[SERVER_PORT].
 * 
 * $default_env:
 * <code>
 * $this->default_env    = array_merge(array(
 *     'SERVER_NAME'       => 'localhost',
 *     'SERVER_PORT'       => 80,
 *     'BASE_URI'          => '',
 *     'SCRIPT_NAME'       => '',
 *     'inject.version'    => \Inject\Stack\Builder::VERSION,
 *     'inject.adapter'    => get_called_class(),
 *     'inject.get'        => array(),
 *     'inject.post'       => array(),
 *     'inject.url_scheme' => 'http'
 * )
 * </code>
 * 
 * If used with serve() (recommended), several worker processes will be
 * started which all share the same server socket.
 */
class HTTPSocket extends AbstractDaemon
{
	/**
	 * The shared socket resource.
	 * 
	 * @var resource
	 */
	protected $socket = null;
	
	/**
	 * Listen for connections while this is true.
	 * 
	 * @var boolean
	 */
	protected $doRun = true;
	
	/**
	 * The default environment hash.
	 * 
	 * @var array(string => mixed)
	 */
	protected $default_env = array();
	
	/**
	 * The list of allowed HTTP methods.
	 * 
	 * @var array(string)
	 */
	protected $allowed_methods = array('OPTIONS', 'GET', 'POST', 'PUT', 'DELETE', 'HEAD', 'TRACE', 'CONNECT');
	
	/**
	 * The timeout for the socket, when exceeded, this process will exit, seconds.
	 * 
	 * Can be used to auto-restart the worker processes during low load when running
	 * this as adapter as a daemon.
	 * 
	 * @var int
	 */
	protected $socket_timeout = 3600;
	
	/**
	 * Buffer size in bytes for the case when streaming from a resource handle.
	 * 
	 * @var int
	 */
	protected $buffer_size = 8192;
	
	// ------------------------------------------------------------------------

	/**
	 * @param  array(string => mixed)  Array with the default contents of the $env
	 *                                 variable, will be merged with the default array
	 * @param  int                     The timeout for the socket, when exceeded, this
	 *                                 process will exit, seconds.
	 * @param  array(string)           List of allowed HTTP methods, will override
	 *                                 the default list
	 */
	public function __construct(array $default_env = array(), $socket_timeout = 3600, $allowed_methods = null)
	{
		$this->socket_timeout = $socket_timeout;
		$this->default_env    = array_merge(array(
			'SERVER_NAME'       => 'localhost',
			'SERVER_PORT'       => 80,
			'BASE_URI'          => '',
			'SCRIPT_NAME'       => '',
			'inject.version'    => \Inject\Stack\Builder::VERSION,
			'inject.adapter'    => get_called_class(),
			'inject.get'        => array(),
			'inject.post'       => array(),
			'inject.url_scheme' => 'http'
		), $default_env);
		
		empty($allowed_methods) OR $this->allowed_methods = $allowed_methods;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Sets the buffer size for streaming from a resource handle, number of bytes.
	 * 
	 * @param  int
	 * @return void
	 */
	public function setBufferSize($value)
	{
		$this->buffer_size = $value;
	}
	
	// ------------------------------------------------------------------------
	
	/**
	 * Creates the shared server stream socket.
	 * 
	 * @return void
	 */
	protected function preFork()
	{
		if( ! ($this->socket = @stream_socket_server('tcp://'.$this->default_env['SERVER_NAME'].':'.$this->default_env['SERVER_PORT'], $err_no, $err_msg)))
		{
			// TODO: Proper exception
			throw new Exception("Could not create socket for tcp://{$this->default_env['SERVER_NAME']}:{$this->default_env['SERVER_PORT']}: $err_no: $err_msg");
		}
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Will start up a server responding on tcp://$default_env[SERVER_NAME]:$default_env[SERVER_PORT].
	 * 
	 * @param  Closure|ObjectImplementing__invoke
	 * @return void
	 */
	public function run($app)
	{
		// If we don't have a socket already, we're not running this using serve()
		if( ! $this->socket)
		{
			// Make sure we init it anyway
			$this->preFork();
		}
		
		// Main run loop
		while($this->doRun && ($conn = @stream_socket_accept($this->socket, $this->socket_timeout)))
		{
			try
			{
				// This loop is for the cases when we only get a partial header, then stream_get_line() returns false
				while(($str = stream_get_line($conn, 4128, "\r\n\r\n")) === false)
				{
					// Empty
				}
				
				$env = $this->parseRequestHeader($str);
				
				if( ! is_numeric($env))
				{
					$env['inject.input'] = $conn;
					
					list($env['REMOTE_ADDR'], $env['REMOTE_PORT']) = $this->getRemote($conn);
					
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
					
					// Run the application!
					if($res = $app($env))
					{
						$this->httpResponse($conn, $res);
					}
				}
				else
				{
					// We have an error from parsing the request:
					fwrite($conn, "HTTP/1.1 $env ".Util::getHttpStatusText($env)."\r\nContent-Length: 0\r\n\r\n");
					// TODO: Add content message?
				}
			}
			catch(Exception $e)
			{
				@fclose($conn);
				
				throw $e;
			}
			
			fclose($conn);
		}
	}
	
	// ------------------------------------------------------------------------
	
	/**
	 * Parses the request header and populates a clone of the $default_env.
	 * 
	 * Does not completely follow Inject\Stack SPEC:
	 * * Missing renames of HTTP_CONTENT_TYPE and HTTP_CONTENT_LENGTH
	 * * Missing Query-string and POST-data parsing
	 * * Missing REMOTE_ADDR assignment
	 * * Does not add the input stream
	 * 
	 * @param  string  The HTTP request until "\r\n\r\n"
	 * @return array(string => mixed)  The Environment hash
	 */
	protected function parseRequestHeader($str)
	{
		$data = explode("\r\n", $str, 2);
		
		if(count($data) != 2)
		{
			// Error parsing request
			return 400;
		}
		
		$reqline = explode(' ', $data[0]);
		
		if(count($reqline) != 3)
		{
			// Error parsing request
			return 400;
		}
		
		if( ! in_array($reqline[0], $this->allowed_methods))
		{
			// Not allowed method
			return 501;
		}
		
		if($reqline[2] !== 'HTTP/1.1')
		{
			// Not correct HTTP protocol
			return 505;
		}
		
		$env = $this->default_env;
		
		// Just provide a nonsense default key, so we won't error out in case of failure
		$last_key = ' ';
		foreach(explode("\r\n", $data[1]) as $line)
		{
			// Wrapped header:
			if(strpos($line, ' ') === 0 OR strpos($line, "\t") === 0)
			{
				$env[$last_key] .= ltrim($line, " \t");
				
				continue;
			}
			
			if( ! ($pos = strpos($line, ':')))
			{
				// Failure parsing headers
				return 400;
			}
			
			$last_key = 'HTTP_'.strtoupper(strtr(substr($line, 0, $pos), '-', '_'));
			
			$env[$last_key] = ltrim(substr($line, $pos + 1), " \t");
		}
		
		$path = parse_url($reqline[1]);
		
		$env['REQUEST_METHOD'] = $reqline[0];
		$env['REQUEST_URI']    = $reqline[1];
		$env['PATH_INFO']      = $path['path'];
		$env['QUERY_STRING']   = empty($path['query']) ? '' : $path['query'];
		$env['HTTP_VERSION']   = $reqline[2];
		
		return $env;
	}
	
	// ------------------------------------------------------------------------
	
	/**
	 * Returns the remote IP and port for the supplied connection.
	 * 
	 * @param  resource
	 * @return array(string, int)
	 */
	protected function getRemote($conn)
	{
		$remote = stream_socket_get_name($conn, true);
		
		$pos = strrpos($remote, ':');
		
		if( ! $pos)
		{
			return array($remote, '');
		}
		else
		{
			return array(substr($remote, 0, $pos), (int)substr($remote, $pos + 1));
		}
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Creates a HTTP response to be sent as the response.
	 * 
	 * @param  resource  The socket connection stream
	 * @param  array     array(response_code, array(header_title => header_content), content)
	 * @return string
	 */
	protected function httpResponse($conn, array $response)
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
		
		// Send HTTP Response and header
		fwrite($conn, sprintf("HTTP/1.1 %s %s\r\n%s\r\n\r\n", $response_code, Util::getHttpStatusText($response_code), implode("\r\n", $head)));
		
		// Send body
		if( ! is_resource($content))
		{
			fwrite($conn, $content);
		}
		else
		{
			// Write the stream to the other stream
			if($use_chunked)
			{
				// Chunked encoding
				while( ! feof($content))
				{
					$data = fread($content, $this->buffer_size);
					fwrite($conn, sprintf('%X', strlen($data))."\r\n".$data."\r\n");
				}
				
				// Terminate
				fwrite($conn, "0\r\n\r\n");
			}
			else
			{
				while( ! feof($content))
				{
					fwrite($conn, fread($content, $this->buffer_size));
				}
			}
			
			fclose($content);
		}
	}
	
	// ------------------------------------------------------------------------
	
	protected function shutdownGracefully()
	{
		$this->doRun = false;
	}
}


/* End of file HTTPSocket.php */
/* Location: src/php/Inject/Stack/Adapter */