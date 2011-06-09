<?php

use \Inject\ClassTools\Autoloader\Generic as Autoloader;
use \InjectStack\Adapter\Generic as ServerAdapter;
use \InjectStack\InjectStack;
use \InjectStack\CascadeEndpoint;
use \InjectStack\Middleware;

set_include_path('.'.PATH_SEPARATOR.'../src/php'.PATH_SEPARATOR.get_include_path());

include 'Inject/ClassTools/Autoloader/Generic.php';

$loader = new Autoloader();
$loader->register();

$endpoint = new CascadeEndpoint(array(
	function($env)
	{
		if(stripos($env['PATH_INFO'], '/test') !== 0)
		{
			return array(404, array('X-Cascade' => 'pass'), '');
		}
		
		return array(200, array('Content-Type' => 'text/plain'), 'This is a plain text response from the /test uri');
	},
	function($env)
	{
		if(stripos($env['PATH_INFO'], '/dump_env') !== 0)
		{
			return array(404, array('X-Cascade' => 'pass'), '');
		}
		
		return array(200, array('Content-Type' => 'text/plain'), print_r($env, true));
	},
	function($env)
	{
		return array(200, array('Content-Type' => 'text/plain'), 'Hello world!');
	}
));

$app = new InjectStack(array(
	new Middleware\RunTimer(),
), $endpoint);

$adapter = new ServerAdapter();

$adapter->run($app);