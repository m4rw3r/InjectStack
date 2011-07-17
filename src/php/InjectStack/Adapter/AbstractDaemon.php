<?php
/*
 * Created by Martin Wernståhl on 2011-04-25.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace InjectStack\Adapter;

use \Closure;
use \InjectStack\AdapterInterface;

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
 * $adapter = new \InjectStack\Adapter\FooDaemon();
 * 
 * // Spawn 4 worker processes
 * $adapter->serve($app_init, 4);
 * </code>
 */
abstract class AbstractDaemon implements AdapterInterface
{
	/**
	 * List of child process ids.
	 * 
	 * @var array(int)
	 */
	protected $child_pids = array();
	
	// ------------------------------------------------------------------------
	
	/**
	 * Starts several child processes and maintains the specified number of children.
	 * 
	 * @param  Closure  Function creating the non-shared resources and returns
	 *                  the application to run
	 * @param  int      Number of child processes
	 * @return never
	 */
	public function serve(Closure $app_builder, $num_children = 5)
	{
		// Init shared resources for the daemon
		$this->preFork();
		
		// Start the specified number of children
		for($i = 0; $i < $num_children; $i++)
		{
			$this->child_pids[] = $this->fork($app_builder);
		}
		
		// Monitor loop:
		while(true)
		{
			// Register signal handlers
			pcntl_signal(SIGTERM, array($this, 'killChildren'), false);
			pcntl_signal(SIGHUP,  array($this, 'killChildren'), false);
			pcntl_signal(SIGUSR1, array($this, 'reloadChildren'), false);
			
			$null = null;
			declare(ticks = 1)
			{
				// Wait for child exit, or signal
				$dead_pid = pcntl_wait($null);
			}
			
			// Error, retry
			if($dead_pid === -1)
			{
				continue;
			}
			
			echo "Restarting child $dead_pid\n";
			// Reset signal handlers for child
			pcntl_signal(SIGTERM, SIG_DFL);
			pcntl_signal(SIGHUP,  SIG_DFL);
			pcntl_signal(SIGUSR1, SIG_DFL);
			
			// Remove dead child PID
			if($key = array_search($dead_pid, $this->child_pids))
			{
				unset($this->child_pids[$key]);
			}
			
			$this->child_pids[] = $this->fork($app_builder);
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
	 * Attempts to fork this process, if success the child will call run() with the result
	 * from $app_builder and the pid will be returned with the parent.
	 * 
	 * @param  Closure  Function to be called to create the non-shared resources and finally
	 *                  instantiate the application
	 * @return int      PID of the worker if we are the server, otherwise this process dies after run() returns
	 */
	protected function fork($app_builder)
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
			
			declare(ticks = 1)
			{
				// Run the $app_builder and then we just let run() handle it normally
				$this->run($app_builder());
			}
			
			// TODO: Proper exception
			throw new Exception("Worker died.");
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
/* Location: src/php/InjectStack/Adapter */