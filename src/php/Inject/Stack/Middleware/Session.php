<?php
/*
 * Created by Martin Wernståhl on 2011-04-30.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace Inject\Stack\Middleware;

use \Inject\Stack\MiddlewareInterface;

use \Inject\Stack\Middleware\Session\Bucket;
use \Inject\Stack\Middleware\Session\StorageInterface;
use \Inject\Stack\Middleware\Session\IdHandlerInterface;

/**
 * 
 */
class Session implements MiddlewareInterface
{
	/**
	 * The callback for the next middleware or the endpoint in this middleware
	 * stack.
	 * 
	 * @var \Inject\Stack\MiddlewareInterface|Closure|ObjectImplementing__invoke
	 */
	protected $next;
	
	/**
	 * Session storage adapter.
	 * 
	 * @var \Inject\Stack\Middleware\Session\StorageInterface
	 */
	protected $storage;
	
	/**
	 * The object fetching and storing the ID for the client.
	 * 
	 * @var \Inject\Stack\Middleware\Session\IdHandlerInterface
	 */
	protected $id_handler;
	
	// ------------------------------------------------------------------------

	/**
	 * 
	 * 
	 * @return 
	 */
	public function __construct(StorageInterface $storage, IdHandlerInterface $id_handler, array $options = array())
	{
		$this->storage    = $storage;
		$this->id_handler = $id_handler;
		$this->sess_key   = empty($options['key']) ? 'inject.session' : $options['key'];
		$this->options    = array_merge(array(
			'gc_probability' => 1,
			'gc_divisor'     => 100
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
		if(mt_rand(1, $this->options['gc_divisor']) <= $this->options['gc_probability'])
		{
			$this->storage->garbageCollect();
		}
		
		$new = false;
		$id  = $this->id_handler->fetchUserId($env);
		
		// TODO: Validate format of the id ?
		if( ! $id)
		{
			$new  = true;
			$id   = $this->generateSessionId();
			$data = array();
		}
		// If we don't have a session data entry, it might be a forgery, regenerate id:
		elseif(($data = $this->storage->loadSession($env, $id)) === false)
		{
			$new  = true;
			$id   = $this->generateSessionId();
			$data = array();
		}
		
		$env[$this->sess_key] = new Bucket($this, $id, $data, $new);
		
		$callback = $this->next;
		$ret      = $callback($env);
		
		if($env[$this->sess_key]->getId() !== false)
		{
			$this->storage->saveSession($env[$this->sess_key]);
		}
		
		if($env[$this->sess_key]->isNew())
		{
			$ret = $this->id_handler->storeUserId($env[$this->sess_key]->getId(), $ret);
		}
		
		return $ret;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Tells the storage to remove the data associated with the supplied session
	 * bucket and gives it a new id.
	 * 
	 * @param  \Inject\Stack\Middleware\Session\Bucket
	 * @return void
	 */
	public function invalidateSession(Bucket $data)
	{
		$this->storage->destroySession($data->getId());
		
		$data->setId($this->generateSessionId());
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Destroys the session the supplied bucket represents.
	 * 
	 * @param  \Inject\Stack\Middleware\Session\Bucket
	 * @return void
	 */
	public function destroySession(Bucket $data)
	{
		$this->storage->destroySession($data->getId());
		
		$data->setId(false);
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Generates a Session id in base 64.
	 * 
	 * @return string
	 */
	public function generateSessionId()
	{
		return base64_encode(pack('i', mt_rand()).pack('i', mt_rand())); 
	}
}


/* End of file Session.php */
/* Location: src/php/Inject/Stack/Middleware */