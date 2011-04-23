<?php
/*
 * Created by Martin Wernståhl on 2011-03-06.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace InjectStack;

class CascadeEndpointTest extends \PHPUnit_Framework_TestCase
{
	public function testInstantiate()
	{
		$endpoint = new CascadeEndpoint();
		
		$this->assertTrue($endpoint instanceof CascadeEndpoint);
	}
	/**
	 * @expectedException PHPUnit_Framework_Error
	 */
	public function testInstantiateWithNoArray()
	{
		$endpoint = new CascadeEndpoint('foobar');
	}
	public function testHasInvoke()
	{
		$endpoint = new CascadeEndpoint();
		
		$this->assertTrue(is_callable($endpoint));
	}
	/**
	 * @expectedException PHPUnit_Framework_Error
	 */
	public function testInvokeMissingParam()
	{
		$endpoint = new CascadeEndpoint();
		
		// Missing required parameter:
		$endpoint();
	}
	public function testInvokeDefault()
	{
		$endpoint = new CascadeEndpoint();
		
		$this->assertEquals(array(404, array(), ''), $endpoint(array()));
	}
	public function testInvokeSingle()
	{
		$received_params = false;
		$param = array('this' => 'test', 'parameter' => 'is awesome!');
		$ret   = array(123, array('some' => 'return'), '');
		
		$endpoint = new CascadeEndpoint(array(function() use(&$received_params, $ret)
		{
			$received_params = func_get_args();
			
			return $ret;
		}));
		
		$this->assertEquals($ret, $endpoint($param));
		$this->assertEquals(array($param), $received_params);
	}
	public function testInvokeCascade()
	{
		$data  = array('some' => 'cool', 'test' => 'data');
		$call1 = false;
		$call2 = false;
		$call3 = false;
		
		$endpoint = new CascadeEndpoint(array(function() use(&$call1)
		{
			$call1 = func_get_args();
			
			return array(404, array('From' => 'call1'), 'From call1');
		},
		function() use(&$call2)
		{
			$call2 = func_get_args();
			
			return array(200, array('From' => 'call2'), 'From call2');
		},
		function() use(&$call3)
		{
			$call3 = func_get_args();
			
			return array(200, array('From' => 'call3'), 'From call3');
		}));
		
		$this->assertEquals(array(200, array('From' => 'call2'), 'From call2'), $endpoint($data));
		$this->assertEquals(array($data), $call1);
		$this->assertEquals(array($data), $call2);
		$this->assertEquals(false, $call3);
	}
	public function testAdd()
	{
		$data  = array('some' => 'cool', 'test' => 'data');
		$call1 = false;
		$call2 = false;
		
		$endpoint = new CascadeEndpoint(array(function() use(&$call1)
		{
			$call1 = func_get_args();
			
			return array(404, array('From' => 'call1'), 'From call1');
		}));
		
		$endpoint->add(function() use(&$call2)
		{
			$call2 = func_get_args();
			
			return array(200, array('From' => 'call2'), 'From call2');
		});
		
		$this->assertEquals(array(200, array('From' => 'call2'), 'From call2'), $endpoint($data));
		$this->assertEquals(array($data), $call1);
		$this->assertEquals(array($data), $call2);
	}
}


/* End of file CascadeEndpointTest.php */
/* Location: src/tests/unit-tests/php/InjectStack */