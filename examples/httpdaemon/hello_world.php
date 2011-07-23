<?php


// ## Class loading ##

// We need a PSR-0 compliant autoloader to load Inject\Stack,
use \Inject\ClassTools\Autoloader\Generic as Autoloader;

// Add the project's source dir to the include path, in case we haven't been installed locally
set_include_path('../../src/php'.PATH_SEPARATOR.get_include_path());

// Include the autoloader and register it
include 'Inject/ClassTools/Autoloader/Generic.php';
$loader = new Autoloader();
$loader->register();


// ## Hello World Example ##

// Instantiate the server adapter
$adapter = new \Inject\Stack\Adapter\HTTPSocket(array('SERVER_PORT' => 8080));

// Per-thread application init function:
$app_init = function()
{
	// Create the hello world "application":
	return function($env)
	{
		// Retreive name from GET parameters, if present, defaults to "World"
		$name = empty($env['inject.get']['name']) ? 'World' : $env['inject.get']['name'];
		$message = 'Greetings from '.getmypid().', '.$name.'!';
		
		// Return a 200 success text response
		return array(200, array('Content-Type' => 'text/plain'), $message);
	};
};

// Run the application with the default number of workers!
$adapter->serve($app_init);
