<?php
/*
 * Created by Martin Wernståhl on 2011-03-06.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace InjectStack\Middleware;

/**
 * Filters $env and strips all non-UTF-8 characters from its strings, prevents
 * injection attacks and similar by confusing the escaping functions with bad UTF-8.
 */
class Utf8Filter implements MiddlewareInterface
{
	/**
	 * The callback for the next middleware or the endpoint.
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
	 * Filters the whole $env variable for invalid UTF-8 characters before passing
	 * it on to the next middleware/endpoint.
	 * 
	 * @param  array
	 * @return array(int, array(string => string), string)
	 */
	public function __invoke($env)
	{
		$env = $this->cleanUtf8($env);
		
		$callback = $this->next;
		return $callback($env);
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Cleans the value's strings from invalid Utf-8 characters, provided they
	 * don't reside in an object.
	 * 
	 * @param  mixed
	 * @return mixed
	 */
	public function cleanUtf8($value)
	{
		if(is_array($value))
		{
			// Create new array, to prevent keeping of old non-utf-8 keys
			$arr = array();
			
			foreach($value as $k => $v)
			{
				// Inlined utf8compliant($k) for speed
				if( ! preg_match('/^.{1}/us', $k))
				{
					$k = iconv('UTF-8', 'UTF-8//IGNORE', $k);
				}
				
				// Inlined parts of cleanUtf8($v) for speed
				if(is_string($v))
				{
					if( ! preg_match('/^.{1}/us', $v))
					{
						$v = iconv('UTF-8', 'UTF-8//IGNORE', $v);
					}
				}
				elseif(is_array($v))
				{
					// Last resort, have to recurse
					$v = $this->cleanUtf8($v);
				}
				
				$arr[$k] = $v;
			}
			
			return $arr;
		}
		elseif(is_string($value))
		{
			// Inline of utf8compliant($value)
			if( ! preg_match('/^.{1}/us', $value))
			{
				$value = iconv('UTF-8', 'UTF-8//IGNORE', $value);
			}
		}
		
		return $value;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns true if the supplied string is UTF8 compliant.
	 * 
	 * @param  string
	 * @return boolean
	 */
	public static function utf8compliant($str)
	{
		if(strlen($str) == 0)
		{
			return true;
		}
		
		// If even just the first character can be matched, when the /u
		// modifier is used, then it's valid UTF-8. If the UTF-8 is somehow
		// invalid, nothing at all will match, even if the string contains
		// some valid sequences
		return preg_match('/^.{1}/us', $str) == 1;
	}
}


/* End of file Utf8Filter.php */
/* Location: src/php/InjectStack/Middleware */