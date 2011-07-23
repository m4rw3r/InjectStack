<?php
/*
 * Created by Martin Wernståhl on 2011-03-06.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace Inject\Stack\Middleware;

use \Inject\Stack\MiddlewareInterface;

/**
 * @covers Inject\Stack\Middleware\Session
 */
class SessionTest extends \PHPUnit_Framework_TestCase
{
	public function testInstantiate()
	{
		$obj = new Session($this->getMock('Inject\\Stack\\Middleware\\Session\\StorageInterface'),
			$this->getMock('Inject\\Stack\\Middleware\\Session\\IdHandlerInterface'));
		
		$this->assertTrue($obj instanceof Session);
		$this->assertTrue($obj instanceof MiddlewareInterface);
	}
	public function testRun()
	{
		$storage = $this->getMock('Inject\\Stack\\Middleware\\Session\\StorageInterface');
		$idhandl = $this->getMock('Inject\\Stack\\Middleware\\Session\\IdHandlerInterface');
		
		$idhandl->expects($this->once())->method('fetchUserId')
			->with(array('test' => 'data'))
			->will($this->returnValue('this_is_a_user_id'));
		$idhandl->expects($this->never())->method('storeUserId');
		
		$storage->expects($this->once())->method('loadSession')
			->with(array('test' => 'data'), 'this_is_a_user_id')
			->will($this->returnValue(array('key' => 'value')));
		$storage->expects($this->never())->method('destroySession');
		
		$obj   = new Session($storage, $idhandl);
		$param = false;
		$that  = $this;
		
		$obj->setNext(function($env) use(&$param, $that, $storage)
		{
			
			$that->assertEquals(2, count($env));
			$that->assertEquals('data', $env['test']);
			$that->assertFalse(empty($env['inject.session']));
			$that->assertInstanceOf('Inject\\Stack\\Middleware\\Session\\Bucket', $env['inject.session']);
			$that->assertEquals('this_is_a_user_id', $env['inject.session']->getId());
			$that->assertInstanceOf('ArrayObject', $env['inject.session']);
			$that->assertEquals(array('key' => 'value'), $env['inject.session']->getArrayCopy());
			
			$storage->expects($that->once())->method('saveSession')->with($env['inject.session']);
			
			return array(200, array(), 'data');
		});
		
		$this->assertEquals(array(200, array(), 'data'), $obj(array('test' => 'data')));
	}
	public function testRun2()
	{
		$storage = $this->getMock('Inject\\Stack\\Middleware\\Session\\StorageInterface');
		$idhandl = $this->getMock('Inject\\Stack\\Middleware\\Session\\IdHandlerInterface');
		
		$idhandl->expects($this->once())->method('fetchUserId')
			->with(array('test' => 'data'))
			->will($this->returnValue(false));
		$idhandl->expects($this->once())->method('storeUserId')
			->with($this->isType('string'), array(200, array(), 'data'))
			->will($this->returnValue(array(200, array('modded' => 'true'), 'data')));
		
		$storage->expects($this->never())->method('loadSession');
		$storage->expects($this->never())->method('destroySession');
		
		$obj   = new Session($storage, $idhandl);
		$param = false;
		$that  = $this;
		
		$obj->setNext(function($env) use(&$param, $that, $storage)
		{
			
			$that->assertEquals(2, count($env));
			$that->assertEquals('data', $env['test']);
			$that->assertFalse(empty($env['inject.session']));
			$that->assertInstanceOf('Inject\\Stack\\Middleware\\Session\\Bucket', $env['inject.session']);
			$that->assertInternalType('string', $env['inject.session']->getId());
			$that->assertInstanceOf('ArrayObject', $env['inject.session']);
			$that->assertEquals(array(), $env['inject.session']->getArrayCopy());
			
			$storage->expects($that->once())->method('saveSession')->with($env['inject.session']);
			
			return array(200, array(), 'data');
		});
		
		$this->assertEquals(array(200, array('modded' => 'true'), 'data'), $obj(array('test' => 'data')));
	}
	public function testNoId()
	{
		$storage = $this->getMock('Inject\\Stack\\Middleware\\Session\\StorageInterface');
		$idhandl = $this->getMock('Inject\\Stack\\Middleware\\Session\\IdHandlerInterface');
		
		$idhandl->expects($this->once())->method('fetchUserId')
			->with(array('test' => 'data'))
			->will($this->returnValue(false));
		$idhandl->expects($this->once())->method('storeUserId')
			->with($this->isType('string'), array(200, array(), 'data'))
			->will($this->returnValue(array(200, array('parsed' => 'true'), 'data')));
		
		$storage->expects($this->never())->method('loadSession');
		$storage->expects($this->never())->method('destroySession');
		
		$obj   = new Session($storage, $idhandl);
		$param = false;
		$that  = $this;
		
		$obj->setNext(function($env) use(&$param, $that, $storage)
		{
			
			$that->assertEquals(2, count($env));
			$that->assertEquals('data', $env['test']);
			$that->assertFalse(empty($env['inject.session']));
			$that->assertInstanceOf('Inject\\Stack\\Middleware\\Session\\Bucket', $env['inject.session']);
			$that->assertInternalType('string', $env['inject.session']->getId());
			$that->assertInstanceOf('ArrayObject', $env['inject.session']);
			$that->assertEquals(array(), $env['inject.session']->getArrayCopy());
			
			$storage->expects($that->once())->method('saveSession')->with($env['inject.session']);
			
			return array(200, array(), 'data');
		});
		
		$this->assertEquals(array(200, array('parsed' => 'true'), 'data'), $obj(array('test' => 'data')));
	}
	public function testInvalidateSession()
	{
		$storage = $this->getMock('Inject\\Stack\\Middleware\\Session\\StorageInterface');
		$idhandl = $this->getMock('Inject\\Stack\\Middleware\\Session\\IdHandlerInterface');
		
		$idhandl->expects($this->once())->method('fetchUserId')
			->with(array('test' => 'data'))
			->will($this->returnValue('this_id'));
		$idhandl->expects($this->once())->method('storeUserId')
			->with($this->logicalAnd($this->isType('string'),
				$this->logicalNot($this->equalTo('this_id'))),
				array(200, array(), 'data'))
			->will($this->returnValue(array(200, array('parsed' => 'true'), 'data')));
		
		$storage->expects($this->once())->method('loadSession')
			->with(array('test' => 'data'), 'this_id')
			->will($this->returnValue(array('key' => 'value')));
		$storage->expects($this->once())->method('destroySession')->with('this_id');
		
		$obj   = new Session($storage, $idhandl);
		$param = false;
		$that  = $this;
		
		$obj->setNext(function($env) use(&$param, $that, $storage)
		{
			
			$that->assertEquals(2, count($env));
			$that->assertEquals('data', $env['test']);
			$that->assertFalse(empty($env['inject.session']));
			$that->assertInstanceOf('Inject\\Stack\\Middleware\\Session\\Bucket', $env['inject.session']);
			$that->assertInternalType('string', $env['inject.session']->getId());
			$that->assertInstanceOf('ArrayObject', $env['inject.session']);
			$that->assertEquals(array('key' => 'value'), $env['inject.session']->getArrayCopy());
			
			$env['inject.session']->invalidateSession();
			
			$that->assertEquals(array(), $env['inject.session']->getArrayCopy());
			
			$storage->expects($that->once())->method('saveSession')->with($env['inject.session']);
			
			return array(200, array(), 'data');
		});
		
		$this->assertEquals(array(200, array('parsed' => 'true'), 'data'), $obj(array('test' => 'data')));
	}
	public function testNoSessionData()
	{
		$storage = $this->getMock('Inject\\Stack\\Middleware\\Session\\StorageInterface');
		$idhandl = $this->getMock('Inject\\Stack\\Middleware\\Session\\IdHandlerInterface');
		
		$idhandl->expects($this->once())->method('fetchUserId')
			->with(array('test' => 'data'))
			->will($this->returnValue('this_is_a_user_id'));
		$idhandl->expects($this->once())->method('storeUserId')
			->with($this->isType('string'), array(200, array(), 'data'))
			->will($this->returnValue(array(200, array('modded' => 'true'), 'data')));
		
		$storage->expects($this->once())->method('loadSession')
			->with(array('test' => 'data'), $this->isType('string'))
			->will($this->returnValue(false));
		$storage->expects($this->never())->method('destroySession');
		
		$obj   = new Session($storage, $idhandl);
		$param = false;
		$that  = $this;
		
		$obj->setNext(function($env) use(&$param, $that, $storage)
		{
			
			$that->assertEquals(2, count($env));
			$that->assertEquals('data', $env['test']);
			$that->assertFalse(empty($env['inject.session']));
			$that->assertInstanceOf('Inject\\Stack\\Middleware\\Session\\Bucket', $env['inject.session']);
			$that->assertInternalType('string', $env['inject.session']->getId());
			$that->assertInstanceOf('ArrayObject', $env['inject.session']);
			$that->assertEquals(array(), $env['inject.session']->getArrayCopy());
			
			$storage->expects($that->once())->method('saveSession')->with($env['inject.session']);
			
			return array(200, array(), 'data');
		});
		
		$this->assertEquals(array(200, array('modded' => 'true'), 'data'), $obj(array('test' => 'data')));
	}
	public function testGarbageCollect()
	{
		$storage = $this->getMock('Inject\\Stack\\Middleware\\Session\\StorageInterface');
		$idhandl = $this->getMock('Inject\\Stack\\Middleware\\Session\\IdHandlerInterface');
		
		$idhandl->expects($this->once())->method('fetchUserId')
			->with(array('test' => 'data'))
			->will($this->returnValue('this_is_a_user_id'));
		$idhandl->expects($this->never())->method('storeUserId');
		
		$storage->expects($this->once())->method('loadSession')
			->with(array('test' => 'data'), 'this_is_a_user_id')
			->will($this->returnValue(array('key' => 'value')));
		$storage->expects($this->never())->method('destroySession');
		$storage->expects($this->once())->method('garbageCollect');
		
		$obj   = new Session($storage, $idhandl, array('gc_probability' => 1, 'gc_divisor' => 1));
		$param = false;
		$that  = $this;
		
		$obj->setNext(function($env) use(&$param, $that, $storage)
		{
			$storage->expects($that->once())->method('saveSession')->with($env['inject.session']);
			
			return array(200, array(), 'data');
		});
		
		$this->assertEquals(array(200, array(), 'data'), $obj(array('test' => 'data')));
	}
	public function testDestroySession()
	{
		$storage = $this->getMock('Inject\\Stack\\Middleware\\Session\\StorageInterface');
		$idhandl = $this->getMock('Inject\\Stack\\Middleware\\Session\\IdHandlerInterface');
		
		$idhandl->expects($this->once())->method('fetchUserId')
			->with(array('test' => 'data'))
			->will($this->returnValue('this_is_a_user_id'));
		$idhandl->expects($this->once())->method('storeUserId')
			->with(false, array(200, array(), 'data'))
			->will($this->returnValue(array(200, array('processed' => 'true'), 'data')));
		
		$storage->expects($this->once())->method('loadSession')
			->with(array('test' => 'data'), 'this_is_a_user_id')
			->will($this->returnValue(array('key' => 'value')));
		$storage->expects($this->never())->method('saveSession');
		$storage->expects($this->once())->method('destroySession')
			->with('this_is_a_user_id');
		
		$obj   = new Session($storage, $idhandl);
		$param = false;
		$that  = $this;
		
		$obj->setNext(function($env) use(&$param, $that, $storage)
		{
			$env['inject.session']->destroySession();
			$that->assertFalse($env['inject.session']->getId());
			
			return array(200, array(), 'data');
		});
		
		$this->assertEquals(array(200, array('processed' => 'true'), 'data'), $obj(array('test' => 'data')));
	}
}


/* End of file SessionTest.php */
/* Location: src/tests/unit-tests/php/Inject/Stack/Middleware */