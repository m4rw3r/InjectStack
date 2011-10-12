<?php
/*
 * Created by Martin Wernståhl on 2011-04-30.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace Inject\Stack\Middleware\Session;

/**
 * 
 */
class Mongrel2CookieIdHandler implements IdHandlerInterface
{
	/**
	 * List of cookie options to include in the Set-Cookie header.
	 * 
	 * @var array(mixed)
	 */
	protected $cookie_options = array();
	
	/**
	 * The name of the cookie to store the id in.
	 * 
	 * @var string
	 */
	protected $cookie_name = 'InjectFw';
	
	/**
	 * @param  string   The name of the read and stored cookie
	 * @param  int      The unix timestamp when the cookie will expire, 0 = session end
	 * @param  string   The path for which the cookie will be valid
	 * @param  string   The domain for which the cookie will be valid
	 * @param  boolean  If the cookie is only allowed to be sent across SSL connections
	 * @param  boolean  If the cookie is only allowed to be sent using HTTP requests
	 */
	public function __construct($cookie_name = 'InjectFw', $expires = 0, $path = null, $domain = null, $secure_only = false, $http_only = false)
	{
		$this->cookie_name = $cookie_name;
		
		empty($expires)     OR $this->cookie_options['Expires'] = date(DATE_RFC822, $expires);
		empty($domain)      OR $this->cookie_options['Domain']  = $domain;
		empty($path)        OR $this->cookie_options['Path']    = $path;
		empty($secure_only) OR $this->cookie_options[]          = 'Secure';
		empty($http_only)   OR $this->cookie_options[]          = 'HttpOnly';
	}
	
	/**
	 * Fetches the user id from the client, if there is no user id, or
	 * if the id is invalid for the user supplying it, returns false.
	 * 
	 * @param  array(string => mixed)
	 * @return string|false
	 */
	public function fetchUserId(array $env)
	{
		if( ! empty($env['HTTP_COOKIE']) &&
			strpos($env['HTTP_COOKIE'], $this->cookie_name) !== false)
		{
			foreach(explode(';', $env['HTTP_COOKIE']) as $part)
			{
				$pair = explode('=', $part);
				
				if(count($pair) == 2 && $pair[0] === $this->cookie_name)
				{
					return $pair[1];
				}
			}
		}
		
		return false;
	}
	
	/**
	 * Stores the user id on the client.
	 * 
	 * @param  string
	 * @param  array(int, array(string => string), string)
	 * @return array(int, array(string => string), string)
	 */
	public function storeUserId($id, array $ret)
	{
		$str = array();
		
		foreach(array_merge(array($this->cookie_name => $id), $this->cookie_options) as $k => $v)
		{
			$str[] = is_numeric($k) ? $v : $k.'='.$v;
		}
		
		$ret[1]['Set-Cookie'] = implode('; ', $str);
		
		return $ret;
	}
}


/* End of file IdHandlerInterface.php */
/* Location: src/php/Inject/Stack/Middleware/Session */