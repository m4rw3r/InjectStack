<?php
/*
 * Created by Martin Wernståhl on 2011-04-30.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace Inject\Stack\Middleware\Session;

/**
 * Array storage, for use with Mongrel2 application server process,
 * for debug purposes only.
 * 
 * NOTE: This storage persists data in-memory of the PHP process,
 * meaning that data is not shared among PHP workers which means that
 * you can only use one PHP process while using this storage handler.
 */
class ArrayStorage implements StorageInterface
{
	/**
	 * Maximum session age in seconds.
	 * 
	 * @var int
	 */
	protected $max_age = 3600;
	
	/**
	 * List of last changed times for the data entries.
	 * 
	 * @var array(string => int)
	 */
	protected $time = array();
	
	/**
	 * Session entries.
	 * 
	 * @var array(string => array)
	 */
	protected $data = array();
	
	// ------------------------------------------------------------------------

	/**
	 * 
	 * 
	 * @return 
	 */
	public function __construct($max_age = 3600)
	{
		$this->max_age = 3600;
	}
	
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
		if( ! array_key_exists($id, $this->data))
		{
			return false;
		}
		
		return $this->data[$id];
	}
	
	/**
	 * Saves the supplied bucket instance.
	 * 
	 * @param  Bucket
	 * @return void
	 */
	public function saveSession(Bucket $data)
	{
		$this->time[$data->getId()] = time();
		$this->data[$data->getId()] = $data->getArrayCopy();
	}
	
	/**
	 * Deletes session information related to the supplied id.
	 * 
	 * @param  scalar
	 * @return void
	 */
	public function destroySession($id)
	{
		unset($this->time[$id]);
		unset($this->data[$id]);
	}
	
	public function garbageCollect()
	{
		$ctime = time();
		
		foreach($this->time as $key => $time)
		{
			if($ctime - $time > $that->max_age)
			{
				unset($this->time[$key]);
				unset($this->data[$key]);
			}
		}
	}
}


/* End of file SessionInterface.php */
/* Location: src/php/Inject/Stack/Middleware/Session */