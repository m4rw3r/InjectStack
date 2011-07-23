<?php
/*
 * Created by Martin Wernståhl on 2011-04-30.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace Inject\Stack\Middleware;

use \Inject\Stack\MiddlewareInterface;

/**
 * Middleware which invalidates sessions created by \Inject\Stack\Middleware\Session
 * if they for example change user agent, or if they time out etc.
 * 
 * Options:
 *  * check_user_agent:        boolean, if to invalidate session if the user agent
 *                             does not match
 *  * check_ip:                boolean, if to invalidate session if the REMOTE_ADDR
 *                             env key does not match
 *  * limit_last_access_time:  int, if time in seconds since last access exceeds
 *                             this, session will be invalidated, 0 == off
 *  * limit_session_time:      int, if the time since the session was created
 *                             exceeds this, the session will be invalidated,
 *                             0 == off
 * 
 * Defaults:
 *  * check_user_agent:       true
 *  * check_ip:               true
 *  * limit_last_access_time: 600 seconds = 10 minutes
 *  * limit_session_time:     3600 seconds = 1 hour
 */
class SessionInvalidator implements MiddlewareInterface
{
	/**
	 * The callback for the next middleware or the endpoint in this middleware
	 * stack.
	 * 
	 * @var \Inject\Stack\MiddlewareInterface|Closure|ObjectImplementing__invoke
	 */
	protected $next;
	
	/**
	 * The $env key containing the session Bucket instance.
	 * 
	 * @var string
	 */
	protected $session_key;
	
	/**
	 * The options for this SessionInvalidator.
	 * 
	 * @var array(string => mixed)
	 */
	
	// ------------------------------------------------------------------------

	/**
	 * @param  string  The key in $env with the Bucket instance
	 * @param  array   An array of options
	 */
	public function __construct($session_key = 'inject.session', $options = array())
	{
		$this->sess_key   = $session_key;
		$this->options    = array_merge(array(
			'check_user_agent' => true,
			'check_ip' => true,
			'limit_last_access_time' => 600,
			'limit_session_time' => 3600
		), $options);
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
		$invalid = false;
		$bucket  = $env[$this->sess_key];
		
		if($this->options['check_user_agent'])
		{
			empty($env['HTTP_USER_AGENT']) AND $env['HTTP_USER_AGENT'] = '';
			
			if( ! empty($bucket['HTTP_USER_AGENT']))
			{
				// = has a higher operator priority than OR
				$invalid = ($invalid OR ($bucket['HTTP_USER_AGENT'] !== $env['HTTP_USER_AGENT']));
			}
			else
			{
				$bucket['HTTP_USER_AGENT'] = $env['HTTP_USER_AGENT'];
			}
		}
		
		if($this->options['check_ip'])
		{
			if( ! empty($bucket['REMOTE_ADDR']))
			{
				$invalid = ($invalid OR ($bucket['REMOTE_ADDR'] !== $env['REMOTE_ADDR']));
			}
			else
			{
				$bucket['REMOTE_ADDR'] = $env['REMOTE_ADDR'];
			}
		}
		
		if($this->options['limit_last_access_time'])
		{
			if( ! empty($bucket['last_access_time']))
			{
				$invalid = ($invalid OR ($bucket['last_access_time'] < time() - $this->options['limit_last_access_time']));
			}
			
			$bucket['last_access_time'] = time();
		}
		
		if($this->options['limit_session_time'])
		{
			if( ! empty($bucket['session_start_time']))
			{
				$invalid = ($invalid OR ($bucket['session_start_time'] < time() - $this->options['limit_session_time']));
			}
			else
			{
				$bucket['session_start_time'] = time();
			}
		}
		
		if($invalid)
		{
			// TODO: Log feature
			$bucket->invalidateSession();
		}
		
		$callback = $this->next;
		$ret      = $callback($env);
		
		return $ret;
	}
}


/* End of file SessionInvalidator.php */
/* Location: src/php/Inject/Stack/Middleware */