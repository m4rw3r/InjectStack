<?php
/*
 * Created by Martin Wernståhl on 2011-04-30.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace InjectStack\Middleware\Session;

use \Memcached;

/**
 * Memcached based session storage adapter, uses optimistic locking on a
 * per-session-id basis, throws exception if the write failed.
 * 
 * Requirements:
 *  * Memcached PECL extension
 *  * libmemcached
 * 
 * Usage:
 * <code>
 * $memcached = new \Memcached();
 * $memcached->addServer('127.0.0.1', 11211);  // localhost and default port
 * 
 * $storage = new \InjectStack\Middleware\Session\MemcachedStorage($memcached);
 * $idhandl = new \InjectStack\Middleware\Session\CookieIdHandler();
 * 
 * $session = new \InjectStack\Middleware\Session($storage, $idhandl);
 * </code>
 */
class MemcachedStorage implements StorageInterface
{
	/**
	 * Prefix for used Memcached keys.
	 * 
	 * @var string
	 */
	protected $prefix = 'InjectFw_Session_';
	
	/**
	 * Maximum session age in seconds, may not be larger than 2592000 (30 days).
	 * 
	 * @var int
	 */
	protected $max_age = 3600;
	
	/**
	 * The memcached connection instance.
	 * 
	 * @var \Memcached
	 */
	protected $memcached;
	
	// ------------------------------------------------------------------------

	/**
	 * @param  Memcached  The memcached connection to use
	 * @param  int        Maximum session age in seconds, may not be larger
	 *                    than 2592000 (30 days).
	 * @param  string     The prefix for the used memcached keys
	 */
	public function __construct(Memcached $connection, $max_age = 3600, $key_prefix = 'InjectFw_Session_')
	{
		$this->memcached = $connection;
		$this->max_age   = $max_age;
		$this->prefix    = $key_prefix;
	}
	
	// ------------------------------------------------------------------------
	
	/**
	 * Loads an existing session, or creates a new one if none exists for
	 * the supplied id, and returns a populated Bucket instance.
	 * 
	 * @param  array(string => mixed)  The $env var
	 * @param  scalar                  The session id
	 * @return array(string => mixed)
	 */
	public function loadSession(array $env, $id)
	{
		if(false === ($result = $this->memcached->get($this->prefix.$id, null, $token)))
		{
			return false;
		}
		
		// Add the Memcached CAS token, so we can make "atomic" writes
		return array_merge($result, array('__memcached_cas_token' => $token));
	}
	
	// ------------------------------------------------------------------------
	
	/**
	 * Saves the supplied bucket instance.
	 * 
	 * @param  Bucket
	 * @return void
	 */
	public function saveSession(Bucket $data)
	{
		$array  = $data->getArrayCopy();
		
		if($data->isNew())
		{
			// New data, no token to validate against, just add the key
			if( ! $this->memcached->add($this->prefix.$data->getId(), $array, $this->max_age))
			{
				// TODO: Proper exception
				throw new \Exception('Could not save session in Memcached: '.$this->memcached->getResultMessage(), $this->memcached->getResultCode());
			}
		}
		else
		{
			// Get CAS token, to make sure we won't overwrite another write
			$token = $array['__memcached_cas_token'];
			unset($array['__memcached_cas_token']);
			
			if( ! $this->memcached->cas($token, $this->prefix.$data->getId(), $array, $this->max_age))
			{
				// TODO: Proper exception
				throw new \Exception('Could not save session in Memcached: '.$this->memcached->getResultMessage(), $this->memcached->getResultCode());
			}
		}
	}
	
	// ------------------------------------------------------------------------
	
	/**
	 * Deletes session information related to the supplied id.
	 * 
	 * @param  scalar
	 * @return void
	 */
	public function destroySession($id)
	{
		$this->memcached->delete($this->prefix.$id);
	}
	
	// ------------------------------------------------------------------------
	
	public function garbageCollect()
	{
		// Intentionally blank, memcached expires stuff by itself
	}
}


/* End of file SessionInterface.php */
/* Location: src/php/InjectStack/Middleware/Session */