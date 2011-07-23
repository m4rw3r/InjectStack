<?php
/*
 * Created by Martin Wernståhl on 2011-03-06.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace Inject\Stack\Middleware;

use \Inject\Stack\MiddlewareInterface;

class RunTimerTest extends \PHPUnit_Framework_TestCase
{
	public function testInstantiate()
	{
		$obj = new RunTimer();
		
		$this->assertTrue($obj instanceof RunTimer);
		$this->assertTrue($obj instanceof MiddlewareInterface);
	}
	public function testRun()
	{
		$obj   = new RunTimer();
		$param = false;
		
		$obj->setNext(function($env) use(&$param)
		{
			$param = func_get_args();
			
			return array(200, array(), 'data');
		});
		
		$res = $obj(array('test' => 'data'));
		
		$this->assertXRuntimeKey($res, 'X-Runtime', 200, 'data');
		
		$this->assertEquals(array(array('test' => 'data')), $param);
	}
	
	public function testRun2()
	{
		$obj   = new RunTimer('test');
		$param = false;
		
		$obj->setNext(function($env) use(&$param)
		{
			$param = func_get_args();
			
			return array(200, array(), 'data');
		});
		
		$res = $obj(array('test' => 'data'));
		
		$this->assertXRuntimeKey($res, 'X-Runtime-test', 200, 'data');
		
		$this->assertEquals(array(array('test' => 'data')), $param);
	}
	
	public function testRu3()
	{
		$obj   = new RunTimer('FooBar');
		$param = false;
		
		$obj->setNext(function($env) use(&$param)
		{
			$param = func_get_args();
			
			return array(200, array(), 'data');
		});
		
		$res = $obj(array('test' => 'data'));
		
		$this->assertXRuntimeKey($res, 'X-Runtime-FooBar', 200, 'data');
		
		$this->assertEquals(array(array('test' => 'data')), $param);
	}
	
	protected function assertXRuntimeKey($res, $name, $expected_ret_code, $expected_ret_data)
	{
		$this->assertEquals(3, count($res));
		$this->assertArrayHasKey(0, $res);
		$this->assertSame($expected_ret_code, $res[0]);
		$this->assertArrayHasKey(1, $res);
		$this->assertFalse(empty($res[1][$name]));
		$this->assertInternalType('float', $res[1][$name]);
		$this->assertArrayHasKey(2, $res);
		$this->assertEquals($expected_ret_data, $res[2]);
	}
}


/* End of file RunTimerTest.php */
/* Location: src/tests/unit-tests/php/Inject/Stack/Middleware */