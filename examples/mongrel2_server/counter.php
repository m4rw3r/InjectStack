<?php

// ## Preparations ##

set_include_path('../../src/php'.PATH_SEPARATOR.get_include_path());
error_reporting(E_ALL | E_STRICT | E_DEPRECATED);
setlocale(LC_CTYPE, 'C');

// Include and register autoloader:
require 'Inject/ClassTools/Autoloader/Generic.php';
$loader = new \Inject\ClassTools\Autoloader\Generic();
$loader->register();


// ## Counter Application ##

// Counter application
$app = function($env)
{
	// Because PHP does not restart between requests, this $count variable
	// will be persisted across different requests, as long as they reach
	// the same PHP process if there are more than one running.
	static $count = array();
	
	// Make sure we have an entry for this path
	isset($count[$env['PATH_INFO']]) OR $count[$env['PATH_INFO']] = 0;
	
	// Create the counter message and increment the counter:
	$message = 'Counter for '.$env['PATH_INFO'].': '.$count[$env['PATH_INFO']]++;
	
	// Return a plain-text message
	return array(200, array('Content-Type' => 'text/html'), $message);
};

// Create a stack builder
$stack = new \InjectStack\Builder(
	array(new \InjectStack\Middleware\RunTimer()),
	$app
);

// Create a new Mongrel2 adapter with supplied UUID and connections,
// other config options are default, except debug = true
$adapter = new \InjectStack\Adapter\Mongrel2(
	'B2D9FFB2-4DF9-4430-8E07-93F342009FE9',
	'tcp://127.0.0.1:9989',
	'tcp://127.0.0.1:9988',
	array(), true);

// Build the stack and run the adapter
$adapter->run($stack->build());