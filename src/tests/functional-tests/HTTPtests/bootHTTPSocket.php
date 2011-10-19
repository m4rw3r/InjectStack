<?php

set_include_path('../../../../src/php'.PATH_SEPARATOR.get_include_path());

include 'Inject/ClassTools/Autoloader/Generic.php';

$loader = new \Inject\ClassTools\Autoloader\Generic();
$loader->register();

file_put_contents('pidfile', getmypid());

$adapter = new \Inject\Stack\Adapter\HTTPSocket(array('SERVER_PORT' => 8088));

$app = function()
{
	return include getenv('APPFILE');
};

$adapter->serve($app, 1);
