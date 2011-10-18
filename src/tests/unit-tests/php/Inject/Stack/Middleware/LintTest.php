<?php
/*
 * Created by Martin Wernståhl on 2011-03-06.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace Inject\Stack\Middleware;

use \stdClass;
use \ArrayObject;
use \Inject\Stack\Middleware\LintException;
use \Inject\Stack\MiddlewareInterface;

/**
 * @covers Inject\Stack\Middleware\Lint
 */
class LintTest extends \PHPUnit_Framework_TestCase
{
	public function setUp()
	{
		if( ! class_exists('Inject\Stack\Middleware\LintTestStrObj'))
		{
			eval('namespace Inject\Stack\Middleware;

class LintTestStrObj
{
	protected $str = \'\';
	public function __construct($str) { $this->str = $str; }
	public function __toString() { return $this->str; }
}');
		}
	}
	
	/**
	 * Basic stream instance, shared, DO NOT CLOSE.
	 */
	protected static $basic_stream = null;
	
	public static function basicEnvProvider()
	{
		empty(static::$basic_stream) && static::$basic_stream = fopen('php://temp', 'r');
		
		return array(
				'REQUEST_METHOD' => 'GET',
				'SCRIPT_NAME' => '',
				'PATH_INFO' => '/',
				'BASE_URI' => '',
				'QUERY_STRING' => '',
				'SERVER_NAME' => 'localhost',
				'SERVER_PORT' => 80,
				'REMOTE_ADDR' => '127.0.0.1',
				'HTTP_HOST' => 'localhost',
				'inject.version' => \Inject\Stack\Builder::VERSION,
				'inject.url_scheme' => 'http',
				'inject.adapter' => __CLASS__,
				'inject.get' => array(),
				'inject.post' => array(),
				'inject.input' => static::$basic_stream
			);
	}
	/**
	 * Returns a list of environment arrays each missing a required key.
	 * 
	 * @return array
	 */
	public static function envsWMissingKeyProvider()
	{
		$ret = array();
		
		foreach(array_keys(self::basicEnvProvider()) as $key)
		{
			$arr = self::basicEnvProvider();
			unset($arr[$key]);
			
			$ret[] = $arr;
		}
		
		return $ret;
	}
	
	public function testInstantiate()
	{
		$obj = new Lint();
		
		$this->assertTrue($obj instanceof MiddlewareInterface);
	}
	public function testBasicRun()
	{
		$obj = new Lint();
		$obj->setNext(function()
		{
			// Simple response:
			return array(200, array('Content-Type' => 'test/plain'), 'data');
		});
		
		$ret = $obj(self::basicEnvProvider());
		
		$this->assertEquals(200, $ret[0]);
		$this->assertEquals(array('Content-Type' => 'test/plain'), $ret[1]);
		$this->assertEquals('data', $ret[2]);
	}
	public function testMissingRequiredKey()
	{
		foreach(self::envsWMissingKeyProvider() as $env)
		{
			try
			{
				$obj = new Lint();
				$obj->setNext(function()
				{
					// Simple response, should never be called
					return array(200, array(), 'data');
				});
				
				$obj($env);
			}
			catch(\Exception $e)
			{
				if($e instanceof LintException)
				{
					continue;
				}
			}
			
			$this->fail('Did not raise an exception on '.print_r($env, true));
		}
	}
	/**
	 * @expectedException \Inject\Stack\Middleware\LintException
	 */
	public function testFaultyRequestMethod()
	{
		$obj = new Lint();
		$obj->setNext(function()
		{
			// Simple response:
			return array(200, array('Content-Type' => 'test/plain'), 'data');
		});
		
		$env = self::basicEnvProvider();
		
		$env['REQUEST_METHOD'] = '´sd´´sfd3235tw´';
		
		$obj($env);
	}
	/**
	 * @expectedException \Inject\Stack\Middleware\LintException
	 */
	public function testFaultyScriptName()
	{
		$obj = new Lint();
		$obj->setNext(function()
		{
			// Simple response:
			return array(200, array('Content-Type' => 'test/plain'), 'data');
		});
		
		$env = self::basicEnvProvider();
		
		$env['SCRIPT_NAME'] = '/';
		
		$obj($env);
	}
	/**
	 * @expectedException \Inject\Stack\Middleware\LintException
	 */
	public function testFaultyScriptName2()
	{
		$obj = new Lint();
		$obj->setNext(function()
		{
			// Simple response:
			return array(200, array('Content-Type' => 'test/plain'), 'data');
		});
		
		$env = self::basicEnvProvider();
		
		$env['PATH_INFO'] = '';
		$env['SCRIPT_NAME'] = '/';
		
		$obj($env);
	}
	/**
	 * @expectedException \Inject\Stack\Middleware\LintException
	 */
	public function testNonArray()
	{
		$obj = new Lint();
		
		$obj(null);
	}
	/**
	 * @expectedException \Inject\Stack\Middleware\LintException
	 */
	public function testNonArray2()
	{
		$obj = new Lint();
		
		$obj(true);
	}
	/**
	 * @expectedException \Inject\Stack\Middleware\LintException
	 */
	public function testNonArray3()
	{
		$obj = new Lint();
		
		$obj('teststring');
	}
	/**
	 * @expectedException \Inject\Stack\Middleware\LintException
	 */
	public function testNonArray4()
	{
		$obj = new Lint();
		
		$obj(new stdClass);
	}
	public function testArrayObject()
	{
		$obj = new Lint();
		$obj->setNext(function($env)
		{
			return array(200, array('Content-Type' => 'text/plain',
				'array' => $env instanceof ArrayObject ? 'object' : 'no'), 'testbody');
		});
		
		$env = new ArrayObject(self::basicEnvProvider());
		
		$ret = $obj($env);
		
		$this->assertEquals(200, $ret[0]);
		$this->assertEquals(array('Content-Type' => 'text/plain', 'array' => 'object'), $ret[1]);
		$this->assertEquals('testbody', $ret[2]);
	}
	/**
	 * @expectedException \Inject\Stack\Middleware\LintException
	 */
	public function testContentLength()
	{
		$obj = new Lint();
		$obj->setNext(function()
		{
			return array(200, array('Content-Type' => 'test'), 'fail');
		});
		
		$env = self::basicEnvProvider();
		
		$env['CONTENT_LENGTH'] = 'lol';
		
		$obj($env);
	}
	/**
	 * @expectedException \Inject\Stack\Middleware\LintException
	 */
	public function testContentLength2()
	{
		$obj = new Lint();
		$obj->setNext(function()
		{
			return array(200, array('Content-Type' => 'test'), 'fail');
		});
		
		$env = self::basicEnvProvider();
		
		$env['HTTP_CONTENT_LENGTH'] = 234;
		
		$obj($env);
	}
	public function testContentLength3()
	{
		$obj = new Lint();
		$obj->setNext(function()
		{
			return array(200, array('Content-Type' => 'test'), 'fail');
		});
		
		$env = self::basicEnvProvider();
		
		$env['CONTENT_LENGTH'] = 234;
		
		$obj($env);
	}
	/**
	 * @expectedException \Inject\Stack\Middleware\LintException
	 */
	public function testHttpContentType()
	{
		$obj = new Lint();
		$obj->setNext(function()
		{
			return array(200, array('Content-Type' => 'test'), 'fail');
		});
		
		$env = self::basicEnvProvider();
		
		$env['HTTP_CONTENT_TYPE'] = 'text/html';
		
		$obj($env);
	}
	/**
	 * @expectedException \Inject\Stack\Middleware\LintException
	 */
	public function testCustomEnvData()
	{
		$obj = new Lint();
		$obj->setNext(function()
		{
			return array(200, array('Content-Type' => 'test'), 'fail');
		});
		
		$env = self::basicEnvProvider();
		
		$env['anobj'] = new stdClass();
		
		$obj($env);
	}
	/**
	 * @expectedException \Inject\Stack\Middleware\LintException
	 */
	public function testCustomEnvData2()
	{
		$obj = new Lint();
		$obj->setNext(function()
		{
			return array(200, array('Content-Type' => 'test'), 'fail');
		});
		
		$env = self::basicEnvProvider();
		
		$env['anarray'] = array();
		
		$obj($env);
	}
	public function testCustomEnvData3()
	{
		$obj = new Lint();
		$obj->setNext(function()
		{
			return array(200, array('Content-Type' => 'test'), 'ok');
		});
		
		$env = self::basicEnvProvider();
		
		$env['an.obj'] = new stdClass();
		
		$ret = $obj($env);
		
		$this->assertEquals(200, $ret[0]);
		$this->assertEquals(array('Content-Type' => 'test'), $ret[1]);
		$this->assertEquals('ok', $ret[2]);
	}
	public function testCustomEnvData4()
	{
		$obj = new Lint();
		$obj->setNext(function()
		{
			return array(200, array('Content-Type' => 'test'), 'ok');
		});
		
		$env = self::basicEnvProvider();
		
		$env['an.array'] = array();
		
		$ret = $obj($env);
		
		$this->assertEquals(200, $ret[0]);
		$this->assertEquals(array('Content-Type' => 'test'), $ret[1]);
		$this->assertEquals('ok', $ret[2]);
	}
	public function testReturnArrayObject()
	{
		$obj = new Lint();
		$obj->setNext(function()
		{
			return new ArrayObject(array(200, array('Content-Type' => 'test'), 'ok'));
		});
		
		$ret = $obj(self::basicEnvProvider());
		
		$this->assertEquals(200, $ret[0]);
		$this->assertEquals(array('Content-Type' => 'test'), $ret[1]);
		$this->assertEquals('ok', $ret[2]);
	}
	public function testReturnArrayObject2()
	{
		$obj = new Lint();
		$obj->setNext(function()
		{
			return new ArrayObject(array(200, new ArrayObject(array('Content-Type' => 'test')), 'ok'));
		});
		
		$ret = $obj(self::basicEnvProvider());
		
		$this->assertEquals(200, $ret[0]);
		$this->assertInstanceOf('ArrayObject', $ret[1]);
		$this->assertEquals(array('Content-Type' => 'test'), $ret[1]->getArrayCopy());
		$this->assertEquals('ok', $ret[2]);
	}
	/**
	 * @expectedException \Inject\Stack\Middleware\LintException
	 */
	public function testReturnError()
	{
		$obj = new Lint();
		$obj->setNext(function()
		{
			return array('lolstr', array('Content-Type' => 'test'), 'fail');
		});
		
		$obj(self::basicEnvProvider());
	}
	public function testReturnError2()
	{
		$obj = new Lint();
		$obj->setNext(function()
		{
			return array('200', array('Content-Type' => 'test'), 'fail');
		});
		
		$obj(self::basicEnvProvider());
	}
	/**
	 * @expectedException \Inject\Stack\Middleware\LintException
	 */
	public function testReturnError3()
	{
		$obj = new Lint();
		$obj->setNext(function()
		{
			return array(99, array('Content-Type' => 'test'), 'fail');
		});
		
		$obj(self::basicEnvProvider());
	}
	/**
	 * @expectedException \Inject\Stack\Middleware\LintException
	 */
	public function testFaultyHeader()
	{
		$obj = new Lint();
		$obj->setNext(function()
		{
			return array(200, array('_Content-Type' => 'test'), 'fail');
		});
		
		$obj(self::basicEnvProvider());
	}
	/**
	 * @expectedException \Inject\Stack\Middleware\LintException
	 */
	public function testFaultyHeader2()
	{
		$obj = new Lint();
		$obj->setNext(function()
		{
			return array(200, array('Content-Type' => "test\0"), 'fail');
		});
		
		$obj(self::basicEnvProvider());
	}
	/**
	 * @expectedException \Inject\Stack\Middleware\LintException
	 */
	public function testNoContentTypeHeader()
	{
		$obj = new Lint();
		$obj->setNext(function()
		{
			return array(100, array('Content-Type' => "test"), 'fail');
		});
		
		$obj(self::basicEnvProvider());
	}
	/**
	 * @expectedException \Inject\Stack\Middleware\LintException
	 */
	public function testNoContentTypeHeader2()
	{
		$obj = new Lint();
		$obj->setNext(function()
		{
			return array(204, array('Content-Type' => "test"), 'fail');
		});
		
		$obj(self::basicEnvProvider());
	}
	/**
	 * @expectedException \Inject\Stack\Middleware\LintException
	 */
	public function testNoContentTypeHeader4()
	{
		$obj = new Lint();
		$obj->setNext(function()
		{
			return array(304, array('Content-Type' => "test"), 'fail');
		});
		
		$obj(self::basicEnvProvider());
	}
	/**
	 * @expectedException \Inject\Stack\Middleware\LintException
	 */
	public function testNoContentLengthHeader()
	{
		$obj = new Lint();
		$obj->setNext(function()
		{
			return array(100, array('Content-Length' => 4), 'fail');
		});
		
		$obj(self::basicEnvProvider());
	}
	/**
	 * @expectedException \Inject\Stack\Middleware\LintException
	 */
	public function testNoContentLengthHeader2()
	{
		$obj = new Lint();
		$obj->setNext(function()
		{
			return array(204, array('Content-Length' => 4), 'fail');
		});
		
		$obj(self::basicEnvProvider());
	}
	/**
	 * @expectedException \Inject\Stack\Middleware\LintException
	 */
	public function testNoContentLengthHeader4()
	{
		$obj = new Lint();
		$obj->setNext(function()
		{
			return array(304, array('Content-Length' => 4), 'fail');
		});
		
		$obj(self::basicEnvProvider());
	}
	/**
	 * @expectedException \Inject\Stack\Middleware\LintException
	 */
	public function testContentLengthDisparity()
	{
		$obj = new Lint();
		$obj->setNext(function()
		{
			return array(200, array('Content-Type' => 'test', 'Content-Length' => 5), 'fail');
		});
		
		$obj(self::basicEnvProvider());
	}
	public function testReturnStream()
	{
		$stream = fopen(__FILE__, 'r');
		
		$obj = new Lint();
		$obj->setNext(function() use($stream)
		{
			return array(200, array('Content-Type' => 'test'), $stream);
		});
		
		$ret = $obj(self::basicEnvProvider());
		
		$this->assertEquals(200, $ret[0]);
		$this->assertEquals(array('Content-Type' => 'test'), $ret[1]);
		$this->assertSame($stream, $ret[2]);
	}
	public function testReturnStrObj()
	{
		$strobj = new LintTestStrObj('this is a string');
		
		$obj = new Lint();
		$obj->setNext(function() use($strobj)
		{
			return array(200, array('Content-Type' => 'test'), $strobj);
		});
		
		$ret = $obj(self::basicEnvProvider());
		
		$this->assertEquals(200, $ret[0]);
		$this->assertEquals(array('Content-Type' => 'test'), $ret[1]);
		$this->assertSame($strobj, $ret[2]);
		$this->assertEquals('this is a string', (String) $ret[2]);
	}
}


/* End of file LintTest.php */
/* Location: src/tests/unit-tests/php/Inject/Stack/Middleware */