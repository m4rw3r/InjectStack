<?php
/*
 * Created by Martin Wernståhl on 2011-03-06.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace InjectStack;

use \InjectStack\MiddlewareInterface;

class InjectStackTest extends \PHPUnit_Framework_TestCase
{
	public function testInstantiate()
	{
		$stack = new InjectStack();
		
		$this->assertTrue($stack instanceof InjectStack);
	}
	
	public function testImplements__invoke()
	{
		$stack = new InjectStack();
		
		$ref = new \ReflectionObject($stack);
		
		$this->assertTrue($ref->hasMethod('__invoke'));
		$this->assertEquals(1, $ref->getMethod('__invoke')->getNumberOfParameters());
		$this->assertEquals(1, $ref->getMethod('__invoke')->getNumberOfRequiredParameters());
	}
	
	/**
	 * @expectedException \InjectStack\NoEndpointException
	 */
	public function testNoEndpointException()
	{
		$stack = new InjectStack();
		
		$stack('DATA');
	}
	
	/**
	 * @expectedException PHPUnit_Framework_Error
	 */
	public function testFaultyParameter()
	{
		$stack = new InjectStack(new \stdClass);
	}
	
	public function testOnlyEndpoint()
	{
		$stack = new InjectStack();
		
		$run = false;
		
		$stack->setEndpoint(function($data) use(&$run)
		{
			$run = $data;
			
			return 'RETURN';
		});
		
		$r = $stack('TESTING!');
		
		$this->assertEquals('TESTING!', $run);
		$this->assertEquals('RETURN', $r);
	}
	
	public function testAddMiddleware()
	{
		$endpoint = function($env)
		{
			return $env.'HANDLED';
		};
		
		$middleware = $this->getMock('InjectStack\\MiddlewareInterface');
		
		$middleware->expects($this->once())->method('setNext')->with($endpoint);
		$middleware->expects($this->once())->method('__invoke')->with('TESTDATA')->will($this->returnCallback(function($env) use($endpoint)
		{
			return $endpoint($env);
		}));
		
		$stack = new InjectStack();
		
		$stack->addMiddleware($middleware);
		$stack->setEndpoint($endpoint);
		
		$r = $stack('TESTDATA');
		
		$this->assertEquals('TESTDATAHANDLED', $r);
	}
	
	public function testAddMultipleMiddleware()
	{
		$endpoint = function($env)
		{
			return $env.'HANDLED';
		};
		
		$middleware2 = $this->getMock('InjectStack\\MiddlewareInterface');
		
		$middleware2->expects($this->once())->method('setNext')->with($endpoint);
		$middleware2->expects($this->once())->method('__invoke')->with('1TESTDATA')->will($this->returnCallback(function($env) use($endpoint)
		{
			return $endpoint('2'.$env).'2';
		}));
		
		$middleware = $this->getMock('InjectStack\\MiddlewareInterface');
		
		$middleware->expects($this->once())->method('setNext')->with($middleware2);
		$middleware->expects($this->once())->method('__invoke')->with('TESTDATA')->will($this->returnCallback(function($env) use($middleware2)
		{
			return $middleware2('1'.$env).'1';
		}));
		
		
		$stack = new InjectStack();
		
		$stack->addMiddleware($middleware);
		$stack->addMiddleware($middleware2);
		$stack->setEndpoint($endpoint);
		
		$r = $stack('TESTDATA');
		
		$this->assertEquals('21TESTDATAHANDLED21', $r);
	}
	
	public function testPrependMiddleware()
	{
		$endpoint = function($env)
		{
			return $env.'HANDLED';
		};
		
		
		$middleware = $this->getMock('InjectStack\\MiddlewareInterface');
		
		$middleware->expects($this->once())->method('setNext')->with($endpoint);
		$middleware->expects($this->once())->method('__invoke')->with('2TESTDATA')->will($this->returnCallback(function($env) use($endpoint)
		{
			return $endpoint('1'.$env).'1';
		}));
		
		$middleware2 = $this->getMock('InjectStack\\MiddlewareInterface');
		
		$middleware2->expects($this->once())->method('setNext')->with($middleware);
		$middleware2->expects($this->once())->method('__invoke')->with('TESTDATA')->will($this->returnCallback(function($env) use($middleware)
		{
			return $middleware('2'.$env).'2';
		}));
		
		$stack = new InjectStack();
		
		$stack->addMiddleware($middleware);
		$stack->prependMiddleware($middleware2);
		$stack->setEndpoint($endpoint);
		
		$r = $stack('TESTDATA');
		
		$this->assertEquals('12TESTDATAHANDLED12', $r);
	}
	
	public function testAddMiddlewareAlternateSyntax()
	{
		$endpoint = function($env)
		{
			return $env.'HANDLED';
		};
		
		$middleware = $this->getMock('InjectStack\\MiddlewareInterface');
		
		$middleware->expects($this->once())->method('setNext')->with($endpoint);
		$middleware->expects($this->once())->method('__invoke')->with('TESTDATA')->will($this->returnCallback(function($env) use($endpoint)
		{
			return $endpoint($env);
		}));
		
		$stack = new InjectStack(array($middleware), $endpoint);
		
		$r = $stack('TESTDATA');
		
		$this->assertEquals('TESTDATAHANDLED', $r);
	}
}


/* End of file InjectStackTest.php */
/* Location: src/tests/unit-tests/php/InjectStack */