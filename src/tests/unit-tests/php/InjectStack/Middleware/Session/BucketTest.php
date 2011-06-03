<?php
/*
 * Created by Martin Wernståhl on 2011-05-27.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace InjectStack\Middleware\Session;

use \stdClass;
use \ArrayObject;

/**
 * @covers InjectStack\Middleware\Session\Bucket
 */
class BucketTest extends \PHPUnit_Framework_TestCase
{
	protected function getSessionMock()
	{
		return $this->getMock('InjectStack\\Middleware\\Session', array(), array(), '', false);
	}
	
	public function testInstantiate()
	{
		$obj = new Bucket($this->getSessionMock(), 'id');
		
		$this->assertTrue($obj instanceof Bucket);
		$this->assertTrue($obj instanceof ArrayObject);
		$this->assertEquals('id', $obj->getId());
	}
	/**
	 * @expectedException PHPUnit_Framework_Error
	 */
	public function testInstantiate2()
	{
		$obj = new Bucket(new stdClass, 'id');
	}
	/**
	 * @expectedException PHPUnit_Framework_Error
	 */
	public function testInstantiate3()
	{
		$obj = new Bucket($this->getSessionMock(), 'id', 'text');
	}
	public function testInstantiate4()
	{
		$obj = new Bucket($this->getSessionMock(), 'yw3ry', array('test' => 'lol'));
		
		$this->assertTrue($obj instanceof Bucket);
		$this->assertEquals('yw3ry', $obj->getId());
		$this->assertEquals('lol', $obj['test']);
	}
	public function testChangeId()
	{
		$obj = new Bucket($this->getSessionMock(), 'yw3ry', array('test' => 'lol'));
		
		$this->assertEquals('yw3ry', $obj->getId());
		$obj->setId('newid');
		$this->assertEquals('newid', $obj->getId());
	}
	public function testClearSession()
	{
		$obj = new Bucket($this->getSessionMock(), 'yw3ry', array('test' => 'lol'));
		
		$this->assertEquals(array('test' => 'lol'), $obj->getArrayCopy());
		$obj->clearSession();
		$this->assertEquals(array(), $obj->getArrayCopy());
	}
	public function testInvalidateSession()
	{
		$mock = $this->getSessionMock();
		
		$mock->expects($this->once())->method('invalidateSession')
			->with($this->isInstanceOf('InjectStack\\Middleware\\Session\\Bucket'));
		
		$obj = new Bucket($mock, 'yw3ry', array('test' => 'lol'));
		
		$obj->invalidateSession();
		$this->assertEquals(array(), $obj->getArrayCopy());
	}
}


/* End of file BucketTest.php */
/* Location: src/tests/unit-tests/php/InjectStack/Middleware/Session */