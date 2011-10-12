====================
Inject\\Stack ReadMe
====================

Inject\\Stack is a minimal base for layered and modular PHP applications.
It is a PHP-style port of Ruby's Rack_, and because of that it inherits
Rack's minimal requirements on the client layers, modules and applications.

Requirements
============

For the default usage with a normal MOD_PHP or PHP-FPM installation:

* PHP 5.3 or later
* PCRE Extension (compiled into PHP by default)
* Tokenizer Extension (compiled into PHP by default), used by default ``ShowException``
  middleware to provide syntax-highlighting
* `PSR-0 Compliant`_ Autoloader, Inject_ClassTools_ provides one general purpose
  autoloader as do the `Symfony ClassLoader`_.

If you use any of the other provided adapters and or middleware, additional
extensions may be required. For example, the Memcached-session middleware
require Memcached_.

Installation
============

To install Inject\\Stack, you can either download a compressed archive
from GitHub_ or use the `InjectFramework PEAR channel`_::

  pear channel-discover injectframework.github.com/pear
  pear install --alldeps injectfw/InjectStack

If you already have a `PSR-0 Compliant`_ autoloader, you do not have to include
``--alldeps`` in the install command.

Getting Started
===============

A quick hello world example with one of the default middlewares:

::

  <?php
  // Include and register the autoloader:
  require 'Inject/ClassTools/Autoloader/Generic.php';
  $loader = new \Inject\ClassTools\Autoloader\Generic();
  $loader->register();
  
  // Our application
  $my_endpoint = function($env)
  {
      return array(200, array('Content-Type' => 'text/plain'), 'Hello World!');
  };
  
  // Let's wrap it in a timer:
  $stack = new \Inject\Stack\Builder(
      // List of middleware
      array(
          new \Inject\Stack\Middleware\RunTimer()
      ),
      $my_endpoint
  );
  
  $adapter = new \Inject\Stack\Adapter\Generic();
  $adapter->run($stack);


Mongrel2 Support
================

Inject\\Stack also has support for Mongrel2_, which is a high-speed network- and
language- agnostic web-server which connects through ZeroMQ_ directly to running
PHP processes. This enables you to get even better performance than the normal
PHP web-servers because everything is already running when the request arrives.
The downside is that you have to be careful so you don't leak data to other
requests or leak memory.

Requirements
------------

* Mongrel2_
* ZeroMQ_
* `ZeroMQ Extension for PHP`_
* `pcntl Extension`_, optional, if you want to be able to spawn multiple worker
  processes directly from PHP.

Current limitations
-------------------

* No support for ``multipart/form-data`` yet so no file-transfers can be accepted,
  ``application/x-www-form-urlencoded`` for normal forms is supported though

HTTP server in PHP using sockets
================================

Inject\\Stack comes with a built-in HTTP server written in PHP which provides
high performance as there are no layers in between your code and the client-socket.

Requirements
------------

* `pcntl Extension`_, required if you want to be able to serve more than one
  request concurrently (provides ``fork()`` so multiple worker processes can
  share one socket).

.. _Rack: http://rack.rubyforge.org
.. _GitHub: https://github.com/InjectFramework/InjectStack
.. _Inject_ClassTools: https://github.com/InjectFramework/Inject_ClassTools
.. _`Symfony ClassLoader`: https://github.com/symfony/ClassLoader
.. _Memcached: http://pecl.php.net/package/memcached
.. _`InjectFramework PEAR channel`: http://injectframework.github.com/pear/
.. _`PSR-0 Compliant`: http://groups.google.com/group/php-standards/web/psr-0-final-proposal
.. _Mongrel2: http://www.mongrel2.org
.. _ZeroMQ: http://www.zero.mq
.. _`ZeroMQ Extension for PHP`: http://pear.zero.mq
.. _`pcntl Extension`: http://se2.php.net/manual/en/book.pcntl.php