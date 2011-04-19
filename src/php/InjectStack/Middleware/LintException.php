<?php
/*
 * Created by Martin Wernståhl on 2011-04-10.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace InjectStack\Middleware;

class LintException extends \RuntimeException
{
	function __construct($message)
	{
		// TODO: Error code:
		parent::__construct('MiddlewareLint: '.$message);
	}
}

/* End of file LintException.php */
/* Location: src/php/InjectStack/Middleware */