<?php
/*
 * Created by Martin Wernståhl on 2011-03-06.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace InjectStack\Middleware;

use \ArrayAccess;
use \Countable;
use \Iterator;
use \IteratorAggregate;

use \InjectStack\MiddlewareInterface;

/**
 * Middleware performing checks on $env and return value to see that they conform
 * to the framework specifications.
 * 
 * WARNING:
 * ========
 * 
 * DO NOT USE IN A PRODUCTION ENVIRONMENT!!
 * IT WILL SLOW YOUR APPLICATION GREATLY!!
 * 
 * 
 * Useful tool to use when testing new middleware, add a Lint middleware before
 * and after your middleware to see that it conforms to the standard.
 */
class Lint implements MiddlewareInterface
{
	/**
	 * The callback for the next middleware or the endpoint in this middleware
	 * stack.
	 * 
	 * @var \InjectStack\MiddlewareInterface|Closure|ObjectImplementing__invoke
	 */
	protected $next;
	
	/**
	 * If it is a HEAD request.
	 * 
	 * @var boolean
	 */
	protected $head_request = false;
	
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
	 * Performs a lint check on the input and output
	 * 
	 * @param  array
	 * @return array(int, array(string => string), string)
	 */
	public function __invoke($env)
	{
		$this->checkEnv($env);
		
		$this->head_request = strtoupper($env['REQUEST_METHOD']) == 'HEAD';
		
		$callback = $this->next;
		$ret      = $callback($env);
		
		$this->checkRet($ret);
		
		return $ret;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Checks the $env variable.
	 * 
	 * @param  mixed
	 * @return void
	 */
	public function checkEnv(&$env)
	{
		$this->assert(sprintf('$env is not array equivalent (iterable, ArrayAccess, Countable), but %s',
			gettype($env) == 'object' ? get_class($env) : gettype($env)),
			$this->isArrayEquivalent($env));
		
		
		// TODO: Check standardized components like session and logging
		
		$string_keys = array(
				'PATH_INFO',
				'QUERY_STRING',
				'REQUEST_METHOD',
				'BASE_URI',
				'SCRIPT_NAME',
				'SERVER_NAME',
				'SERVER_PORT',
				'inject.version',
				'inject.url_scheme',
				'inject.adapter',
				'inject.get',
				'inject.post',
				'inject.input'
		);
		
		foreach($string_keys as $str_key)
		{
			$this->assert(sprintf('$env is missing required key %s', $str_key),
				isset($env[$str_key]));
		}
		
		foreach(array('HTTP_CONTENT_TYPE', 'HTTP_CONTENT_LENGTH') as $header)
		{
			$this->assert(sprintf('$env key %s exists, must use %s instead', $header, substr($header, 5)),
				! isset($env[$header]));
		}
		
		// Keys without dots must be string (CGI keys)
		foreach($env as $key => $val)
		{
			if(strpos($key, '.') !== false)
			{
				continue;
			}
			
			$this->assert(sprintf('$env[\'%s\'] is not a string', $key), $this->isStringEquivalent($val));
		}
		
		$this->assert(sprintf('inject.url_scheme unknown: %s',  empty($env['inject.url_scheme']) ? 'EMPTY' : $env['inject.url_scheme']),
			isset($env['inject.url_scheme']) && in_array($env['inject.url_scheme'], array('http', 'https')));
		
		$this->assert('inject.adapter must be present and string',
			isset($env['inject.adapter']) && gettype($env['inject.adapter']) == 'string');
		
		$this->assert('inject.get must be an array',     $this->isArrayEquivalent($env['inject.get']));
		$this->assert('inject.post must be an array',    $this->isArrayEquivalent($env['inject.post']));
		
		$this->assert(sprintf('Unknown REQUEST_METHOD %s', $env['REQUEST_METHOD']),
			preg_match('/^[0-9A-Za-z!\#$%&\'*+.^_`|~-]+$/', $env['REQUEST_METHOD']));
		
		$this->assert('SCRIPT_NAME must begin with "/" if not empty',
			empty($env['SCRIPT_NAME']) OR substr($env['SCRIPT_NAME'], 0, 1) === '/');
		
		$this->assert('PATH_INFO must start with slash if not empty',
			empty($env['PATH_INFO']) OR substr($env['PATH_INFO'], 0, 1) === '/');
		
		$this->assert('Invalid CONTENT_LENGTH', empty($env['CONTENT_LENGTH']) OR preg_match('/^\d+$/', $env['CONTENT_LENGTH']));
		
		$this->assert('SCRIPT_NAME cannot be "/", make it "" and PATH_INFO "/"',
			$env['SCRIPT_NAME'] != '/');
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Validates the return value.
	 * 
	 * @param  mixed
	 * @return void
	 */
	public function checkRet(&$ret)
	{
		$this->assert(sprintf('Return value is not an array, but %s',
			gettype($ret) == 'object' ? get_class($ret) : gettype($ret)),
			$this->isArrayEquivalent($ret));
		
		$this->assert('First return value is missing',  isset($ret[0]));
		$this->assert('Second return value is missing', isset($ret[1]));
		$this->assert('Third return value is missing',  isset($ret[2]));
		
		$this->assert('Response status must be an int', preg_match('/^\d+$/', $ret[0]));
		
		$this->assert('Response status must be >= 100', ((int)$ret[0]) >= 100);
		
		$this->assert('Second return value must be an array', $this->isArrayEquivalent($ret[1]));
		
		foreach($ret[1] as $hkey => $hval)
		{
			$this->assert('Header key must be string', ! is_numeric($hkey));
			
			$this->assert('Header must not contain Status', strtolower($hkey) != 'status');
			
			$this->assert('Header names must not contain : or \\n', ! preg_match('/[:\n]/', $hkey));
			
			$this->assert('Invalid header name', preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $hkey));
			
			$this->assert('Header value must be a scalar or an object responding to __toString', $this->isStringEquivalent($hval));
			
			foreach(explode("\n", $hval) as $line)
			{
				$this->assert(sprintf('Header %s value contains invalid characters', $hkey), ! preg_match('/[\000-\027]/', $line));
			}
		}
		
		$this->checkContentTypeHeader($ret[0], $ret[1]);
		$this->checkContentLengthHeader($ret[0], $ret[1], $ret[2]);
		
		$this->assert('Response body must be a scalar or an object responding to __toString', $this->isStringEquivalent($ret[2]));
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Checks for a content type header, or the absence of one, depending on the
	 * response code.
	 * 
	 * @param  int   The response code
	 * @param  array The header array
	 * @return void
	 */
	public function checkContentTypeHeader($response_code, $headers)
	{
		foreach($headers as $hkey => $hval)
		{
			if(strtolower($hkey) == 'content-type')
			{
				$this->assert('Content-Type header found, not allowed with header code '.$response_code,
					! $this->isCodeWithNoBody($response_code));
				
				return;
			}
		}
		
		$this->assert('Content-Type header not found', $this->isCodeWithNoBody($response_code));
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Performs checks for the Content-Length header.
	 * 
	 * @param  int
	 * @param  array(string => string)
	 * @param  string
	 * @return void
	 */
	public function checkContentLengthHeader($response_code, $headers, $body)
	{
		// TODO: Remove? As adapters automatically add it?
		$content_length = false;
		
		foreach($headers as $hkey => $hval)
		{
			if(strtolower($hkey) == 'content-length')
			{
				$this->assert('Content-Length header found, not allowed with header code '.$response_code,
					! $this->isCodeWithNoBody($response_code));
				
				$content_length = $hval;
			}
		}
		
		if($this->head_request)
		{
			$this->assert('Response was given for HEAD request, should be empty', strlen($body) == 0);
		}
		elseif($content_length !== false)
		{
			if( ! is_resource($body))
			{
				$this->assert(sprintf('Response length is %s, should be %s', $content_length , strlen($body)),
					strlen($body) == $content_length);
			}
		}
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns true if the supplied response code is a code which should not have
	 * a response body.
	 * 
	 * @param  int
	 * @return boolean
	 */
	public function isCodeWithNoBody($code)
	{
		return $code < 200 && $code >= 199 OR $code == 204 OR $code == 304;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns true if the supplied value can be casted to string without loss.
	 * 
	 * @param  mixed
	 * @return boolean
	 */
	public function isStringEquivalent($value)
	{
		return is_object($value) && method_exists($value, '__toString') OR ! in_array(gettype($value), array('array', 'object'));
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns true if the supplied value can be treated as an array (iterable,
	 * countable and has array access).
	 * 
	 * @param  mixed
	 * @return boolean
	 */
	public function isArrayEquivalent($value)
	{
		return is_array($value) OR
				$value instanceof ArrayAccess &&
				$value instanceof Countable &&
				(
					$value instanceof Iterator OR
					$value instanceof IteratorAggregate
				);
	}
	
	// ------------------------------------------------------------------------

	/**
	 * If the supplied closure fails, a LintException will be thrown with the
	 * supplied error message.
	 * 
	 * @param  string   Error message
	 * @param  Closure  Closure returning false if the test fails
	 * @return void
	 */
	public function assert($message, $call)
	{
		if( ! $call OR is_callable($call) && ! $call())
		{
			throw new LintException($message);
		}
	}
}


/* End of file RunTimer.php */
/* Location: src/php/InjectStack/Middleware */