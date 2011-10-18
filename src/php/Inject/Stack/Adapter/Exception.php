<?php
/*
 * Created by Martin Wernståhl on 2011-04-25.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace Inject\Stack\Adapter;

use \Exception as BaseException;

class Exception extends BaseException
{
	public static function pcntlMissing($requiring_class)
	{
		return new static(sprintf('The PCNTL Extension is required by %s, compile PHP with --enable-pcntl to enable it. See <http://www.php.net/manual/en/book.pcntl.php> for more information.', $requiring_class));
	}
	
	public static function couldNotFork()
	{
		return new static('Could not fork() process using pcntl_fork().');
	}
	
	public static function socketUnavailable($addr, $err_no, $err_msg)
	{
		return new static(sprintf('Could not create socket for listening on %s, caused by %d: %s', $addr, $err_no, $err_msg));
	}
}

/* End of file Exception.php */
/* Location: src/php/Inject/Stack/Adapter */