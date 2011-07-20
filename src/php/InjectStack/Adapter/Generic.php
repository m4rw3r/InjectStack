<?php
/*
 * Created by Martin Wernståhl on 2011-03-06.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace InjectStack\Adapter;

use \InjectStack\AdapterInterface;
use \InjectStack\Util;

/**
 * Acts as an adapter between the server and the application stack, presumes MODPHP
 * or FastCGI or similar.
 */
class Generic implements AdapterInterface
{
	/**
	 * Runs the supplied application with values fetched from the server environment
	 * and sends the output to the browser.
	 * 
	 * @param  \InjectStack\Builder|Closure|ObjectImplementing__invoke
	 * @return void
	 */
	public function run($app)
	{
		$env = $_SERVER;
		
		$env['inject.version']    = \InjectStack\Builder::VERSION;
		$env['inject.adapter']    = get_called_class();
		$env['inject.url_scheme'] = (( ! empty($env['HTTPS'])) && $env['HTTPS'] != 'off') ? 'https' : 'http';
		// No need to close this stream, PHP automatically closes when this process reloads
		$env['inject.input']      = fopen('php://input', 'r');
		
		// SCRIPT_NAME + PATH_INFO = URI - QUERY_STRING
		$env['SCRIPT_NAME'] == '/'  && $env['SCRIPT_NAME']  = '';
		isset($env['QUERY_STRING']) OR $env['QUERY_STRING'] = '';
		$env['PATH_INFO']            = '/'.trim($env['PATH_INFO'], '/');
		$env['REQUEST_METHOD']       = Util::checkRequestMethod($env['REQUEST_METHOD']);
		
		$env['BASE_URI']             = dirname($env['SCRIPT_NAME']);
		
		if(empty($env['QUERY_STRING']))
		{
			$env['inject.get'] = array();
		}
		else
		{
			parse_str($env['QUERY_STRING'], $env['inject.get']);
		}
		
		if($env['REQUEST_METHOD'] != 'POST' && ! empty($env['CONTENT_TYPE']) &&
			stripos($env['CONTENT_TYPE'], 'application/x-www-form-urlencoded') === 0)
		{
			// $_POST not be accurate, depends on request type, read from php://input
			parse_str(stream_get_contents($env['inject.input'], empty($env['CONTENT_LENGTH']) ? -1 : $env['CONTENT_LENGTH']), $env['inject.post']);
		}
		
		$this->respondWith($app($env));
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Sends the response to the browser.
	 * 
	 * @param  array  array(response_code, array(header_title => header_content), content)
	 * @return void
	 */
	function respondWith(array $response)
	{
		$response_code = $response[0];
		$headers = $response[1];
		$content = $response[2];
		
		header(sprintf('HTTP/1.1 %s %s', $response_code, Util::getHttpStatusText($response_code)));
		
		foreach($headers as $k => $v)
		{
			header($k.': '.$v);
		}
		
		if( ! is_resource($content))
		{
			echo $content;
		}
		else
		{
			// Write the stream to the output stream
			// TODO: Ability to adjust buffer size?
			while( ! feof($content))
			{
				echo fread($content, 8192);
			}
			
			fclose($content);
		}
	}
}


/* End of file Generic.php */
/* Location: src/php/InjectStack/Adapter */