<?php


// ## Class loading ##

// We need a PSR-0 compliant autoloader to load Inject\Stack,
use \Inject\ClassTools\Autoloader\Generic as Autoloader;

// and an adapter to interact with the web server
use \Inject\Stack\Adapter\Generic as ServerAdapter;

// Add the project's source dir to the include path, in case we haven't been installed locally
set_include_path('../src/php'.PATH_SEPARATOR.get_include_path());

// Include the autoloader and register it
include 'Inject/ClassTools/Autoloader/Generic.php';
$loader = new Autoloader();
$loader->register();


// ## Hello World Example ##

// Instantiate the server adapter
$adapter = new ServerAdapter();

// Create the hello world "application"
$hello_world = function($env)
{
	// Retreive name from GET parameters, if present, defaults to "World"
	$name = empty($env['inject.get']['name']) ? 'World' : $env['inject.get']['name'];
	$message = 'Hello '.$name.'!';
	
	// Return a 200 success text response
	return array(200, array('Content-Type' => 'text/plain'), $message);
};

// Run the application!
$adapter->run($hello_world);
