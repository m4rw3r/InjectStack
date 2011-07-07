=======================
Mongrel2 Server example
=======================

This is a very simple example. And the ``\InjectStack\Adapter\Mongrel2``
class is not yet finished, it is still missing stuff like Multipart and
cookie parsing.

Running Mongrel and InjectStack
===============================

::
  
  cd deployment
  m2sh load -db config.sqlite -config mongrel2.conf
  m2sh start -db config.sqlite -host localhost -sudo
  cd ..
  php <example_name>.php

Then open your favourite browser and browse to
``http://localhost:6767/<example_name>``.

To stop Mongrel2 and InjectStack::

  [Ctrl-C]
  cd deployment
  m2sh stop -db config.sqlite -host localhost


Provided examples
=================

Here is a list of Mongrel2 example applications:

Counter
-------

File: ``counter.php``

This application counts the number of requests received for a certain
URI. The application can be reached at ``http://localhost:6767/counter``.


Running InjectStack with Mongrel and XDebug profiler
====================================================

::

  php -d xdebug.profiler_enable=On mongrel2.php

*Note*: Ignore the ``php:ZMQSocket->recv`` function as that function's run
time also includes wait time for the requests to arrive (so if there are two
requests with one second in between, ``php:ZMQSocket->recv`` will look like it
took a bit more than one second).

