<?php
/*
 * Created by Martin Wernståhl on 2011-03-06.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace Inject\Stack\Builder;

/**
 * Exception telling the user that an endpoint is missing on a Builder.
 */
class NoEndpointException extends \RuntimeException
{
	function __construct()
	{
		// TODO: Error code:
		parent::__construct('Missing endpoint in Builder.');
	}
}

/* End of file NoEndpointException.php */
/* Location: src/php/Inject/Stack/Builder */