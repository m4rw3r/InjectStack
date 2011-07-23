<?php
/*
 * Created by Martin Wernståhl on 2011-04-11.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace InjectStack;

/**
 * 
 */
class Util
{
	/**
	 * List of valid HTTP/1.1 request methods.
	 * 
	 * @var array(string)
	 */
	static protected $request_methods = array(
			'CONNECT',
			'DELETE',
			'GET',
			'HEAD',
			'OPTIONS',
			'POST',
			'PUT',
			'TRACE'
		);
	
	/**
	 * The HTTP status codes and their corresponding textual representation.
	 * 
	 * @var array(string => string)
	 */
	static public $status_texts = array(
		'100' => 'Continue',
		'101' => 'Switching Protocols',
		'200' => 'OK',
		'201' => 'Created',
		'202' => 'Accepted',
		'203' => 'Non-Authoritative Information',
		'204' => 'No Content',
		'205' => 'Reset Content',
		'206' => 'Partial Content',
		'300' => 'Multiple Choices',
		'301' => 'Moved Permanently',
		'302' => 'Found',
		'303' => 'See Other',
		'304' => 'Not Modified',
		'305' => 'Use Proxy',
		'307' => 'Temporary Redirect',
		'400' => 'Bad Request',
		'401' => 'Unauthorized',
		'402' => 'Payment Required',
		'403' => 'Forbidden',
		'404' => 'Not Found',
		'405' => 'Method Not Allowed',
		'406' => 'Not Acceptable',
		'407' => 'Proxy Authentication Required',
		'408' => 'Request Timeout',
		'409' => 'Conflict',
		'410' => 'Gone',
		'411' => 'Length Required',
		'412' => 'Precondition Failed',
		'413' => 'Request Entity Too Large',
		'414' => 'Request-URI Too Long',
		'415' => 'Unsupported Media Type',
		'416' => 'Requested Range Not Satisfiable',
		'417' => 'Expectation Failed',
		'418' => 'I\'m a teapot',
		'444' => 'No Response',   // Nginx, closes connection without responding
		'500' => 'Internal Server Error',
		'501' => 'Not Implemented',
		'502' => 'Bad Gateway',
		'503' => 'Service Unavailable',
		'504' => 'Gateway Timeout',
		'505' => 'HTTP Version Not Supported',
		);
	
	// ------------------------------------------------------------------------

	/**
	 * Returns the text representation of the supplied response code.
	 * 
	 * @param  int
	 * @return string
	 */
	public static function getHttpStatusText($response_code)
	{
		return self::$status_texts[$response_code];
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Validates the HTTP request method, so it contains a valid method, throws
	 * exception if it is not valid.
	 * 
	 * @param  string
	 * @param  boolean Set to true if this is from a post override, then the
	 *                 exception will reflect that if it is thrown
	 * @return string
	 */
	public static function checkRequestMethod($request_method, $post_override = false)
	{
		$request_method = strtoupper($request_method);
		
		if( ! in_array($request_method, self::$request_methods))
		{
			$allowed_methods = implode(', ', array_slice(self::$request_methods, 0, -1)).(($m = end(self::$request_methods)) ? ' and '.$m : '');
			
			if($post_override)
			{
				// TODO: Exception
				throw new \Exception(sprintf('Unknown HTTP request method %s specified by "_method" in POST data, accepted HTTP methods are: %s.', $request_method, $allowed_methods));
			}
			else
			{
				// TODO: Exception
				throw new \Exception(sprintf('Unknown HTTP request method %s, accepted HTTP methods are: %s.', $request_method, $allowed_methods));
			}
		}
		
		return $request_method;
	}
}


/* End of file Util.php */
/* Location: src/php/InjectStack */