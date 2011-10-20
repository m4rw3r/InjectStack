<?php

include 'testclass.php';

// Server type to start
Test::$test_bootstrap = 'bootHTTPSocket.php';

test('Basic')
	->input("GET /index.html HTTP/1.1\r\nHost: localhost\r\n\r\n")
	->expect(function($in){ return preg_match('/^HTTP\/1.1 200 OK\r\n/', $in); })
	->run();

test('Basic, no headers')
	->input("GET /index.html HTTP/1.1\r\n\r\n")
	->expect("HTTP/1.1 400 Bad Request\r\nContent-Length: 0\r\n\r\n")
	->run();

test('Nothing but newlines')
	->input("\r\n\r\n")
	->expect("HTTP/1.1 400 Bad Request\r\nContent-Length: 0\r\n\r\n")
	->run();

test('Only request line')
	->input("GET /test HTTP/1.1\r\n\r\n")
	->expect("HTTP/1.1 400 Bad Request\r\nContent-Length: 0\r\n\r\n")
	->run();

test('Faulty request line')
	->input("GETFOO\r\nHost: localhost\r\n\r\n")
	->expect("HTTP/1.1 400 Bad Request\r\nContent-Length: 0\r\n\r\n")
	->run();

test('Unsupported HTTP verb')
	->input("LOL /test HTTP/1.1\r\nHost: localhost\r\n\r\n")
	->expect("HTTP/1.1 501 Not Implemented\r\nContent-Length: 0\r\n\r\n")
	->run();

test('Header missing a ":"-separator')
	->input("GET /test HTTP/1.1\r\nHost: localhost\r\nLOLHeader\r\n\r\n")
	->expect("HTTP/1.1 400 Bad Request\r\nContent-Length: 0\r\n\r\n")
	->run();

test('No Host Header')
	->input("GET foo.txt HTTP/1.1\r\nAccept: text/plain\r\n\r\n")
	->expect("HTTP/1.1 400 Bad Request\r\nContent-Length: 0\r\n\r\n")
	->run();

test('Wrong HTTP version')
	->input("GET foo.txt HTTP/1.0\r\nHost: localhost\r\n\r\n")
	->expect("HTTP/1.1 505 HTTP Version Not Supported\r\nContent-Length: 0\r\n\r\n")
	->run();

$str = array("GET teststring HTTP/1.1\r\nHost: localhost");
$str[] = 'test:'.str_repeat('foobar', 1000);

test('Too large HTTP Header')
	->input(implode("\r\n", $str)."\r\n\r\n")
	->expect("HTTP/1.1 414 Request-URI Too Long\r\nContent-Length: 0\r\n\r\n")
	->run();

$str = array("GET teststring HTTP/1.1\r\nHost: localhost");

for($i = 0; $i < 500; $i++)
{
	$str[] = "Header$i: $i";
}

test('Too many HTTP Headers')
	->input(implode("\r\n", $str)."\r\n\r\n")
	->expect("HTTP/1.1 414 Request-URI Too Long\r\nContent-Length: 0\r\n\r\n")
	->run();



test('Empty string')
	->input('')
	// Expects a timeout, so the http response will be false, but still content in the output:
	->expect(function($in){ return $in === false; })
	->run();















