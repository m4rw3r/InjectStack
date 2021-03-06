<?php
/*
 * Created by Martin Wernståhl on 2011-03-06.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace Inject\Stack;

use \Inject\Stack\Builder\NoEndpointException;

/**
 * An object which creates a stack of middleware for dealing with requests, middleware can modify the
 * request or response, return a response or do other actions before the request reaches the endpoint.
 * 
 * A middleware is a component which performs actions before or after the request
 * is passed on to the following component in the chain. A middleware can also
 * return a response directly instead of calling the next component, this can
 * for example be used by a validation component.
 */
class Builder
{
	/**
	 * Version constant for Inject\Stack, compatible with version_compare().
	 * 
	 * @var string
	 */
	const VERSION = '0.1.0';
	
	/**
	 * The endpoint for this stack.
	 * 
	 * @var Closure|ObjectImplementing__invoke
	 */
	protected $endpoint;
	
	/**
	 * The middleware to call before calling the endpoint.
	 * 
	 * @var array(\Inject\Stack\MiddlewareInterface)
	 */
	protected $middleware = array();
	
	// ------------------------------------------------------------------------

	/**
	 * Creates a new Builder with the supplied middleware and endpoint.
	 * 
	 * @param  array(\Inject\Stack\MiddlewareInterface)
	 * @param  Closure|ObjectImplementing__invoke
	 */
	public function __construct(array $middleware = array(), $endpoint = null)
	{
		$this->middleware = $middleware;
		$this->endpoint   = $endpoint;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Sets the endpoint for this Builder.
	 * 
	 * @param  Closure|ObjectImplementing__invoke
	 * @return void
	 */
	public function setEndpoint($endpoint)
	{
		$this->endpoint = $endpoint;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Adds a middleware at the start of the stack.
	 * 
	 * @param  MiddlewareInterface
	 * @return void
	 */
	public function prependMiddleware(MiddlewareInterface $middleware)
	{
		array_unshift($this->middleware, $middleware);
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
	public function __invoke($env)
	{
		$callback = $this->build();
		
		return $callback($env);
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Builds the middleware chain and returns it.
	 * 
	 * @return Closure|MiddlewareInterface|ObjectImplementing__invoke
	 */
	public function build()
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
		
		return $callback;
	}
}


/* End of file Builder.php */
/* Location: src/php/Inject/Stack */