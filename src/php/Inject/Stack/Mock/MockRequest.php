<?php
/*
 * Created by Martin Wernståhl on 2011-04-24.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace Inject\Stack\Mock;

use Inject\Stack\Builder;
use Inject\Stack\Middleware\Lint;

class MockRequest
{
	/**
	 * The application to run with the mock request.
	 * 
	 * @var Closure|ObjectImplementing__invoke
	 */
	protected $app;
	
	// ------------------------------------------------------------------------

	/**
	 * @param  Closure|ObjectImplementing__invoke  Inject\Stack application to run.
	 */
	public function __construct($app)
	{
		$this->app = $app;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Fires a mock request to the wrapped application.
	 * 
	 * @param  string  The URL or URI of the mocked request (if not all parts of
	 *                 the url are present, defaults will be used from the url
	 *                 "http://example.com:80/")
	 * @param  string  The http method to use, uppercase
	 * @param  array   Additional parameters, includes:
	 *                 - script_name for the SCRIPT_NAME
	 *                 - lint: set to true if to wrap the call in a Lint middleware
	 *                 - params: additional request parameters, added to inject.get
	 *                   if the request method is GET, otherwise it will be added to
	 *                   inject.post. The additions here will also reflect in
	 *                   inject.input and QUERY_STRING on the $env var
	 *                 - Additional parameters will be added to the $env var as is,
	 *                   with the rules that keys with no dots are only to contain strings
	 * @return array(int, array(string => string), string)
	 */
	public function request($url, $method = 'GET', array $options = array())
	{
		if( ! empty($options['lint']))
		{
			$app = new Lint();
			$app->setNext($this->app);
			
			unset($options['lint']);
		}
		else
		{
			$app = $this->app;
		}
		
		$env = $this->createEnvFrom($url, array_merge($options, array('method' => $method)));
		
		return $app($env);
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Creates an $env hash.
	 * 
	 * Special options:
	 * - method:       Sets the REQUEST_METHOD, default = GET
	 * - script_name:  Sets the SCRIPT_NAME, default = ""
	 * - params:       Sets additional GET parameters if method == GET, if
	 *                 method == POST, then it will set inject.post and also
	 *                 populate inject.input with the query-variant.
	 *                 Can be supplied in a query-string format
	 * 
	 * @param  string  URI or URL which will be parsed into the $env var
	 * @param  array   List of options to be added to the $env var
	 * @return array
	 */
	public function createEnvFrom($url, array $options = array())
	{
		$env  = $this->getDefaultEnv();
		$segs = \parse_url($url);
		
		$env['REQUEST_METHOD']    = empty($options['method'])      ? 'GET'         : $options['method'];
		$env['SCRIPT_NAME']       = empty($options['script_name']) ? ''            : $options['script_name'];
		$env['BASE_URI']          = empty($options['script_name']) ? ''            : dirname($options['script_name']);
		$env['SERVER_NAME']       = empty($segs['host'])           ? 'example.com' : $segs['host'];
		$env['SERVER_PORT']       = empty($segs['port'])           ? 80            : $segs['port'];
		$env['PATH_INFO']         = empty($segs['path'])           ? '/'           : $segs['path'];
		$env['QUERY_STRING']      = empty($segs['query'])          ? ''            : $segs['query'];
		$env['inject.url_scheme'] = empty($segs['scheme'])         ? 'http'        : $segs['scheme'];
		
		if( ! empty($options['params']))
		{
			if(is_string($options['params']))
			{
				parse_str($options['params'], $options['params']);
			}
			
			if($env['REQUEST_METHOD'] == 'GET')
			{
				parse_str($env['QUERY_STRING'], $tmp);
				
				$env['inject.get']   = array_merge($tmp, $options['params']);
				$env['QUERY_STRING'] = http_build_query($env['inject.get']);
			}
			else
			{
				$env['inject.post'] = $options['params'];
				$env['inject.input'] = http_build_query($env['inject.post']);
				$env['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
				$env['CONTENT_LENGTH'] = strlen($env['inject.input']);
			}
		}
		
		$used_opts = array(
			'method' => 0,
			'script_name' => 0,
			'params' => 0
			);
		
		foreach(array_diff_key($options, $used_opts) as $key => $value)
		{
			// Only allow keys conforming to standards, ie. only string values unless
			// the key has a dot
			if(is_string($value) OR strpos($key, ':') !== false)
			{
				$env[$key] = $value;
			}
		}
		
		// TODO: Add error catcher on logger which will throw exceptions
		
		return $env;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns the default $env hash.
	 * 
	 * @return array
	 */
	public function getDefaultEnv()
	{
		return array(
			'inject.version'    => Builder::VERSION,
			'inject.adapter'    => get_class($this),
			'inject.url_scheme' => 'http',
			'inject.input'      => '',
			'inject.cookies'    => array(),
			'inject.files'      => array(),
			'inject.get'        => array(),
			'inject.post'       => array(),
			'CONTENT_LENGTH'    => 0
		);
	}
}


/* End of file MockRequest.php */
/* Location: src/php/Inject/Stack/Mock */