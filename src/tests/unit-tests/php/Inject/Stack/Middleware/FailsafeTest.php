<?php
/*
 * Created by Martin Wernståhl on 2011-03-06.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace Inject\Stack\Middleware;

use \Inject\Stack\MiddlewareInterface;

class FailsafeTest extends \PHPUnit_Framework_TestCase
{
	public function testInstantiate()
	{
		$obj = new Failsafe();
		
		$this->assertTrue($obj instanceof Failsafe);
		$this->assertTrue($obj instanceof MiddlewareInterface);
	}
	public function testRun()
	{
		$obj   = new Failsafe();
		$param = false;
		
		$obj->setNext(function($env) use(&$param)
		{
			$param = func_get_args();
			
			return 'data';
		});
		
		$this->assertEquals('data', $obj(array('test' => 'data')));
		$this->assertEquals(array(array('test' => 'data')), $param);
	}
	public function testCatch()
	{
		$obj = new Failsafe();
		
		$obj->setNext(function()
		{
			throw new \Exception();
		});
		
		$this->assertEquals(array(500, array(), ''), $obj(array()));
	}
}


/* End of file FailsafeTest.php */
/* Location: src/tests/unit-tests/php/Inject/Stack/Middleware */