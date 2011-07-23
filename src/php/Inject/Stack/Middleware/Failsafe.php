<?php
/*
 * Created by Martin Wernståhl on 2011-04-29.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace InjectStack\Middleware;

use \InjectStack\MiddlewareInterface;

/**
 * Converts any thrown exceptions into a 500 status which is passed to parent
 * middleware.
 */
class Failsafe implements MiddlewareInterface
{
	/**
	 * The callback for the next middleware or the endpoint in this middleware
	 * stack.
	 * 
	 * @var \InjectStack\MiddlewareInterface|Closure|ObjectImplementing__invoke
	 */
	protected $next;
	
	// ------------------------------------------------------------------------
	
	/**
	 * Tells this middleware which middleware or endpoint it should call if it
	 * wants the call-chain to proceed.
	 * 
	 * @param  \InjectStack\MiddlewareInterface|Closure|ObjectImplementing__invoke
	 */
	public function setNext($next)
	{
		$this->next = $next;
	}
	
	// ------------------------------------------------------------------------
	
	/**
	 * Performs the operations of the middleware.
	 * 
	 * @param  array
	 * @return array(int, array(string => string), string)
	 */
	public function __invoke($env)
	{
		try
		{
			$callback = $this->next;
			return $callback($env);
		}
		catch(\Exception $e)
		{
			return array(500, array(), '');
		}
	}
}


/* End of file Failsafe.php */
/* Location: src/php/InjectStack/Middleware */