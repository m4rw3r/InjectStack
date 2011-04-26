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
 * Acts as an adapter between the server and the application stack.
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
	public static function run($app)
	{
		$env = $_SERVER;
		
		$env['inject.version']    = \InjectStack\Builder::VERSION;
		$env['inject.adapter']    = get_called_class();
		$env['inject.url_scheme'] = (( ! empty($env['HTTPS'])) && $env['HTTPS'] != 'off') ? 'https' : 'http';
		$env['inject.input']      = file_get_contents('php://input');
		
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
			parse_str($env['inject.input'], $env['inject.post']);
		}
		
		static::respondWith($app($env));
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Sends the response to the browser.
	 * 
	 * @param  array  array(response_code, array(header_title => header_content), content)
	 * @return void
	 */
	protected static function respondWith(array $response)
	{
		$response_code = $response[0];
		$headers = $response[1];
		$content = $response[2];
		
		header(sprintf('HTTP/1.1 %s %s', $response_code, Util::getHttpStatusText($response_code)));
		
		if( ! isset($headers['Content-Type']))
		{
			$headers['Content-Type'] = 'text/html';
		}
		
		$headers['Content-Length'] = strlen($content);
		
		foreach($headers as $k => $v)
		{
			header($k.': '.$v);
		}
		
		echo $content;
	}
}


/* End of file Generic.php */
/* Location: src/php/InjectStack/Adapter */