<?php
/*
 * Created by Martin Wernståhl on 2011-03-06.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace InjectStack;

/**
 * Endpoint which performs string matching against segments of $env['PATH_INFO']
 * to determine which callback to call.
 * 
 * If it does not match any string (if "/" is a matcher, it will always match
 * that as a last resort) it will return a 404 response without headers or body
 * (use a middleware to generate a proper 404 page).
 * 
 * If it does match, then the supplied callback will be called with $env, but with
 * some slight modifications:
 * 
 * - PATH_INFO will now contain everything after the match, so "/foo" matching "/foo/bar"
 *             will result in PATH_INFO containing "/bar"
 * - SCRIPT_NAME will have the matched part of the path appended
 * - urlmap.orig.SCRIPT_NAME will contain the original SCRIPT_NAME
 * 
 * Can be used to map several sub-applications on different URLs:
 * <code>
 * $map = new UrlMapEndpoint(array(
 *     'admin' => function($env)
 *     {
 *         return /* Create and call admin app instance * /;
 *     },
 *     '/' => function($env)
 *     {
 *         return /* Create and call default app instance * /;
 *     }
 * ));
 * </code>
 */
class UrlMapEndpoint
{
	/**
	 * List of apps to attempt.
	 * 
	 * @var array(callback)
	 */
	protected $map = array();
	
	// ------------------------------------------------------------------------

	/**
	 * @param  array(string => callback)
	 */
	public function __construct(array $map = array())
	{
		$this->remap($map);
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Adds the supplied string and callback pairs to this UrlMapEndpoint.
	 * 
	 * @param  array(string => callback)
	 * @return void
	 */
	public function remap(array $map)
	{
		foreach($map as $url => $destination)
		{
			$url = '/'.trim($url, '/');
			
			$this->map[$url] = $destination;
		}
		
		uksort($this->map, function($one, $two)
		{
			return strlen($two) - strlen($one);
		});
	}
	
	// ------------------------------------------------------------------------
	
	public function __invoke($env)
	{
		// Remove double slashes from PATH_INFO
		$env['PATH_INFO'] = preg_replace('#(?<=/)/+#', '', $env['PATH_INFO']);
		
		foreach($this->map as $pattern => $app)
		{
			if(stripos($env['PATH_INFO'], $pattern) !== 0)
			{
				continue;
			}
			
			$path = substr($env['PATH_INFO'], strlen($pattern));
			
			if(empty($path) OR $path[0] == '/')
			{
				// Modify SCRIPT_NAME and PATH_INFO so that the relevant part of the
				// URI is in PATH_INFO, This should be taken into consideration when
				// generating URLs
				$env['urlmap.orig.SCRIPT_NAME'] = $env['SCRIPT_NAME'];
				$env['SCRIPT_NAME'] .= rtrim($pattern, '/');
				$env['PATH_INFO']    = empty($path) ? '/' : $path;

				return call_user_func($app, $env);
			}
		}
		
		return array(404, array(), '');
	}
}


/* End of file UrlMapEndpoint.php */
/* Location: src/php/InjectStack */