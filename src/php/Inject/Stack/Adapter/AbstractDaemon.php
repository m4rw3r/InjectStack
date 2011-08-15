<?php
/*
 * Created by Martin Wernståhl on 2011-04-25.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace Inject\Stack\Adapter;

use \Closure;
use \Exception;
use \Inject\Stack\AdapterInterface;

/**
 * Base class for adapters which should be able to spawn worker processes to serve requests.
 * 
 * Usage:
 * <code>
 * 
 * // Init global stuff here
 * 
 * // The per-process application init
 * $app_init = function()
 * {
 *     // Create and return our application here, will be run for each child process
 *     // This is run before we are listening for any requests
 *     
 *     $app = function($env)
 *     {
 *         return array(200, array('Content-Type' => 'text/plain'), 'Process id: '.getmypid());
 *     };
 *     
 *     return $app;
 * };
 * 
 * // Replace this row with whatever daemon adapter you use:
 * $adapter = new \Inject\Stack\Adapter\FooDaemon();
 * 
 * // Spawn 4 worker processes
 * $adapter->serve($app_init, 4);
 * </code>
 * 
 * If a worker process dies, for any reason, it will be replaced with a new fork.
 * 
 * If the shared memory monitoring is enabled (AbstractDaemon::$use_shmop == true, default)
 * the child processes writes into shared memory periodically to tell the managing
 * process that they are still responsive to requests. If a child does not write within
 * a set period of time, it will be killed and restarted.
 * 
 * To force a re-fork of all the worker processes, send a SIGUSR1 signal to
 * the monitor process (the one manually started). This will make all children
 * quit as soon as they do not serve a request.
 * 
 * To kill the monitor and all workers, just send the normal SIGTERM, SIGHUP or
 * SIGINT signal to the managing process and it will forward it to the children.
 */
abstract class AbstractDaemon implements AdapterInterface
{
	/**
	 * If to use Shared Memory for worker process monitoring, will restart workers
	 * if they hang. Requires PHP to be compiled with `--enable-shmop`.
	 * 
	 * @var boolean
	 */
	public static $use_shmop = true;
	
	/**
	 * List of child process ids.
	 * 
	 * @var array(int)
	 */
	private $child_pids = array();
	
	/**
	 * Integer pointing to the shared memory.
	 * 
	 * @var int
	 */
	private $shmop_ptr = null;
	
	/**
	 * The index position for the PID in the shared memory for this child.
	 * 
	 * @var int
	 */
	private $pid_index = null;
	
	/**
	 * The amount of time to spend waiting between requests before timing out and
	 * calling notifyNoHang() and resuming waiting for requests.
	 * 
	 * @var int
	 */
	protected $sleep_time = 10;
	
	// ------------------------------------------------------------------------
	
	/**
	 * Starts several child processes and maintains the specified number of children.
	 * 
	 * @param  Closure   Function creating the non-shared resources and returns
	 *                   the application to run
	 * @param  int       Number of child processes
	 * @param  int|false Number of seconds between checking up on worker status, > 1
	 *                   The shared memory monitoring will use half of this number as
	 *                   the execution limit for one request for the workers
	 * @return never
	 */
	public function serve(Closure $app_builder, $num_children = 5, $sleep_time = 2)
	{
		$this->sleep_time = $sleep_time < 2 ? 2 : $sleep_time;
		
		// Init shared resources for the daemon
		$this->preFork();
		
		static::$use_shmop && $this->initShmop($num_children);
		
		// Start the specified number of children
		for($i = 0; $i < $num_children; $i++)
		{
			$this->child_pids[$i] = $this->fork($app_builder, $i);
		}
		
		// Monitor loop:
		while(true)
		{
			// Register signal handlers
			pcntl_signal(SIGTERM, array($this, 'killChildren'), false);
			pcntl_signal(SIGHUP,  array($this, 'killChildren'), false);
			pcntl_signal(SIGINT,  array($this, 'killChildren'), false);
			pcntl_signal(SIGUSR1, array($this, 'reloadChildren'), false);
			
			// Wait for a child exit or hang, or signal
			declare(ticks = 1)
			{
				$dead_pid = -1;
				
				while($dead_pid < 1)
				{
					// TODO: maybe time_nanosleep()?
					sleep($this->sleep_time);
					
					$null = null;
					$dead_pid = pcntl_waitpid(-1, $null, WNOHANG);
					
					if( ! in_array($dead_pid, $this->child_pids))
					{
						// Not a process we currently care about, ignore
						$dead_pid = -1;
					}
					
					if(static::$use_shmop && $dead_pid < 1 && ($index = $this->checkHang()) > -1)
					{
						$dead_pid = $this->child_pids[$index];
						
						echo "[Child $dead_pid has hanged]\n";
						
						// TODO: This will trigger pcntl_waitpid() next iteration,
						// fix so it does not?
						posix_kill($dead_pid, SIGTERM);
					}
				}
			}
			
			// Reset signal handlers for child
			pcntl_signal(SIGTERM, SIG_DFL);
			pcntl_signal(SIGHUP,  SIG_DFL);
			pcntl_signal(SIGINT,  SIG_DFL);
			pcntl_signal(SIGUSR1, SIG_DFL);
			
			// Remove dead child PID
			if(($key = array_search($dead_pid, $this->child_pids)) !== false)
			{
				unset($this->child_pids[$key]);
				
				echo "Restarting child $dead_pid\n";
				$this->child_pids[$key] = $this->fork($app_builder, $key);
			}
			else
			{
				// TODO: Should we do anything about this?
				// echo "[Child $dead_pid is not a registered PID]\n";
			}
		}
	}
	
