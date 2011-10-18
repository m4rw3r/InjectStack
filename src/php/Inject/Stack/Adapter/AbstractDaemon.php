<?php
/*
 * Created by Martin Wernståhl on 2011-04-25.
 * Copyright (c) 2011 Martin Wernståhl.
 * All rights reserved.
 */

namespace Inject\Stack\Adapter;

use \Closure;
use \Inject\Stack\AdapterInterface;
use \Inject\Stack\Adapter\Exception as AdapterException;

/**
 * Base class for adapters which should be able to spawn worker processes to serve requests.
 * 
 * Requires the PHP PCNTL Extension <http://www.php.net/manual/en/book.pcntl.php>
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
	 * List of child process ids.
	 * 
	 * @var array(int)
	 */
	private $child_pids = array();
	
	/**
	 * The index position for the PID in the shared memory for this child.
	 * 
	 * @var int
	 */
	private $pid_index = null;
	
	// ------------------------------------------------------------------------
	
	/**
	 * Starts several child processes and maintains the specified number of children.
	 * 
	 * @param  Closure   Function creating the non-shared resources and returns
	 *                   the application to run
	 * @param  int       Number of child processes
	 * @return never
	 */
	public function serve(Closure $app_builder, $num_children = 5)
	{
		// Check for PCNTL Extension
		if( ! extension_loaded('pcntl'))
		{
			throw AdapterException::pcntlMissing(__CLASS__);
		}
		
		// Init shared resources for the daemon
		$this->preFork();
		
		// Start the specified number of children
		for($i = 0; $i < $num_children; $i++)
		{
			$this->child_pids[$i] = $this->fork($app_builder, $i);
		}
		
		// Monitor loop:
		while(true)
		{
			// Register signal handlers
			\pcntl_signal(SIGTERM, array($this, 'killChildren'), false);
			\pcntl_signal(SIGHUP,  array($this, 'killChildren'), false);
			\pcntl_signal(SIGINT,  array($this, 'killChildren'), false);
			\pcntl_signal(SIGUSR1, array($this, 'reloadChildren'), false);
			
			// Wait for a child exit or hang, or signal
			declare(ticks = 1)
			{
				$null = null;
				$dead_pid = \pcntl_wait($null);
			}
			
			// Reset signal handlers for child
			\pcntl_signal(SIGTERM, SIG_DFL);
			\pcntl_signal(SIGHUP,  SIG_DFL);
			\pcntl_signal(SIGINT,  SIG_DFL);
			\pcntl_signal(SIGUSR1, SIG_DFL);
			
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
		\pcntl_signal(SIGTERM, SIG_DFL);
		\pcntl_signal(SIGHUP,  SIG_DFL);
		\pcntl_signal(SIGINT,  SIG_DFL);
		\pcntl_signal(SIGUSR1, SIG_DFL);
		
		foreach($this->child_pids as $pid)
		{
			echo "Killing child $pid...\n";
			\posix_kill($pid, SIGTERM);
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
			\posix_kill($pid, SIGUSR1);
		}
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
		$pid = \pcntl_fork();
		
		if($pid == -1)
		{
			throw AdapterException::couldNotFork();
		}
		elseif($pid === 0)
		{
			// Child:
			// Register the graceful shutdown signal handler, and declare that we listen for it
			\pcntl_signal(SIGUSR1, array($this, 'shutdownGracefully'), false);
			
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