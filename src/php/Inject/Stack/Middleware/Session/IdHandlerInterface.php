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
interface IdHandlerInterface
{
	/**
	 * Fetches the user id from the client, if there is no user id, or
	 * if the id is invalid for the user supplying it, return false.
	 * 
	 * @param  array(string => mixed)
	 * @return string
	 */
	public function fetchUserId(array $env);
	
	/**
	 * Stores the user id on the client.
	 * 
	 * @param  string|false  If false, remove the user id from the client
	 * @param  array(int, array(string => string), string)
	 * @return array(int, array(string => string), string)
	 */
	public function storeUserId($id, array $ret);
}


/* End of file IdHandlerInterface.php */
/* Location: src/php/Inject/Stack/Middleware/Session */