	// ------------------------------------------------------------------------
	
	/**
	 * Kills all children, then suicides.
	 * 
	 * @return void
	 */
	public function killChildren()
	{
		pcntl_signal(SIGTERM, SIG_DFL);
		pcntl_signal(SIGHUP,  SIG_DFL);
		pcntl_signal(SIGINT,  SIG_DFL);
		pcntl_signal(SIGUSR1, SIG_DFL);
		
		foreach($this->child_pids as $pid)
		{
			echo "Killing child $pid...\n";
			posix_kill($pid, SIGTERM);
		}
		
		$this->child_pids = array();
		
		echo "[All children killed]\n";
		die();
	}
	
	// ------------------------------------------------------------------------
	
	/**
	 * Sends all children the SIGUSR1 signal, which should indicate a graceful shutdown after the
	 * next request (the child adapter's shutdownGracefully() method will be called).
	 * 
	 * The monitor loop will recreate children when they do shutdown.
	 * 
	 * @return void
	 */
	public function reloadChildren()
	{
		foreach($this->child_pids as $pid)
		{
			posix_kill($pid, SIGUSR1);
		}
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Initializes the shared memory for worker hang-checking.
	 * 
	 * @param  int   The number of worker processes to use
	 * @return void
	 */
	private function initShmop($num_children)
	{
		// Ensure we delete the shared memory on shutdown
		register_shutdown_function(array($this, 'delShmop'));
		
		// Make 10 attempts at getting shared memory
		$i = 0;
		while( ! $this->shmop_ptr && $i < 10)
		{
			// The key is a random number in an unsigned int16
			$this->shmop_ptr  = shmop_open(mt_rand(0, 4294967295), "c", 0644, $num_children);
			
			$i++;
		}
		
		if( ! $this->shmop_ptr)
		{
			throw new Exception("Could not allocate shared memory for worker communication");
		}
		
		// Reset shared memory
		shmop_write($this->shmop_ptr, str_repeat("0", $num_children), 0);
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Frees the shared memory allocated by initShmop().
	 * 
	 * @return void
	 */
	public function delShmop()
	{
		echo "Freeing shared memory...";
		
		$res = shmop_delete($this->shmop_ptr);
		
		if( ! $res)
		{
			echo "Failed to free $this->shmop_ptr\n";
		}
		else
		{
			echo "DONE\n";
		}
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Writes a byte into the shared memory to indicate that this child is still
	 * alive and responsive, call before starting to wait for a request.
	 * 
	 * Wait at most $this->sleep_time for a new request before calling again.
	 * 
	 * @return void
	 */
	public function notifyNoHang()
	{
		static::$use_shmop && shmop_write($this->shmop_ptr, "1", $this->pid_index);
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Checks if any of the children has not written to the shared memory, if
	 * that is the case, the first index without a "1" in it will be returned.
	 * 
	 * Does not work when $use_shmop is false, do not call if that is the case.
	 * 
	 * @return int  The child index for the hanged child, -1 otherwise.
	 */
	private function checkHang()
	{
		$index        = -1;
		$num_children = count($this->child_pids);
		$bytes        = shmop_read($this->shmop_ptr, 0, $num_children);
		
		// Find a process which has not written its no-hang notification
		for($i = 0; $i < $num_children; $i++)
		{
			// The process has not written a signal
			if( ! $bytes[$i])
			{
				$index = $i;
				
				break;
			}
		}
		
		// Reset shared memory
		shmop_write($this->shmop_ptr, str_repeat("0", $num_children), 0);
		
		return $index;
	}
	
	// ------------------------------------------------------------------------
	
	/**
	 * Attempts to fork this process, if successful; the child will call run() with the result
	 * from $app_builder and the pid will be returned with the parent.
	 * 
	 * @param  Closure  Function to be called to create the non-shared resources and finally
	 *                  instantiate the application
	 * @param  int      The index of the PID in shared memory
	 * @return int      PID of the worker if we are the server, otherwise this process dies after run() returns
	 */
	protected function fork($app_builder, $pid_index)
	{
		$pid = pcntl_fork();
		
		if($pid == -1)
		{
			// TODO: Proper exception
			throw new Exception('Could not fork process.');
		}
		elseif($pid === 0)
		{
			// Child:
			// Register the graceful shutdown signal handler, and declare that we listen for it
			pcntl_signal(SIGUSR1, array($this, 'shutdownGracefully'), false);
			
			// Update the pid_index for shared memory and also the sleep time,
			// sleep time needs to be half so we can guarantee that a child will
			// notify that it is still responsive
			$this->pid_index  = $pid_index;
			$this->sleep_time = (int) $this->sleep_time / 2;
			
			declare(ticks = 1)
			{
				// Run the $app_builder and then we just let run() handle it normally
				$this->run($app_builder());
			}
			
			die("[Worker died normally]\n");
		}
		
		echo "Forked child $pid\n";
		
		// Successful fork, return child id
		return $pid;
	}
	
	// ------------------------------------------------------------------------
	
	/**
	 * This method will be run ONCE before children are being forked, to initialize shared
	 * stuff for the adapter, like used sockets.
	 *
	 * @return void
	 */
	protected function preFork()
	{
		// Empty
	}
	
	// ------------------------------------------------------------------------
	
	/**
	 * This method is called when PHP receives a SIGUSR1 call, and should tell run() to
	 * quit gracefully after the current request has been served.
	 * 
	 * @return void
	 */
	protected abstract function shutdownGracefully();
}


/* End of file AbstractDaemon.php */
/* Location: src/php/Inject/Stack/Adapter */