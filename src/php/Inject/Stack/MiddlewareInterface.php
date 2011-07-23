<?php
/*
 * Created by Martin Wernståhl on 2011-03-06.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace InjectStack;

/**
 * Interface for Middlewares, these can do pre or post processing etc. in
 * a chain before the endpoint which usually is a controller action.
 */
interface MiddlewareInterface
{
	/**
	 * Sets the middleware or endpoint to call.
	 * 
	 * @param  Closure|ObjectImplementing__invoke
	 */
	public function setNext($callback);
	
	/**
	 * Performs the middleware actions, and forwards the call to the value
	 * set by setNext() if appropriate.
	 * 
	 * @param  array
	 * @return array
	 */
	public function __invoke($env);
}


/* End of file MiddlewareInterface.php */
/* Location: src/php/InjectStack */