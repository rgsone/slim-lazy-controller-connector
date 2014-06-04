# slim-lazy-controller-connector

Slim `LazyControllerConnector` is an extension for Slim Framework who provides a simple way to connect
and 'lazy load' controllers & middlewares with Slim routes.

### Features

* Provides a lazy loading for controllers and middlewares
* The registered controllers are instantiated only once (singleton)
* Provides a simple way to bind a controller with multiple routes and middlewares

## Install

With Composer.

	"require": {
		"slim/slim": "~2.4",
		"rgsone/slim-lazy-controller-connector": "dev-master"
	}

## Usage

`LazyControllerConnector` provides two ways to connect Slim routes with controllers.
Provides also a simple way to call a method's controller.

### Single route way

Setup

	<?php

	$slim = new \Slim\Slim();
    // LazyControllerConnector needs a Slim instance
    $connector = new \Rgsone\Slim\LazyControllerConnector( $slim );

Basic usage

    // connects route '/' with 'MyController' and calls his 'myAction' method via GET method
    $connector->connect( 'GET', '/', '\MyController:myAction' );

    // anothers examples
    $connector->connect( 'GET', '/foo/', '\MyOtherController:myAction' );
    // with namespace
    $connector->connect( 'GET', '/foo/bar/', '\Foo\Bar\Controller:myAction' );

Also accepts an array of middlewares to call in additional parameters

    $connector->connect(
    	'GET',
    	'/middleware/',
    	'\MyController:myAction',
    	array(
    		function() { echo 'middleware'; },
    		function() { echo 'another middleware'; },
    		// middlewares can also to be called like this
    		'\MyMiddleware:myAction'
    		// or in any callable forms
    	)
    );

`LazyControllerConnector::connect` method returns an `\Slim\Route` instance, so it is possible to add a route name
and/or a route conditions like Slim routing system

    $connector->connect( 'GET', '/bar/', '\MyController:myAction' )
    	->name( 'my.route.name' );

    $connector->connect( 'GET', '/bar/:id', '\MyController:myAction' )
    	->conditions( array( 'id' => '[0-9]+' ) )
    	->name( 'my.route.with.id.name' );

It also possible to bind multiples HTTP methods on a same route

    // binds with GET and POST methods
    $connector->connect( 'GET|POST', '/foo/bar', '\MyController:myAction' );

    // binds with GET, POST, DELETE and PUT methods
    $connector->connect( 'GET|POST|DELETE|PUT', '/foo/bar', '\MyController:myAction' );


### Multiple routes for a same controller

Setup

	<?php

	$slim = new \Slim\Slim();
    // LazyControllerConnector needs a Slim instance
    $connector = new \Rgsone\Slim\LazyControllerConnector( $slim );

Basic usage,
the only required parameter for each route is `action`, all others are optionnal by default,
if the `method` parameter is not present, the default HTTP method is `GET`

	$connector->connectRoutes(

		// controller
		'\MyController',

		// route list
		array(
			'/' => array(
				'action' => 'myAction'
			),
			'/foo' => array(
				'action' => 'myAction',
				'method' => 'GET'
			),
			'/bar' => array(
				'action' => 'myAction',
				'method' => 'POST'
			)
		)

	);

It is possible to bind multiples HTTP methods for a same route,
like `LazyControllerConnector::connect` each method must be separated by a pipe `|`

	$connector->connectRoutes(

		'\Foo\MyController',

		array(
			'/foo/foo' => array(
				'action' => 'myAction',
				// binds GET, POST and PUT methods with this route
				'method' => 'GET|POST|PUT'
			)
		)

	);

In addition, it is possible to name route, add conditions and add a middlewares for the same route

	$connector->connectRoutes(

		'\MyController',

		array(
			'/foo/:id' => array(
				'action' => 'myAction',
				'method' => 'GET|POST',
				// add conditions for :id
				'conditions' => array(
					'id' => '[0-9]+'
				),
				// names this route
				'name' => 'route.foo.name',
				// middlewares accepts any callable
				'middlewares' => array(
					function() { echo 'route middleware'; },
					// middlewares can also be called like this
					'\Middlewares\MyMiddleware:myAction'
				)
			)
		)

	);

As a last, a globals middlewares can be defined,
a global middleware is a middleware who is binded with each declared route

	$connector->connectRoutes(

		'\MyController',

		array(
			'/foo/bar' => array(
				'action' => 'myAction'
			),
			'/bar/foo' => array(
				'action' => 'myOtherAction'
			)
		),

		// these middlewares will be called for '/foo/bar' and '/bar/foo' routes
		function() { echo 'global middleware'; },
		'\MyMiddleware:myAction'

	);

Full example

	$connector->connectRoutes(

		'\Foo\Bar\MyController',

		array(
			'/foobar' => array(
				'method' => 'GET',
				'action' => 'myAction',
				'name' => 'route.foobar',
				'middlewares' => array(
					'\Middlewares\MyMiddleware:myAction'
				)
			),
			'/foobar/:id' => array(
				'method' => 'GET|POST',
				'action' => 'myOtherAction',
				'name' => 'route.foobar.id',
				'conditions' => array( 'id' => '[0-9]+' ),
				'middlewares' => array(
					function() { echo 'single route middleware'; }
				)
			)
		),

		function() { echo 'global middleware'; },
		'\MyMiddleware:myAction'

	);

### Calls a method from a controller

Setup

	<?php

	$slim = new \Slim\Slim();
    // LazyControllerConnector needs a Slim instance
    $connector = new \Rgsone\Slim\LazyControllerConnector( $slim );

Basic usage is to call an action/method from a controller

	// calls myAction from MyController
	$connector->callAction( '\MyController', 'myAction' );
	// it also possible to pass args to the called method
	$connector->callAction( '\MyController', 'myAction', array( 'my', 'parameters', 11 ) );

`LazyControllerConnector::callAction` is useful particularly for `Slim::notFound` method

	$slim->notFound( function() use ( $connector ) {
    	$connector->callAction( '\Controllers\MyNotFoundController', 'do404' );
    });

### Addition

A `\Slim\Slim` instance is passed in constructor parameters of each controller

	<?php

	// example of controller
	class MyController
	{
		private $_slimApp;

		public function __construct( \Slim\Slim $slim )
		{
			$this->_slimApp = $slim;
		}
	}

## Author

[rgsone aka Rudy Marc](http://rgsone.com)

## Thanks

to [Josh Lockhart](https://github.com/codeguy) for Slim Framework.

## License

Slim LazyControllerConnector is released under the MIT public license.
