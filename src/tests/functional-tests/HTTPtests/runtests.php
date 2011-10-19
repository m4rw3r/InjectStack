<?php

include 'testclass.php';

// Server type to start
Test::$test_bootstrap = 'bootHTTPSocket.php';

test('basic')
	->input("GET /index.html HTTP/1.1\r\nHost: localhost\r\n\r\n")
	->expect(function($in){ return preg_match('/^HTTP\/1.1 200 OK\r\n/', $in); })
	->run();

test('basic no headers')
	->input("GET /index.html HTTP/1.1\r\n\r\n")
	->expect("HTTP/1.1 400 Bad Request\r\nContent-Length: 0\r\n\r\n")
	->run();

test('nothing but newlines')
	->input("\r\n\r\n")
	->expect("HTTP/1.1 400 Bad Request\r\nContent-Length: 0\r\n\r\n")
	->run();

test('only request line')
	->input("GET /test HTTP/1.1\r\n\r\n")
	->expect("HTTP/1.1 400 Bad Request\r\nContent-Length: 0\r\n\r\n")
	->run();

test('faulty request line')
	->input("GETFOO\r\nHost: localhost\r\n\r\n")
	->expect("HTTP/1.1 400 Bad Request\r\nContent-Length: 0\r\n\r\n")
	->run();

test('unsupported HTTP verb')
	->input("LOL /test HTTP/1.1\r\nHost: localhost\r\n\r\n")
	->expect("HTTP/1.1 501 Not Implemented\r\nContent-Length: 0\r\n\r\n")
	->run();

test('header missing :-separator')
	->input("GET /test HTTP/1.1\r\nHost: localhost\r\nLOLHeader\r\n\r\n")
	->expect("HTTP/1.1 400 Bad Request\r\nContent-Length: 0\r\n\r\n")
	->run();

test('no host')
	->input("GET foo.txt HTTP/1.1\r\nAccept: text/plain\r\n\r\n")
	->expect("HTTP/1.1 400 Bad Request\r\nContent-Length: 0\r\n\r\n")
	->run();

test('wrong HTTP version')
	->input("GET foo.txt HTTP/1.0\r\nHost: localhost\r\n\r\n")
	->expect("HTTP/1.1 505 HTTP Version Not Supported\r\nContent-Length: 0\r\n\r\n")
	->run();



test('nothing')
	->input('')
	// Expects a timeout, so the http response will be false:
	->expect(function($in){ return $in === false; })
	->run();















