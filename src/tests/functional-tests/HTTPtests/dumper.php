<?php

return function($env)
{
	ob_start();
	
	var_dump($env);
	
	return array(200, array('Content-Type' => 'text/plain'), ob_get_clean());
};