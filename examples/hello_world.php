<?php
// This is a simple Hello-World application written for Inject\Stack.
// It will demonstrate basic bootstrapping and how to tie a simple
// PHP Closure as the application.

// ## Class loading ##

// We need a PSR-0 compliant autoloader to load Inject\Stack:
use \Inject\ClassTools\Autoloader\Generic as Autoloader;

// And an adapter to interact with the web server:
// 
// This `Generic` adapter is meant to be run under a CGI-like environment,
// like MOD_PHP or PHP-FPM.
use \Inject\Stack\Adapter\Generic as ServerAdapter;
// Add the project's source dir to the include path, in case we haven't been installed locally:
set_include_path('../src/php'.PATH_SEPARATOR.get_include_path());
// Include the autoloader and register it:
include 'Inject/ClassTools/Autoloader/Generic.php';

$loader = new Autoloader();
$loader->register();
// ## Hello World Application ##

// Create the hello world application as a PHP Closure taking the
// Env-hash with request parameters.
// This is a configurable Hello-World application where you can specify
// the name to greet through the Query-String parameter `name`, otherwise
// we could have skipped the `$env` parameter
$hello_world = function($env)
{
	// Retreive name from GET parameters, if present, defaults to "World"
	$name = empty($env['inject.get']['name']) ? 'World' : $env['inject.get']['name'];
	// Create the Response message:
	$message = 'Hello '.$name.'!';
	// Return a 200 success text response:
	return array(200, array('Content-Type' => 'text/plain'), $message);
};
// ## Run The Application ##

// Instantiate the server adapter to handle the request and convert itto an
// Inject\Stack compatible Environment hash.
// It is also responsible for sending the response to the browser.
$adapter = new ServerAdapter();
// Run the application!
$adapter->run($hello_world);
