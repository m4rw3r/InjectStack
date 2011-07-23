<?php
/*
 * Created by Martin Wernståhl on 2011-04-01.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace Inject\Stack;

use ArrayObject;

/**
 * Object wrapping the $env var to simplify interaction and to also
 * provide tools for interacting with the request.
 */
class Request extends ArrayObject
{
	/**
	 * Cache of the parsed HTTP_ACCEPT header.
	 * 
	 * @var array(string => float)
	 */
	protected $accept_cache = array();
	
	/**
	 * Cache for the base application URL = (scheme + host + port + SCRIPT_NAME).
	 * 
	 * @var string
	 */
	protected $base_url_cache = false;
	
	/**
	 * Cache for host with port.
	 * 
	 * @var string
	 */
	protected $hostwport_cache = false;
	
	// ------------------------------------------------------------------------

	/**
	 * Returns the path supplied to the application (PATH_INFO + QUERY_STRING).
	 * 
	 * @return string
	 */
	public function getPath()
	{
		return $this['PATH_INFO'].(empty($this['QUERY_STRING']) ? '' : '?'.$this['QUERY_STRING']);
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns the full base URL (scheme + host + port + SCRIPT_NAME).
	 * 
	 * @return string
	 */
	public function getBaseUrl()
	{
		if( ! $this->base_url_cache)
		{
			$parts = explode(':', $this->getHostWithPort());
			
			$str  = $this['inject.url_scheme'].'://'.$parts[0];
			$port = empty($parts[1]) ? $this['SERVER_PORT'] : $parts[1];
			
			if($port == 80 && $this['inject.url_scheme'] == 'http' OR
				$port == 443 && $this['inject.url_scheme'] == 'https')
			{
				$this->base_url_cache = $str.$this['SCRIPT_NAME'];
			}
			else
			{
				$this->base_url_cache = $str.':'.$port.$this['SCRIPT_NAME'];
			}
		}
		
		return $this->base_url_cache;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns the reconstructed request URL.
	 *
	 * @return string
	 */
	public function getRequestUrl()
	{
		return $this->getBaseUrl().$this->getPath();
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns the server host-name + port, if the port is not the default port for
	 * the protocol specified in inject.url_scheme.
	 * 
	 * @param  boolean  If to always append a port
	 * @return string
	 */
	public function getHostWithPort()
	{
		if( ! $this->hostwport_cache)
		{
			if( ! empty($this['HTTP_X_FORWARDED_HOST']))
			{
				$this->hostwport_cache = trim(end(explode(',', $this['HTTP_X_FORWARDED_HOST'])));
			}
			elseif( ! empty($this['HTTP_HOST']))
			{
				$this->hostwport_cache = $this['HTTP_HOST'];
			}
			else
			{
				$host = empty($this['SERVER_NAME']) ? $this['SERVER_ADDR'] : $this['SERVER_NAME'];
				
				$this->hostwport_cache = $host.':'.$this['SERVER_PORT'];
			}
		}
		
		return $this->hostwport_cache;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns the host name.
	 * 
	 * @return string
	 */
	public function getHost()
	{
		return reset(explode(':', $this->getHostWithPort()));
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns the server port used for the request.
	 *
	 * @return int
	 */
	public function getPort()
	{
		$host = $this->getHostWithPort();
		
		if(strpos(':', $host))
		{
			return (int)end(explode(':', $host));
		}
		else
		{
			return $this['SERVER_PORT'];
		}
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns the client ip or the ip of the proxy used, if $allow_proxied_ip is
	 * used, the ip supplied by the proxy is returned if a proxy is used.
	 * 
	 * @return string
	 */
	public function getClientIp($allow_proxied_ip = false)
	{
		if($allow_proxied_ip)
		{
			if( ! empty($this['HTTP_CLIENT_IP']))
			{
				return $this['HTTP_CLIENT_IP'];
			}
			elseif( ! empty($this['HTTP_X_FORWARDED_FOR']))
			{
				return $this['HTTP_X_FORWARDED_FOR'];
			}
		}
		
		return $this['REMOTE_ADDR'];
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns true if this request is an XmlHttpRequest (X-Requested-With: XmlHttpRequest).
	 * 
	 * @return boolean
	 */
	public function isXhr()
	{
		return empty($this['HTTP_X_REQUESTED_WITH']) ? strtolower($this['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' : false;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Negotiates a Mime type with the client's Accept header, defaults to the
	 * mime type specified first in the $formats parameter.
	 * 
	 * @param  array|string  A list containing allowed mime-types, ordered by
	 *                       preference
	 * @return string
	 */
	public function negotiateMime($formats)
	{
		$formats = (Array) $formats;
		
		$accepts = $this->getAccepts();
		
		if(in_array('*/*', $accepts))
		{
			return reset($formats);
		}
		
		$matches = array_intersect($formats, $accepts);
		
		if( ! empty($matches))
		{
			return reset($matches);
		}
		else
		{
			return reset($formats);
		}
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns the list of formats this request accepts, the array is sorted
	 * with the most preferred first.
	 * 
	 * @return array(string)
	 */
	public function getAccepts()
	{
		if(empty($this->accept_cache))
		{
			if(empty($this['HTTP_ACCEPT']))
			{
				$this->accept_cache = array('text/html');
			}
			else
			{
				$this->accept_cache = array_keys($this->parseAccept($this['HTTP_ACCEPT']));
			}
		}
		
		return $this->accept_cache;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Parses the HTTP_ACCEPT* header(s) and sorts the formats by priority.
	 * 
	 * Example return format:
	 * <code>
	 * array
	 *   'application/xml' => float 1
	 *   'application/xhtml+xml' => float 1
	 *   'image/png' => float 1
	 *   'text/html' => float 0.9
	 *   'text/plain' => float 0.8
	 *   '* /*' => float 0.5  // Escaped end of comment by adding a space
	 * </code>
	 *
	 * @param  The supplied header contents
	 * @return array(string => float)
	 */
	protected function parseAccept($header)
	{
		$formats = array();
		$types   = array_map('trim', explode(',', $header));
		
		foreach(array_filter($types) as $t)
		{
			preg_match('/^([^\s;]+)\s*(?:;\s*q=([\d\.]+))?/', $t, $matches);
			
			$type_name = $matches[1];
			$quality   = empty($matches[2]) ? 1.0 : $matches[2];
			
			$formats[$type_name] = (double)$quality;
		}
		
		arsort($formats);
		
		return $formats;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns the default URL construction options.
	 * 
	 * @return array(string => string)
	 */
	public function getDefaultUrlOptions()
	{
		return array(
			'protocol'    => $this['inject.url_scheme'],
			'host'        => $this->getHostWithPort(),
			'script_name' => $this['SCRIPT_NAME']
			);
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Constructs a URL from a set of options.
	 * 
	 * Options:
	 * 'protocol':    Protocol to use, usually 'http' or 'https'
	 * 'host':        Hostname, includes port
	 * 'script_name': Front controller
	 * 'path':        Path info, if it starts with '/', script_name won't be prepended
	 * 'only_path':   If to only generate the path info and forward
	 * 'params':      GET parameters
	 * 'anchor':      Anchor
	 * 
	 * @param  array(string => string)
	 * @return string
	 */
	public static function urlFor(array $options)
	{
		// TODO: Rewrite? Replace?
		
		if( ! (isset($options['host']) OR isset($options['only_path']) && $options['only_path']))
		{
			// TODO: Exception
			throw new \Exception('No host to link to, please set $default_url_options[\'host\'], $options[\'host\'] or $options[\'only_path\'].');
		}
		
		if(preg_match('#^[A-Za-z]+://#u', $options['path']))
		{
			return $options['path'];
		}
		
		$rewritten_url = '';
		
		if( ! (isset($options['only_path']) && $options['only_path']))
		{
			$rewritten_url .= isset($options['protocol']) ? $options['protocol'] : 'http';
			// TODO: Add authentication?
			$rewritten_url = trim($rewritten_url, '://').'://'.$options['host'];
		}
		
		if(strpos($options['path'], '/') === 0)
		{
			$rewritten_url .= $options['path'];
		}
		else
		{
			$rewritten_url .= (isset($options['script_name']) ? $options['script_name'] : '').'/'.$options['path'];
		}
		
		// GET parameters
		$rewritten_url .= empty($options['params']) ? '' : '?'.http_build_query($options['params']);
		
		if(isset($options['anchor']))
		{
			$rewritten_url .= '#'.urlencode($options['anchor']);
		}
		
		return $rewritten_url;
	}
}


/* End of file Request.php */
/* Location: src/php/Inject/Stack */