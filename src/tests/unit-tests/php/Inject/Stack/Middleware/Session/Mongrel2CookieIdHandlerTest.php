<?php
/*
 * Created by Martin Wernståhl on 2011-05-27.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace Inject\Stack\Middleware\Session;

/**
 * @covers Inject\Stack\Middleware\Session\Mongrel2CookieIdHandler
 */
class Mongrel2CookieIdHandlerTest extends \PHPUnit_Framework_TestCase
{
	public function testInstantiate()
	{
		$handler = new Mongrel2CookieIdHandler();
		
		$this->assertTrue($handler instanceof Mongrel2CookieIdHandler);
	}
	public function testReadCookie1()
	{
		$handler = new Mongrel2CookieIdHandler();
		
		$this->assertEquals(false, $handler->fetchUserId(array()));
	}
	public function testReadCookie2()
	{
		$handler = new Mongrel2CookieIdHandler();
		
		$this->assertEquals(false, $handler->fetchUserId(array('HTTP_COOKIE' => '')));
	}
	public function testReadCookie3()
	{
		$handler = new Mongrel2CookieIdHandler();
		
		$this->assertEquals(false, $handler->fetchUserId(array('HTTP_COOKIE' => 'failedcookie=lol')));
	}
	public function testReadCookie4()
	{
		$handler = new Mongrel2CookieIdHandler();
		
		$this->assertEquals(false, $handler->fetchUserId(array('HTTP_COOKIE' => 'injectfw=thisvalue')));
	}
	public function testReadCookie5()
	{
		$handler = new Mongrel2CookieIdHandler();
		
		$this->assertEquals(false, $handler->fetchUserId(array('HTTP_COOKIE' => 'lol=InjectFw')));
	}
	public function testReadCookie6()
	{
		$handler = new Mongrel2CookieIdHandler();
		
		$this->assertEquals(false, $handler->fetchUserId(array('HTTP_COOKIE' => 'lol=InjectFw=foo')));
	}
	public function testReadCookie7()
	{
		$handler = new Mongrel2CookieIdHandler();
		
		$this->assertEquals('2', $handler->fetchUserId(array('HTTP_COOKIE' => 'lol=InjectFw;InjectFw=2')));
	}
	public function testReadCookie8()
	{
		$handler = new Mongrel2CookieIdHandler();
		
		$this->assertEquals('lol', $handler->fetchUserId(array('HTTP_COOKIE' => 'InjectFw=lol')));
	}
	public function testReadCookie9()
	{
		$handler = new Mongrel2CookieIdHandler();
		
		$this->assertEquals('foobar', $handler->fetchUserId(array('HTTP_COOKIE' => 'lol=InjectFw;InjectFw=foobar; test=another')));
	}
	public function testReadCookie10()
	{
		$handler = new Mongrel2CookieIdHandler();
		
		$this->assertEquals('thisvalue', $handler->fetchUserId(array('HTTP_COOKIE' => 'InjectFw=thisvalue')));
	}
	public function testDefaultCookieOptions()
	{
		$handler = new Mongrel2CookieIdHandler();
		
		$this->assertEquals(array(200, array('a' => 'header', 'Set-Cookie' => 'InjectFw=FoobarId'), 'empty_text'), $handler->storeUserId('FoobarId', array(200, array('a' => 'header'), 'empty_text')));
	}
	
	public function testCookieName()
	{
		$handler = new Mongrel2CookieIdHandler('FooName');
		
		$this->assertEquals(array(200, array('a' => 'header', 'Set-Cookie' => 'FooName=FoobarId'), 'empty_text'), $handler->storeUserId('FoobarId', array(200, array('a' => 'header'), 'empty_text')));
	}
	
	public function testCookieExpires()
	{
		$handler = new Mongrel2CookieIdHandler('FooName', time() + 300);
		
		$this->assertEquals(array(200, array('a' => 'header', 'Set-Cookie' => 'FooName=FoobarId; Expires='.date(DATE_RFC822, time() + 300)), 'empty_text'), $handler->storeUserId('FoobarId', array(200, array('a' => 'header'), 'empty_text')));
	}
}


/* End of file BucketTest.php */
/* Location: src/tests/unit-tests/php/Inject/Stack/Middleware/Session */