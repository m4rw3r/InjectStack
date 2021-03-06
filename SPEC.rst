============================
Inject\\Stack Specifications
============================

This protocol is almost a straight port of Ruby's Rack_ `Specifications
<http://rack.rubyforge.org/doc/files/SPEC.html>`_.

Introduction
============

The basic principle of Inject\\Stack is a stack of layers — so called 
middleware — which perform specific actions and then passes the request
on to the next layer, ultimately reaching an endpoint which performs
the bulk of the operations required to create a response (think of an
endpoint as an action in a Controller from the MVC [#]_ pattern).
The endpoint might pass this on to another stack which leads to another
endpoint, or something completely different... you get the idea.

When an endpoint is done executing, its response will be returned through all
the middleware, enabling them to finish processing of the request and
finally let the browser see the result.

In more general terms, the application interface only requires one thing:
an object implementing the method ``__invoke($env)`` which incidentally also
includes `PHP Closures`_. The ``$env`` parameter and the return value of the
application are required have a specific format, see `The Environment Variable`_
and `The Return Value`_.

To make the creation of the stack easier, the class ``\Inject\Stack\Builder`` 
and the interface ``\Inject\Stack\MiddlewareInterface`` has been introduced.
``\Inject\Stack\Builder`` constructs a stack from supplied concrete instances
of the ``\Inject\Stack\MiddlewareInterface`` and a final endpoint implementing
the method ``__invoke($env)``.

What is an endpoint?
--------------------

An endpoint is a PHP object implementing ``__invoke($env)`` method (which
also includes PHP closures taking a single parameter). Usually the main
endpoint of your application will be a Router which in turn will call the
controller, or a controller specific ``\Inject\Stack\Builder`` or something else.

Running of a simple endpoint::

  <?php
  
  class MyEndpoint
  {
      public function __invoke($env)
      {
          return array(200, array('Content-Type' => 'text/plain'), 'Hello World!');
      }
  }
  
  // Or even:
  $hello_endpoint = function($env)
  {
      return array(200, array('Content-Type' => 'text/plain'), 'Hello World!');
  };
  
  // Run your application:
  $adapter = new \Inject\Stack\Adapter\Generic();
  $adapter->run($hello_endpoint);

For a more complicated endpoint, see the ``\Inject\Stack\CascadeEndpoint``.
This endpoint attempts several callbacks until one does not return a
response with the status code (first array index in the response) set to
``404``. So the associated callbacks will return a response along the lines
of ``array(404, array(), '')`` if they do not process the request.

What is middleware?
-------------------

If you have used Ruby on Rails or Ruby's Rack webserver interface you
probably already know what it is as it is almost a port.

A middleware is an object implementing 
``Inject\Stack\MiddlewareInterface`` which specifies a basic
interface for middleware. This interface enforces two public methods:
``setNext(callback $next)`` (sets the callback pointing to the next
layer/endpoint) and ``__invoke($env)`` which performs the middleware
logic and then (if the middleware logic allows) forwards the request to
the next middleware or endpoint.

The main reason for the usage of an interface is that it is not feasible
to inject the next middleware using the constructor of a middleware,
mainly because it will not be as fast or flexible in PHP as it is in Ruby.

Here is an example middleware which checks for the ``$_GET`` parameter "go" and 
returns a 404 if it cannot find it::

  <?php
  
  namespace MyNamespace;
  
  use \Inject\Stack\MiddlewareInterface;
  
  /**
   * Will return a 404 if the GET key "go" does not exist.
   */
  class BlockIfNotGo implements MiddlewareInterface
  {
      protected $next;
      
      public function setNext($callback)
      {
          $this->next = $callback;
      }

      public function __invoke($env)
      {
          if( ! isset($env['inject.get']['go']))
          {
              return array(404, array('Content-Type' => 'text/plain'), 'Page not found');
          }
          
          $callback = $this->next;
          return $callback($env);
      }
  }

For a simple middleware which does something more useful, look at
``\Inject\Stack\Middleware\RunTimer`` which times the execution of all the 
nested middleware and endpoint(s), code called by those and finally adds
this in an ``X-Runtime`` response header.

The Environment Variable
========================

The environment variable, usually referred to as ``$env``, is a hash
(PHP array with string keys) which is passed through all the layers
of the middleware stack. This hash contains a list of CGI like-headers (as
``$_SERVER`` usually looks like).

The base for this ``$env`` variable is usually the global ``$_SERVER``
variable as it already contains many of the headers which are used
by PHP applications and also the information needed to run said
application and its components.

``$env`` is not a static hash, all components of the system are allowed
to modify the environment to, for example, add a global object, filter a
specific header or change something like the ``REQUEST_METHOD``. This
can be very useful when for example performing internal HMVC [#]_ requests,
as you can copy the ``$env`` variable and change a few keys before
passing it on to the internal controller.

The environment variable must however conform to a few basic rules:

Required keys
-------------

The Environment variable must always include these keys:

``REQUEST_METHOD``:
    The HTTP request method, such as "GET" or "POST". This cannot ever
    be an empty string, and so is always required. Uppercase.

``SCRIPT_NAME``:
    The initial portion of the request URL's "path" that corresponds
    to the application object, so that the application knows its virtual
    "location". This may be an empty string, if the application
    corresponds to the "root" of the server (in the case of URL rewriting).
    
    If it is not empty it must start with a ``/``, it must never contain
    ``/`` by itself.

``PATH_INFO``:
    The remainder of the request URL's "path", designating the virtual
    "location" of the request‘s target within the application. This may
    be an empty string, if the request URL targets the application root
    and does not have a trailing slash. This value may be percent-encoded
    when originating from a URL.
    
    If it is not empty it must start with a ``/``, if ``SCRPT_NAME`` is
    empty, it must be ``/``.

``BASE_URI``:
    The URI prefix to be used when referring to static assets which are
    not processed by the application logic.
    
    This is usually the URI without the ``index.php`` file name, and will
    usually be taken care of by the concrete class implementing
    ``\Inject\Stack\AdapterInterface``.

``QUERY_STRING``:
    The portion of the request URL that follows the ?, if any. May be empty,
    but is always required!

``SERVER_NAME``, ``SERVER_PORT``:
    When combined with SCRIPT_NAME and PATH_INFO, these variables can be
    used to complete the URL. Note, however, that HTTP_HOST, if present,
    should be used in preference to SERVER_NAME for reconstructing the
    request URL. SERVER_NAME and SERVER_PORT can never be empty strings,
    and so are always required.

``REMOTE_ADDR``:
    The IP address of the remote connection which the server received.

``HTTP_`` Variables:
    Variables corresponding to the client-supplied HTTP request headers
    (i.e., variables whose names begin with HTTP\_). The presence or absence
    of these variables should correspond with the presence or absence of
    the appropriate HTTP header in the request.

Adapter supplied keys
---------------------

Inject\\Stack's ``AdapterInterface`` implementations will include these keys:

``inject.version``:
    The current version of Inject\\Stack.

``inject.url_scheme``:
    ``https`` or ``http``, depending on the request URL.

``inject.adapter``:
    The class name of the concrete class implementing
    ``\Inject\Stack\AdapterInterface`` which is used to run the application.

``inject.get``:
    Contains the GET data.

``inject.post``:
    Contains the POST data, ie. parsed ``inject.input``, provided the request's
    ``REQUEST_METHOD`` is ``POST`` or that the ``CONTENT_TYPE`` is
    ``application/x-www-form-urlencoded`` or ``multipart/form-data``.

``inject.input``:
    Stream containing the request body, will be closed by the adapter upon request completion.
    By default this is stream can **not** be rewinded!

.. TODO: Add more when a few middleware gets standardized, like error
   handler, session, cookie storage, file upload etc.

Optional keys with restrictions
-------------------------------

All keys which do not contain a dot (``.``) must contain string/scalar values,
if you include a dot in the name (like ``web.route``) there are no
restrictions on what you can use as a value.

These keys have special rules:

``CONTENT_LENGTH``:
    If present it must match ``/^\d+$/``.

``HTTP_CONTENT_TYPE``:
    Must not be present, rename to ``CONTENT_TYPE``.

``HTTP_CONTENT_LENGTH``:
    Must not be present, rename to ``CONTENT_LENGTH``.

The Return value
================

The return value of all middleware and endpoints is an array with three
elements, containing response code, array with response headers and
finally the string which is the response body::

  array(response_code, response_headers, response_body)

It can also be an object implementing ``\ArrayAccess``, ``\Countable``
and also ``\Iterator`` or ``\IteratorAggregate``.
The value returned by ``$return_array[0]`` must be the response code,
``$return_array[1]`` are the headers and ``$return_array[2]`` contains
the response body.

Example response array::

  array(200,
      array('Content-Type'  => 'text/html; charset=utf-8',
            'Last-Modified' => date(\DateTime::RFC1123),
            'Cache-Control' => 'public'),
      '<?xml version="1.0" encoding="UTF-8"?>
      <!DOCTYPE html PUBLIC ...')

Response Code
-------------

A plain integer which is the HTTP response code (matches ``/^\d+$/``
and ``>= 100``).

Response Headers
----------------

Must be an array or array equivalent (``\ArrayAccess``, ``\Countable``
and also ``\Iterator`` or ``\IteratorAggregate``).

All header keys are strings, and written as they are in the HTTP specification,
ie. ``Content-Type`` instead of ``content-type`` or ``content_type``. [#]_
Their values cannot contain ``:`` or ``\n`` and must match
``/^[a-zA-Z][a-zA-Z0-9_-]*$/``. The header ``status`` is not allowed.

All header values must either be strings or objects responding to
``__toString()``, and they must not contain ASCII character values
below ``028`` (excepting newline ``== 012 == \n``).

If the response code is ``1xx``, ``204`` or ``304`` the ``Content-Type``
header cannot exist. Otherwise it must be present.

If the response code is ``1xx``, ``204`` or ``304``, or if the
``REQUEST_METHOD`` is ``HEAD``, the ``Content-Length`` header must not
exist. Otherwise it must match the length of the body (``strlen($body)``)
provided that the header itself exists.

Response Body
-------------

The response body is a string or an object responding to ``__toString()``.
It must be empty if the ``REQUEST_METHOD`` is ``HEAD``.

It can also be a resource-stream which can be used with ``fread()``, ``feof()``
and ``fclose()``. In that case adapters will read from the resource using
``fread()`` while ``feof()`` != ``false``, and when the stream reading has
reached ``EOF`` the stream will be closed with ``fclose()``.

If the ``Content-Length`` header does not exist and the response body is a
string or object (and not empty), it will be created and assigned with the
length of the resulting string. (``Transfer-Encoding`` header must be empty for this
auto-assignment)

If the ``Content-Length`` header does not exist and the response is a
resource-stream, `Chunked-Encoding`_ will be used to transfer the data from
the stream, making it possible to deliver content which length is not yet known.
(``Transfer-Encoding`` header must be empty for this auto-assignment)

Validating ``$env`` and the response
====================================

To validate ``$env`` and the response of your middleware/endpoints, you may
use the ``\Inject\Stack\Middleware\Lint`` middleware. This middleware will
validate the ``$env`` var when it is received, and after the next 
middleware/endpoint has processed the request, it will validate the response.

It is recommended to add one instance before your middleware and one after
to validate that the ``$env`` variable is passed on correctly. If you want
to validate an endpoint, just add the lint middleware as the last middleware
before your endpoint.

If any of the assertions fail, a ``LintException`` will be thrown, detailing
the problem

*Note*: Do not use this in production, however, as all the checks will slow 
down the request processing by a large factor.


.. [#] Model-View-Controller, see `Wikipedia about MVC`_
.. [#] Hierarchical Model-View-Controller, see `Wikipedia about HMVC`_
.. [#] This is to prevent multiple fields for the same key (HTTP specifications
       say that header keys are case-insensitive) without lots of extra code
       converting them to and from the ``ucfirst()`` format specified in the
       HTTP-spec.
.. _Rack: http://rack.rubyforge.org/
.. _`PHP Closures`: http://php.net/manual/en/functions.anonymous.php
.. _`Wikipedia about MVC`: http://en.wikipedia.org/wiki/Model%E2%80%93view%E2%80%93controller
.. _`Wikipedia about HMVC`: http://en.wikipedia.org/wiki/Presentation-abstraction-control
.. _`Chunked-Encoding`: http://www.w3.org/Protocols/rfc2616/rfc2616-sec3.html#sec3.6.1
