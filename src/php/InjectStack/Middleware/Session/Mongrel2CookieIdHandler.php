<?php
/*
 * Created by Martin Wernståhl on 2011-04-30.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace InjectStack\Middleware\Session;

/**
 * 
 */
class Mongrel2CookieIdHandler implements IdHandlerInterface
{
	protected $cookie_name = 'InjectFw';
	
	/**
	 * Fetches the user id from the client, if there is no user id, or
	 * if the id is invalid for the user supplying it, generate a new one.
	 * 
	 * @param  array(string => mixed)
	 * @return string|false
	 */
	public function fetchUserId(array $env)
	{
		if( ! $renew && ! empty($env['HTTP_COOKIE']) &&
			strpos($env['HTTP_COOKIE'], $this->cookie_name) !== false)
		{
			// TODO: Replace with proper code, do not presume that all cookes reside on the domain
			parse_str($env['HTTP_COOKIE'], $cookie);
			
			if( ! empty($cookie[$this->cookie_name]))
			{
				return $cookie[$this->cookie_name];
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
		$ret[1]['Set-Cookie'] = http_build_query(array($this->cookie_name => $id));
		
		return $ret;
	}
}


/* End of file IdHandlerInterface.php */
/* Location: src/php/InjectStack/Middleware/Session */