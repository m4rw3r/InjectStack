<?php
/*
 * Created by Martin Wernståhl on 2011-03-06.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace InjectStack;

/**
 * Exception telling the user that an endpoint is missing on a InjectStack.
 */
class NoEndpointException extends \RuntimeException
{
	function __construct()
	{
		// TODO: Error code:
		parent::__construct('Missing endpoint in InjectStack.');
	}
}

/* End of file NoEndpointException.php */
/* Location: src/php/InjectStack */