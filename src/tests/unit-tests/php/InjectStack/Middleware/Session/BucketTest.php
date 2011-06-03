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
		$this->assertEquals(array(), $obj->getArrayCopy());
		$this->assertEquals('id', $obj->getId());
		$this->assertFalse($obj->isNew());
	}
	public function testInstantiate2()
	{
		$obj = new Bucket($this->getSessionMock(), 'id', array(), false);
		
		$this->assertTrue($obj instanceof Bucket);
		$this->assertTrue($obj instanceof ArrayObject);
		$this->assertEquals(array(), $obj->getArrayCopy());
		$this->assertEquals('id', $obj->getId());
		$this->assertFalse($obj->isNew());
	}
	public function testInstantiate3()
	{
		$obj = new Bucket($this->getSessionMock(), 'id', array(), true);
		
		$this->assertTrue($obj instanceof Bucket);
		$this->assertTrue($obj instanceof ArrayObject);
		$this->assertEquals(array(), $obj->getArrayCopy());
		$this->assertEquals('id', $obj->getId());
		$this->assertTrue($obj->isNew());
	}
	public function testInstantiate4()
	{
		$obj = new Bucket($this->getSessionMock(), 'id', array('test' => 'data'), true);
		
		$this->assertTrue($obj instanceof Bucket);
		$this->assertTrue($obj instanceof ArrayObject);
		$this->assertEquals(array('test' => 'data'), $obj->getArrayCopy());
		$this->assertEquals('id', $obj->getId());
		$this->assertTrue($obj->isNew());
	}
	/**
	 * @expectedException PHPUnit_Framework_Error
	 */
	public function testInstantiate5()
	{
		$obj = new Bucket(new stdClass, 'id');
	}
	/**
	 * @expectedException PHPUnit_Framework_Error
	 */
	public function testInstantiate6()
	{
		$obj = new Bucket($this->getSessionMock(), 'id', 'text');
	}
	public function testInstantiate7()
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
		$this->assertTrue($obj->isNew());
	}
	public function testClearSession()
	{
		$obj = new Bucket($this->getSessionMock(), 'yw3ry', array('test' => 'lol'));
		
		$this->assertEquals(array('test' => 'lol'), $obj->getArrayCopy());
		$obj->clearSession();
		$this->assertEquals(array(), $obj->getArrayCopy());
		$this->assertFalse($obj->isNew());
	}
	public function testInvalidateSession()
	{
		$mock = $this->getSessionMock();
		
		$mock->expects($this->once())->method('invalidateSession')
			->with($this->isInstanceOf('InjectStack\\Middleware\\Session\\Bucket'));
		
		$obj = new Bucket($mock, 'yw3ry', array('test' => 'lol'));
		
		$obj->invalidateSession();
		$this->assertEquals(array(), $obj->getArrayCopy());
		$this->assertFalse($obj->isNew()); // Session changes the ID, so still false
	}
	public function testInvalidateSessionAlt()
	{
		$mock = $this->getSessionMock();
		
		$mock->expects($this->once())->method('invalidateSession')
			->with($this->isInstanceOf('InjectStack\\Middleware\\Session\\Bucket'));
		
		$obj = new Bucket($mock, 'yw3ry', array('test' => 'lol'));
		
		$obj->invalidateSession(false);
		$this->assertEquals(array(), $obj->getArrayCopy());
		$this->assertFalse($obj->isNew()); // Session changes the ID, so still false
	}
	public function testInvalidateSessionAlt2()
	{
		$mock = $this->getSessionMock();
		
		$mock->expects($this->once())->method('invalidateSession')
			->with($this->isInstanceOf('InjectStack\\Middleware\\Session\\Bucket'));
		
		$obj = new Bucket($mock, 'yw3ry', array('test' => 'lol'));
		
		$obj->invalidateSession(true);
		$this->assertEquals(array('test' => 'lol'), $obj->getArrayCopy());
		$this->assertFalse($obj->isNew()); // Session changes the ID, so still false
	}
	public function testDestroySession()
	{
		$mock = $this->getSessionMock();
		
		$obj = new Bucket($mock, 'yw3ry', array('test' => 'lol'));
		
		$mock->expects($this->once())->method('destroySession')
			->with($this->isInstanceOf('InjectStack\\Middleware\\Session\\Bucket'));
		
		$obj->destroySession();
		
		$this->assertEquals(array(), $obj->getArrayCopy());
		$this->assertFalse($obj->isNew()); // Session changes the ID, so still false
	}
}


/* End of file BucketTest.php */
/* Location: src/tests/unit-tests/php/InjectStack/Middleware/Session */