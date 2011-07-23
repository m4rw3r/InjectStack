<?php
/*
 * Created by Martin Wernståhl on 2011-04-30.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace Inject\Stack\Middleware\Session;

/**
 * 
 * 
 * Default setcookie() options:
 *  * expire:   0   When the web-browser exits
 *  * path:     ''  
 *  * domain    ''
 *  * secure    false
 *  * httponly  false
 * 
 */
class CookieIdHandler implements IdHandlerInterface
{
	/**
	 * The name of the cookie.
	 * 
	 * @var string
	 */
	protected $cookie_name = 'InjectFw';
	
	/**
	 * The options for the cookie.
	 * 
	 * @param array(string => mixed)
	 */
	protected $options = array();
	
	// ------------------------------------------------------------------------
	
	/**
	 * @param  string  The cookie name
	 * @param  array   array with parameters to setcookie(), named as in
	 *                 the documentation
	 */
	public function __construct($cookie_name = 'InjectFw', $options = array())
	{
		$this->cookie_name = $cookie_name;
		$this->options     = array_merge(array(
			'expire'   => 0,
			'path'     => '',
			'domain'   => '',
			'secure'   => false,
			'httponly' => false
		), $options);
	}
	
	// ------------------------------------------------------------------------
	
	/**
	 * Fetches the user id from the client, if there is no user id, or
	 * if the id is invalid for the user supplying it, return false.
	 * 
	 * @param  array(string => mixed)
	 * @return string|false
	 */
	public function fetchUserId(array $env)
	{
		if( ! empty($_COOKIE[$this->cookie_name]))
		{
			return $_COOKIE[$this->cookie_name];
		}
		
		return false;
	}
	
	// ------------------------------------------------------------------------
	
	/**
	 * Stores the user id on the client.
	 * 
	 * @param  string|false  If false, remove the user id from the client
	 * @param  array(int, array(string => string), string)
	 * @return array(int, array(string => string), string)
	 */
	public function storeUserId($id, array $ret)
	{
		if( ! $id)
		{
			// Delete the cookie, expires 1 Unix time
			setcookie($this->cookie_name, 0, 1,
				$this->options['path'],
				$this->options['domain'],
				$this->options['secure'],
				$this->options['httponly']);
		}
		else
		{
			setcookie($this->cookie_name, $id, $this->options['expire'],
				$this->options['path'],
				$this->options['domain'],
				$this->options['secure'],
				$this->options['httponly']);
		}
		
		return $ret;
	}
}


/* End of file CookieIdHandler.php */
/* Location: src/php/Inject/Stack/Middleware/Session */