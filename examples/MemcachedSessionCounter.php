<?php

// We need Memcached extension to store session:
use \Memcached;

// Also a PSR-0 compliant autoloader:
use \Inject\ClassTools\Autoloader\Generic as Autoloader;

// Include classes we're going to use and the Middleware namespace
use \InjectStack\Builder;
use \InjectStack\Middleware;
use \InjectStack\Adapter\Generic as ServerAdapter;

// Set include path for the autoloader, so we can load stuff even if we haven't been installed by PEAR
set_include_path(realpath(__DIR__.'/../src/php').PATH_SEPARATOR.get_include_path());

// Include the autoloader and instantiate it
require 'Inject/ClassTools/Autoloader/Generic.php';

$loader = new Autoloader();
$loader->register();

// Initialize Memcached to use with session middleware.
$cache = new Memcached;
$cache->addServer('127.0.0.1', 11211);

// Create a stack with middleware ending in an application:
$stack = new Builder(
	// Array of middleware, ordered with the first layer first:
	array(
		// Time the run of the whole application:
		new Middleware\RunTimer(),
		// Show exceptions nicely if we encounter any:
		// (Session can throw if there is an error connecting to Memcached)
		new Middleware\ShowException(),
		// The Session middleware which adds a session object to $env['inject.session']
		new Middleware\Session(
			// Memcached storage adapter:
			new Middleware\Session\MemcachedStorage($cache),
			// Cookie ID reader:
			new Middleware\Session\CookieIdHandler
		),
		// Session invalidator, invalidates session if user changes IP or similar
		new Middleware\SessionInvalidator()
	),
	// The application itself:
	function($env)
	{
		// Check if we have an entry in the session, if not fix one:
		isset($env['inject.session']['count']) OR $env['inject.session']['count'] = 0;
		
		// Increase the count for the user:
		$env['inject.session']['count']++;
		
		// Construct response:
		$message  = 'Hello '.$env['inject.session']->getId()."!\n";
		$message .= 'You have accessed this '.$env['inject.session']['count']." times.\n";
		$message .= print_r($env['inject.session']->getArrayCopy(), true);
		
		// Return 200 text response:
		return array(200, array('Content-Type' => 'text/plain'), $message);
	}
);

// Create the adapter and run the application!
$adapter = new ServerAdapter();
$adapter->run($stack);
