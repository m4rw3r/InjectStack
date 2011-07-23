<?php
/*
 * Created by Martin Wernståhl on 2011-03-06.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace Inject\Stack\Middleware;

use \Inject\Stack\MiddlewareInterface;

/**
 * Times the request from the time it passes this middleware until it returns
 * a response through this middleware.
 * 
 * The result is stored in the header X-Runtime. If the RunTimer is named
 * by using the parameter to the constructor, the header will be X-Runtime-$name.
 */
class RunTimer implements MiddlewareInterface
{
	/**
	 * The timer name, empty if no name is given.
	 * 
	 * @var string
	 */
	protected $name = '';
	
	/**
	 * The callback for the next middleware or the endpoint in this middleware
	 * stack.
	 * 
	 * @var \Inject\Stack\MiddlewareInterface|Closure|ObjectImplementing__invoke
	 */
	protected $next;
	
	// ------------------------------------------------------------------------

	/**
	 * @param  string  The timer name, if any
	 */
	public function __construct($name = '')
	{
		$this->name = $name;
	}
	
	// ------------------------------------------------------------------------
	
	/**
	 * Tells this middleware which middleware or endpoint it should call if it
	 * wants the call-chain to proceed.
	 * 
	 * @param  \Inject\Stack\MiddlewareInterface|Closure|ObjectImplementing__invoke
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
		// PHP does not allow $this->next($env), so store $this->next
		$callback = $this->next;
		
		// Start the timer, invoke the next middleware or the endpoint and stop timer
		$start_time = microtime(true);
		$ret        = $callback($env);
		$end_time   = microtime(true);
		
		// Add the X-Runtime header to the response
		$ret[1]['X-Runtime'.($this->name ? '-'.$this->name : '')] = $end_time - $start_time;
		
		return $ret;
	}
}


/* End of file RunTimer.php */
/* Location: src/php/Inject/Stack/Middleware */