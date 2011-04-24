<?php
/*
 * Created by Martin Wernståhl on 2011-03-06.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace InjectStack;

class UrlMapEndpointTest extends \PHPUnit_Framework_TestCase
{
	public function testInstantiate()
	{
		$endpoint = new UrlMapEndpoint();
		
		$this->assertTrue($endpoint instanceof UrlMapEndpoint);
	}
	/**
	 * @expectedException PHPUnit_Framework_Error
	 */
	public function testInstantiateWithNoArray()
	{
		$endpoint = new UrlMapEndpoint('foobar');
	}
	public function testHasInvoke()
	{
		$endpoint = new UrlMapEndpoint();
		
		$ref = new \ReflectionObject($endpoint);
		
		$this->assertTrue($ref->hasMethod('__invoke'));
		$this->assertEquals(1, $ref->getMethod('__invoke')->getNumberOfParameters());
		$this->assertEquals(1, $ref->getMethod('__invoke')->getNumberOfRequiredParameters());
	}
	/**
	 * @expectedException PHPUnit_Framework_Error
	 */
	public function testInvokeMissingParam()
	{
		$endpoint = new UrlMapEndpoint();
		
		// Missing required parameter:
		$endpoint();
	}
	/**
	 * @expectedException PHPUnit_Framework_Error
	 */
	public function testInvokeMissingParam2()
	{
		$endpoint = new UrlMapEndpoint();
		
		$this->assertEquals(array(404, array(), ''), $endpoint(array()));
	}
	public function testInvokeDefault()
	{
		$env = array('PATH_INFO' => '/test', 'SCRIPT_NAME' => 'default_script');
		$endpoint = new UrlMapEndpoint();
		
		$this->assertEquals(array(404, array(), ''), $endpoint($env));
	}
	public function testRootUrl()
	{
		$env    = array('PATH_INFO' => '/', 'SCRIPT_NAME' => 'default_script');
		$params = false;
		
		$endpoint = new UrlMapEndpoint(array('' => function() use(&$params)
		{
			$params = func_get_args();
			
			return 'This is a return value';
		}));
		
		$this->assertEquals('This is a return value', $endpoint($env));
		$this->assertEquals(array(array('PATH_INFO' => '/', 'SCRIPT_NAME' => 'default_script', 'urlmap.orig.SCRIPT_NAME' => 'default_script')), $params);
	}
	public function testUrlSort()
	{
		$env    = array('PATH_INFO' => '/am/big/ous/url', 'SCRIPT_NAME' => 'script');
		$param1 = false;
		$param2 = false;
		
		$endpoint = new UrlMapEndpoint(array(
			'/am' => function() use(&$param1)
			{
				$param1 = func_get_args();
				
				return 'FAIL! Should not be called!';
			},
			'/am/big' => function() use(&$param2)
			{
				$param2 = func_get_args();
				
				return 'SUCCESS!';
			}
		));
		
		$this->assertEquals('SUCCESS!', $endpoint($env));
		$this->assertEquals(false, $param1);
		$this->assertEquals(array(array('PATH_INFO' => '/ous/url', 'SCRIPT_NAME' => 'script/am/big', 'urlmap.orig.SCRIPT_NAME' => 'script')), $param2);
	}
	public function testMultipleSlashes()
	{
		$env1   = array('PATH_INFO' => '/with///slash', 'SCRIPT_NAME' => 'foo');
		$env2   = array('PATH_INFO' => '/without//slash', 'SCRIPT_NAME' => 'foo');
		$param1 = false;
		$param2 = false;
		
		$endpoint1 = new UrlMapEndpoint(array('/with' => function() use(&$param1)
		{
			$param1 = func_get_args();
			
			return 'with';
		}));
		$endpoint2 = new UrlMapEndpoint(array('/without' => function() use(&$param2)
		{
			$param2 = func_get_args();
			
			return 'without';
		}));
		
		$this->assertEquals('with', $endpoint1($env1));
		$this->assertEquals('without', $endpoint2($env2));
		$this->assertEquals(array(array('PATH_INFO' => '/slash', 'SCRIPT_NAME' => 'foo/with', 'urlmap.orig.SCRIPT_NAME' => 'foo')), $param1);
		$this->assertEquals(array(array('PATH_INFO' => '/slash', 'SCRIPT_NAME' => 'foo/without', 'urlmap.orig.SCRIPT_NAME' => 'foo')), $param2);
	}
	public function testPrependSlash()
	{
		$env1   = array('PATH_INFO' => '/with/slash', 'SCRIPT_NAME' => 'foo');
		$env2   = array('PATH_INFO' => '/without/slash', 'SCRIPT_NAME' => 'foo');
		$param1 = false;
		$param2 = false;
		
		$endpoint1 = new UrlMapEndpoint(array('/with' => function() use(&$param1)
		{
			$param1 = func_get_args();
			
			return 'with';
		}));
		$endpoint2 = new UrlMapEndpoint(array('without' => function() use(&$param2)
		{
			$param2 = func_get_args();
			
			return 'without';
		}));
		
		$this->assertEquals('with', $endpoint1($env1));
		$this->assertEquals('without', $endpoint2($env2));
		$this->assertEquals(array(array('PATH_INFO' => '/slash', 'SCRIPT_NAME' => 'foo/with', 'urlmap.orig.SCRIPT_NAME' => 'foo')), $param1);
		$this->assertEquals(array(array('PATH_INFO' => '/slash', 'SCRIPT_NAME' => 'foo/without', 'urlmap.orig.SCRIPT_NAME' => 'foo')), $param2);
	}
	public function testNotMatching()
	{
		$env    = array('PATH_INFO' => '/some/url', 'SCRIPT_NAME' => 'lol');
		$param1 = false;
		$param2 = false;
		
		$endpoint = new UrlMapEndpoint(array(
			'some/more/specific' => function() use(&$param1)
			{
				$param1 = func_get_args();
				
				return 'FAILURE! SHOULD NOT BE CALLED!';
			},
			'some' => function() use(&$param2)
			{
				$param2 = func_get_args();
				
				return 'SUCCESS!';
			}
		));
		
		$this->assertEquals('SUCCESS!', $endpoint($env));
		$this->assertEquals(false, $param1);
		$this->assertEquals(array(array('PATH_INFO' => '/url', 'SCRIPT_NAME' => 'lol/some', 'urlmap.orig.SCRIPT_NAME' => 'lol')), $param2);
	}
}


/* End of file UrlMapEndpointTest.php */
/* Location: src/tests/unit-tests/php/InjectStack */