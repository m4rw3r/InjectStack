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
		$m = new InjectStack();
		
		$this->assertTrue($m instanceof InjectStack);
	}
	
	/**
	 * @expectedException \InjectStack\NoEndpointException
	 */
	public function testNoEndpointException()
	{
		$m = new InjectStack();
		
		$m->run('DATA');
	}
	
	/**
	 * @expectedException PHPUnit_Framework_Error
	 */
	public function testFaultyParameter()
	{
		$m = new InjectStack(new \stdClass);
	}
	
	public function testOnlyEndpoint()
	{
		$m = new InjectStack();
		
		$run = false;
		
		$m->setEndpoint(function($data) use(&$run)
		{
			$run = $data;
			
			return 'RETURN';
		});
		
		$r = $m->run('TESTING!');
		
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
		
		$m = new InjectStack();
		
		$m->addMiddleware($middleware);
		$m->setEndpoint($endpoint);
		
		$r = $m->run('TESTDATA');
		
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
		
		
		$m = new InjectStack();
		
		$m->addMiddleware($middleware);
		$m->addMiddleware($middleware2);
		$m->setEndpoint($endpoint);
		
		$r = $m->run('TESTDATA');
		
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
		
		$m = new InjectStack();
		
		$m->addMiddleware($middleware);
		$m->prependMiddleware($middleware2);
		$m->setEndpoint($endpoint);
		
		$r = $m->run('TESTDATA');
		
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
		
		$m = new InjectStack(array($middleware), $endpoint);
		
		$r = $m->run('TESTDATA');
		
		$this->assertEquals('TESTDATAHANDLED', $r);
	}
}


/* End of file InjectStackTest.php */
/* Location: src/tests/unit-tests/php/InjectStack */