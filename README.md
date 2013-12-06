# slim-controller-connector

Slim `ControllerConnector` is an extension for Slim Framework who provides
a simple way to connect a controller with a Slim route.

### Features

* The controller for a particular route is instantiated only if his route is matched
* The registered controllers are instantiated only once (singleton)

## Install

With Composer.

	"require": {
		"slim/slim": "~2.3",
		"rgsone/slim-controller-connector": "dev-master"
	}

## Usage

`ControllerConnector` provide two ways to connect Slim routes with controllers.

### Single route way

setup

	<?php

	$slim = new \Slim\Slim();
    // ControllerConnector needs a Slim instance
    $connector = new \Rgsone\Slim\ControllerConnector( $slim );

basic usage

    // connects '/' with 'MyController' and calls his 'myAction' method via GET method
    $connector->connect( 'GET', '/', 'MyController:myAction' );

    // another example
    $connector->connect( 'GET', '/foo/', 'MyOtherController:myAction' );

also accepts an array of middlewares to call, in additional parameters

    $connector->connect(
    	'GET',
    	'/middleware/',
    	'MyController:myAction',
    	array(
    		function() { echo 'middleware'; },
    		function() { echo 'another middleware'; },
    		// middlewares can also to be called like this
    		'MyMiddleware:myAction'
    		// or in any callable forms
    	)
    );

`ControllerConnector::connect` method returns an `\Slim\Route` object, so it is possible to add a route name and/or
a route conditions like Slim routing system

    $connector->connect( 'GET', '/bar/', 'MyController:myAction' )
    	->name( 'my.route.name' );

    $connector->connect( 'GET', '/bar/:id', 'MyController:myAction' )
    	->conditions( array( 'id' => '[0-9]+' ) )
    	->name( 'my.route.with.id.name' );

it also possible to bind multiples HTTP methods on a same route

    // binds with GET and POST methods
    $connector->connect( 'GET|POST', '/foo/bar', 'MyController:myAction' );

    // binds with GET, POST, DELETE and PUT methods
    $connector->connect( 'GET|POST|DELETE|PUT', '/foo/bar', 'MyController:myAction' );


### Multiple routes for a same controller

setup

	<?php

	$slim = new \Slim\Slim();
    // ControllerConnector needs a Slim instance
    $connector = new \Rgsone\Slim\ControllerConnector( $slim );

basic usage,
the only required parameter for each route is `action`, all others are optionnal by default,
if the `method` parameter is not present, the default HTTP method is `GET`

	$connector->connectRoutes(

		'MyController',

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

it is possible to bind multiples HTTP methods for a same route,
like `ControllerConnector::connect` each method must be separated by a `|`

	$connector->connectRoutes(

		'MyController',

		array(
			'/foo/foo' => array(
				'action' => 'myAction',
				// binds GET, POST and PUT methods with this route
				'method' => 'GET|POST|PUT'
			)
		)

	);

in addition, it is possible to name route, add conditions and add a middlewares for the same route

	$connector->connectRoutes(

		'MyController',

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
					'MyMiddleware:myAction'
				)
			)
		)

	);

as a last, a globals middlewares can be defined,
a global middleware is a middleware who is called for each declared route

	$connector->connectRoutes(

		'MyController',

		array(
			'/foo/bar' => array(
				'action' => 'myAction'
			),
			'/bar/foo' => array(
				'action' => 'myOtherAction'
			)
		),

		// this middlewares will be called for '/foo/bar' and '/bar/foo' routes
		function() { echo 'global middleware'; },
		'MyMiddleware:myAction'

	);

full example

	$connector->connectRoutes(

		'MyController',

		array(
			'/foobar' => array(
				'method' => 'GET',
				'action' => 'myAction',
				'name' => 'route.foobar',
				'middlewares' => array(
					'MyMiddleware:myAction'
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
		'MyMiddleware:myAction'

	);

### Addition

The `\Slim\Slim` instance is passed in constructor parameters of each controller.

	<?php

	// example of controller

	class MyController
	{
		private $slimApp;

		public function __construct( \Slim\Slim $slim )
		{
			$this->slimApp = $slim;
		}
	}

## Configuration

`ControllerConnector` allows to define a namespace prefix.
If controllers are in `\\App\\Controller` namespace, configure the namespace prefix like this :

	<?php

	$connector = new \Rgsone\Slim\ControllerConnector( new \Slim\Slim() );
	$connector->setNamespacePrefix( '\\App\\Controller' );

And this following route

	$connector->connect( 'GET', '/', 'HomeController:showIndex' );

is translates to

	$connector->connect( 'GET', '/', '\\App\\Controller\\HomeController:showIndex' );

## Author

[rgsone aka Rudy Marc](http://rgsone.com)

## Thanks

to [Josh Lockhart](https://github.com/codeguy) for Slim Framework.

## License

Slim Controller Connector is released under the MIT public license.
