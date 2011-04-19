<?php
/*
 * Created by Martin Wernståhl on 2011-03-06.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace InjectStack;

/**
 * A stack which creates a chain for dealing with requests, middleware can modify the
 * request or response, return a response or do other actions before the request reaches the endpoint.
 * 
 * A middleware is a component which performs actions before or after the request
 * is passed on to the following component in the chain. A middleware can also
 * return a response directly instead of calling the next component, this can
 * for example be used by a validation component.
 */
class InjectStack
{
	/**
	 * Version constant for InjectStack, compatible with version_compare().
	 * 
	 * @var string
	 */
	const VERSION = '0.1.0';
	
	/**
	 * The endpoint for this stack.
	 * 
	 * @var Callback
	 */
	protected $endpoint;
	
	/**
	 * The middleware to call before calling the endpoint.
	 * 
	 * @var array(\InjectStack\MiddlewareInterface)
	 */
	protected $middleware = array();
	
	// ------------------------------------------------------------------------

	/**
	 * Creates a new InjectStack with the supplied middleware and endpoint.
	 * 
	 * @param  array(\InjectStack\MiddlewareInterface)
	 * @param  callback
	 */
	public function __construct(array $middleware = array(), $endpoint = null)
	{
		$this->middleware = $middleware;
		$this->endpoint   = $endpoint;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Sets the endpoint for this InjectStack.
	 * 
	 * @param  callback
	 * @return void
	 */
	public function setEndpoint($endpoint)
	{
		$this->endpoint = $endpoint;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Adds a middleware at the end of the stack.
	 * 
	 * @param  MiddlewareInterface
	 * @return void
	 */
	public function addMiddleware(MiddlewareInterface $middleware)
	{
		$this->middleware[] = $middleware;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Creates the middleware chain and then calls the first middleware with the
	 * supplied parameters.
	 * 
	 * @param  mixed
	 * @return mixed
	 */
	public function run($env)
	{
		if(empty($this->endpoint))
		{
			throw new NoEndpointException();
		}
		
		$callback = array_reduce(array_reverse($this->middleware), function($callback, $middleware)
		{
			$middleware->setNext($callback);
			
			return $middleware;
		}, $this->endpoint);
		
		return $callback($env);
	}
}


/* End of file InjectStack.php */
/* Location: src/php/InjectStack */