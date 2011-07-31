<?php

// This is a simple application which will count how many times a user has accessed
// the application with the use of cookies and Memcached storage.

// ## Bootstrap ##

// We need Memcached extension to store session data, as well as an autoloader,
// stack-builder, server-adapter and a bunch of middleware:
use \Memcached;

use \Inject\ClassTools\Autoloader\Generic as Autoloader;

use \Inject\Stack\Builder;
use \Inject\Stack\Adapter\Generic as ServerAdapter;
use \Inject\Stack\Middleware;

// Include the autoloader, make it load from the project's `src`-folder and instantiate it:
require 'Inject/ClassTools/Autoloader/Generic.php';

set_include_path(realpath(__DIR__.'/../src/php').PATH_SEPARATOR.get_include_path());

$loader = new Autoloader();
$loader->register();

// Initialize Memcached to use with session middleware.
$cache = new Memcached();
$cache->addServer('127.0.0.1', 11211);


// ## Stack Builder ##

// The `Inject\Stack\Builder` is a helper class which will assemble
// a bunch of middleware and an endpoint into a runnable stack conforming
// to Inject\Stack specifications.

// Syntax is `new Builder` followed by an array of instantiated middleware and
// then an endpoint.
$stack = new Builder(
	// Array of middleware, ordered with the first layer first:
	array(
		// Time the run of the whole application and save it in the HTTP header
		// `X-Runtime`:
		new Middleware\RunTimer(),
		// Show exceptions nicely if we encounter any
		// (`Session` can throw if there is an error connecting to Memcached):
		new Middleware\ShowException(),
		// The Session middleware which adds a session object to $env['inject.session']:
		new Middleware\Session(
			// Memcached storage adapter:
			new Middleware\Session\MemcachedStorage($cache),
			// Normal cookie ID reader:
			new Middleware\Session\CookieIdHandler()
		),
		// Session invalidator, invalidates session if user changes IP or similar
		// (see PHPdoc for more information):
		new Middleware\SessionInvalidator()
	),
	
	// ## The Application ##
	function($env)
	{
		// Check if we have a `count` entry in the session, if not fix one to zero:
		isset($env['inject.session']['count']) OR $env['inject.session']['count'] = 0;
		
		// Increase the count for the user:
		$env['inject.session']['count']++;
		
		// Construct response:
		$message  = 'Hello '.$env['inject.session']->getId()."!\n";
		$message .= 'You have accessed this '.$env['inject.session']['count']." times.\n";
		// We also dump the contents of the Session array:
		$message .= print_r($env['inject.session']->getArrayCopy(), true);
		
		// Return 200 text response:
		return array(200, array('Content-Type' => 'text/plain'), $message);
	}
);


// ## Run Application ##
$adapter = new ServerAdapter();
$adapter->run($stack);
