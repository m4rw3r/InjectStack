<?php
/*
 * Created by Martin Wernståhl on 2011-04-30.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace InjectStack\Middleware\Session;

use \InjectStack\Middleware\Session;

/**
 * 
 */
interface StorageInterface
{
	/**
	 * Loads an existing session, or creates a new one if none exists for
	 * the supplied id, and returns a populated Bucket instance.
	 * 
	 * @param  array(string => mixed)  The $env var
	 * @param  string                  The session id
	 * @return array(string => mixed)|false  The session data, false if no
	 *                                       stored session is present
	 */
	public function loadSession(array $env, $id);
	
	/**
	 * Saves the supplied bucket instance.
	 * 
	 * @param  Bucket
	 * @return void
	 */
	public function saveSession(Bucket $data);
	
	/**
	 * Deletes session information related to the supplied id.
	 * 
	 * @param  scalar
	 * @return void
	 */
	public function destroySession($id);
	
	/**
	 * Performs session data garbage collection.
	 */
	public function garbageCollect();
}


/* End of file SessionInterface.php */
/* Location: src/php/InjectStack/Middleware/Session */