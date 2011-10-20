<?php

class Test
{
	public static $test_bootstrap = 'bootHTTPSocket.php';
	
	protected $name = '';
	
	protected $appfile = 'dumper.php';
	
	protected $input = '';
	
	protected $expected_out = '';
	
	protected $expected_file = '';
	
	public function __construct($name)
	{
		$this->name = $name;
		
		// Default validation is a not-empty check
		$this->expected_out = $this->expected_file = function($in)
		{
			return ! empty($in);
		};
	}
	
	public function app($appfile)
	{
		$this->appfile = $appfile;
		
		return $this;
	}
	
	public function input($input)
	{
		$this->input = $input;
		
		return $this;
	}
	
	public function expect($expected_output)
	{
		$this->expected_out = $expected_output;
		
		return $this;
	}
	
	public function console($expected_file)
	{
		$this->expected_file = $expected_file;
		
		return $this;
	}
	
	public function run()
	{
		$ret = $this->runHTTP();
		
		$fail = false;
		
		if(is_string($this->expected_out))
		{
			$fail = ($fail OR ($this->expected_out !== $ret[0]));
		}
		else
		{
			$func = $this->expected_out;
			
			$fail = ($fail OR ! $func($ret[0]));
		}
		
		if(is_string($this->expected_file))
		{
			$fail = ($fail OR ($this->expected_file !== $ret[1])); 
		}
		else
		{
			$func = $this->expected_file;
			
			$fail = ($fail OR ! $func($ret[1]));
		}
		
		if($fail OR getenv('verbose'))
		{
			file_put_contents($this->name.'.response', $ret[0]);
			file_put_contents($this->name.'.output', $ret[1]);
		}
		
		$this->printResult($fail);
	}
	
	protected function printResult($fail)
	{
		$str = str_replace(array("\r", "\n"), array("\\r", "\\n"), $this->input);
		
		printf("%-30s %4s\n", $this->name , $fail ? 'FAIL' : 'OK');
	}
	
	protected function runHTTP()
	{
		system('APPFILE='.escapeshellarg($this->appfile).' php '.escapeshellarg(self::$test_bootstrap).' > output.txt &');
		
		usleep(70000);
		
		$sock = stream_socket_client('tcp://127.0.0.1:8088', $errno, $errstr, 2);
		
		if( ! $sock)
		{
			usleep(70000);
			
			if(file_exists('pidfile'))
			{
				while( ! posix_kill(file_get_contents('pidfile'), SIGTERM))
				{
					// Wait
				}
				
				unlink('pidfile');
			}
			
			return array(false, false);
		}
		
		stream_set_timeout($sock, 1, 0);
		
		fwrite($sock, $this->input);
		
		$str = stream_get_contents($sock);
		
		$info = stream_get_meta_data($sock);
		
		if($info['timed_out'])
		{
			$str = false;
		}
		
		if( ! file_exists('pidfile'))
		{
			return array(false, false);
		}
		
		while( ! posix_kill(file_get_contents('pidfile'), SIGTERM))
		{
			// Wait
		}
		
		unlink('pidfile');
		
		usleep(70000);
		
		$console =  file_get_contents('output.txt');
		
		unlink('output.txt');
		
		return array($str, $console);
	}
}

function test($name)
{
	return new Test($name);
}