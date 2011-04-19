<?php
/*
 * Created by Martin Wernståhl on 2011-03-06.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace InjectStack;

/**
 * Endpoint which attempts to call several apps and returns the response of the
 * first one to return a response without the X-Cascade header set to pass.
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
		$ret = array(404, array('X-Cascade' => 'pass'), '');
		
		foreach($this->apps as $app)
		{
			$ret = call_user_func($app, $env);
			
			if( ! isset($ret[1]['X-Cascade']) OR $ret[1]['X-Cascade'] != 'pass')
			{
				return $ret;
			}
		}
		
		return $ret;
	}
}


/* End of file CascadeEndpoint.php */
/* Location: src/php/InjectStack */