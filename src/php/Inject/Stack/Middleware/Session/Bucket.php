<?php
/*
 * Created by Martin Wernståhl on 2011-04-30.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace InjectStack\Middleware\Session;

use \ArrayObject;

use \InjectStack\Middleware\Session;

/**
 * Object containing session data, the populating and persisting of
 * this object is handled by the \InjectStack\Middleware\Session middleware.
 */
class Bucket extends ArrayObject
{
	/**
	 * Storage instance managing the data of this object.
	 * 
	 * @var \InjectStack\Middleware\Session\StorageInterface
	 */
	protected $session;
	
	/**
	 * The session id.
	 * 
	 * @var scalar
	 */
	protected $id = null;
	
	/**
	 * If this session is new or not.
	 * 
	 * @var boolean
	 */
	protected $new = false;
	
	/**
	 * If the session is destroyed or not.
	 * 
	 * @var boolean
	 */
	protected $destroyed = false;
	
	// ------------------------------------------------------------------------

	/**
	 * @param  \InjectStack\Middleware\Session
	 * @param  string
	 * @param  array
	 */
	public function __construct(Session $session, $id, array $data = array(), $new = false)
	{
		parent::__construct($data);
		
		$this->session = $session;
		$this->id      = $id;
		$this->new     = $new;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Sets the id of this session, do not use unless you really know what its
	 * implications are, use invalidateSession() instead to create a new id.
	 * 
	 * Should only be used if you understand the implications, because
	 * if this is used, then the old session will still remain intact, which
	 * is not recommended. Use invalidateSession() instead to force removal
	 * of the old session entry in the storage.
	 * 
	 * @param  string
	 * @return void
	 */
	public function setId($id)
	{
		$this->id  = $id;
		$this->new = true;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns the unique id of this session.
	 * 
	 * @return string
	 */
	public function getId()
	{
		return $this->id;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns true if this session has a new id, and should be written to the
	 * client.
	 * 
	 * @return boolean
	 */
	public function isNew()
	{
		return $this->new;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Will clear all saved attributes from this session.
	 * 
	 * @return void
	 */
	public function clearSession()
	{
		$this->exchangeArray(array());
	}
	
	// ------------------------------------------------------------------------
	
	/**
	 * Will remove data in storage and generate a new session id for the user.
	 * 
	 * @param  boolean  If to clear the data of the session, or if it should
	 *                  carry over to the new ID
	 * @return void
	 */
	public function invalidateSession($keep_data = false)
	{
		$this->session->invalidateSession($this);
		
		$keep_data OR $this->exchangeArray(array());
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Destroys the session: removes the Session id from the client, destroys
	 * data in storage and also clears the contents of this object.
	 * 
	 * @return void
	 */
	public function destroySession()
	{
		$this->session->destroySession($this);
		
		$this->exchangeArray(array());
	}
}


/* End of file Bucket.php */
/* Location: src/php/InjectStack/Middleware/Session */