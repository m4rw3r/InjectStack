<?php
/*
 * Created by Martin Wernståhl on 2011-04-11.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace InjectStack;

/**
 * Interface for framework adapters, a framework adapter is responsible for
 * handing the main request over to the middleware stack and displaying the return value.
 */
interface AdapterInterface
{
	/**
	 * Will run the supplied application, fetching the appropriate data to construct
	 * the $env variable which it then sends to the app.
	 * 
	 * The Adapter will also handle outputting of the return value from the stack
	 * in an appropriate way.
	 * 
	 * @param  \InjectStack\Builder|Closure|ObjectImplementing__invoke
	 * @return void
	 */
	public static function run($app);
}


/* End of file AdapterInterface.php */
/* Location: src/php/InjectStack */