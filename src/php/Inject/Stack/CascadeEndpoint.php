<?php
/*
 * Created by Martin Wernståhl on 2011-03-06.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace InjectStack;

/**
 * Endpoint which attempts to call several apps and returns the response of the
 * first one to return a response which does not have a 404 status code.
 */
class CascadeEndpoint
{
	/**
	 * List of apps to attempt.
	 * 
	 * @var array(callback)
	 */
	protected $apps = array();
	
	// ------------------------------------------------------------------------

	/**
	 * @param  array(callback)
	 */
	public function __construct(array $apps = array())
	{
		$this->apps = $apps;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Adds an additional application to attempt.
	 * 
	 * @param  callback
	 * @return void
	 */
	public function add($callback)
	{
		$this->apps[] = $callback;
	}
	
	// ------------------------------------------------------------------------
	
	public function __invoke($env)
	{
		$ret = array(404, array(), '');
		
		foreach($this->apps as $app)
		{
			$ret = call_user_func($app, $env);
			
			// Check 404 status
			if($ret[0] != 404)
			{
				return $ret;
			}
		}
		
		return $ret;
	}
}


/* End of file CascadeEndpoint.php */
/* Location: src/php/InjectStack */