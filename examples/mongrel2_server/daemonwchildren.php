<?php

// ## Global Preparations ##

set_include_path('../../src/php'.PATH_SEPARATOR.get_include_path());
error_reporting(E_ALL | E_STRICT | E_DEPRECATED);
setlocale(LC_CTYPE, 'C');

// Include and register autoloader:
require 'Inject/ClassTools/Autoloader/Generic.php';
$loader = new \Inject\ClassTools\Autoloader\Generic();
$loader->register();


// ## Application Init ##
$app_init = function()
{
	// Create and return our application here, will be run for each child process
	
	// ## Applicaiton ##
	$app = function($env)
	{
		return array(200, array('Content-Type' => 'text/plain'), 'Process id: '.getmypid());
	};
	
	return $app;
};


// Create a new Mongrel2 adapter with supplied UUID and connections,
// other config options are default, except debug = true
$adapter = new \InjectStack\Adapter\Mongrel2(
	'B2D9FFB2-4DF9-4430-8E07-93F342009FE9',
	'tcp://127.0.0.1:9989',
	'tcp://127.0.0.1:9988',
	array(), false);


// Spawn it with 3 worker processes
$adapter->serve($app_init, 3);