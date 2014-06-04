<?php

/* ############################################################################
///////////////////////////////////////////////////////////////////////////////

	LazyControllerConnector is an extension for Slim Framework
	who provides a simple way to connect and 'lazy load' controller(s)
	and middleware(s) with a Slim route(s).

	@author	rgsone aka rudy marc
	@web	http://rgsone.com

///////////////////////////////////////////////////////////////////////////////
############################################################################ */

namespace Rgsone\Slim;

use Slim\Route;

/**
 * Class LazyControllerConnector
 * @package Rgsone\Slim
 */
class LazyControllerConnector
{
	############################################################################
	//// PRIVATE PROPERTY //////////////////////////////////////////////////////
	############################################################################

	/** @var \Slim\Slim Slim instance */
	protected $_slim;
	/** @var array */
	protected $_controllers = array();

	############################################################################
	//// CONSTRUCTOR ///////////////////////////////////////////////////////////
	############################################################################

	/**
	 * @param \Slim\Slim $slim Slim instance
	 */
	public function __construct( \Slim\Slim $slim )
	{
		$this->_slim = $slim;
	}

	############################################################################
	//// PRIVATE METHOD ////////////////////////////////////////////////////////
	############################################################################

	/**
	 * @param string $class
	 * @return callable
	 */
	protected function share( $class )
	{
		return function () use ( $class )
		{
			static $object;

			if ( null === $object )
			{
				$object = new $class( $this->_slim );
			}

			return $object;
		};
	}

	/**
	 * Registers a controller if he is not already registered
	 * and returns his instance
	 * @param string $name Controller name
	 * @return mixed Instance of registered controller
	 */
	protected function register( $controller )
	{
		if ( array_key_exists( $controller, $this->_controllers ) )
		{
			return $this->_controllers[ $controller ]();
		}

		$this->_controllers[ $controller ] = $this->share( $controller );

		return $this->_controllers[ $controller ]();
	}

	/**
	 * Binds a list of middleware to a Slim route
	 * @param \Slim\Route $route Slim Route instance
	 * @param array $middlewares Array of callable middleware
	 */
	protected function bindMiddlewares( \Slim\Route $route, array $middlewares )
	{
		foreach ( $middlewares as $mw )
		{
			if ( is_callable( $mw ) )
			{
				$route->setMiddleware( $mw );
			}
			elseif ( is_string( $mw ) )
			{
				$mwSplit = explode( ':', $mw );
				$mwClass = $mwSplit[0];
				$mwMethod = $mwSplit[1];

				$route->setMiddleware( function() use ( $mwClass, $mwMethod ) {
					$middleware = $this->register( $mwClass );
					call_user_func( array( $middleware, $mwMethod ) );
				});
			}
		}
	}

	############################################################################
	//// PUBLIC METHOD /////////////////////////////////////////////////////////
	############################################################################

	/**
	 * Connects a route with a controller/action and middleware(s).
	 *
	 * Usage :
	 * connect( 'GET', '/', 'Controller:action' );
	 * connect( 'GET|POST', '/foo/bar/:id/', 'Controller:action' );
	 * connect( 'GET', '/foo/', 'Controller:action', array( function() { echo 'middleware'; } ) );
	 * connect( 'POST', '/foo/bar/:id/', 'Controller:action', array( 'Middleware1:action', 'Middleware2:action' ) );
	 *
	 * @param string 	 $httpMethod 	HTTP Method,
	 * 									ex. : 'GET' for GET method
	 * 									multiple methods are allowed, separate them by a pipe '|'
	 * 						 			ex. : 'GET|POST|UPDATE' for GET, POST, and UPDATE method
	 *
	 * @param string 	 $pattern 		Route pattern, like Slim route pattern,
	 * 						  			ex. : '/' or '/foo/bar/:id'
	 *
	 * @param string 	 $callable 		Controller and action to call,
	 * 							 		ex. : 'MyController:myAction'
	 *
	 * @param null|array $middlewares 	An array of middlewares to call
	 *
	 * @return \Slim\Route Instance of \Slim\Route
	 */
	public function connect( $httpMethod, $pattern, $callable, array $middlewares = array() )
	{
		## creates Slim Route instance

		$split = explode( ':', $callable );
		$className = $split[0];
		$methodName = $split[1];

		$routeCallable = function() use ( $className, $methodName ) {
			$controller = $this->register( $className );
			return call_user_func_array( array( $controller, $methodName ), func_get_args() );
		};

		$route = new Route( $pattern, $routeCallable );

		## binds HTTP method(s)

		$methods = explode( '|', $httpMethod );
		foreach ( $methods as $m )
		{
			$route->via( $m );
		}

		## binds middleware(s)

		if ( !empty( $middlewares ) )
		{
			$this->bindMiddlewares( $route, $middlewares );
		}

		$this->_slim->router->map( $route );

		return $route;
	}

	/**
	 * Connects a controller with multiples routes.
	 *
	 * Usage :
	 * connectRoutes(
	 * 		'MyController',
	 * 		array(
	 *			'/foo' => array(
	 * 				'action' => 'myAction',
	 * 				'param' => '',
	 * 				'param' => '',
	 * 				...
	 * 			)
	 * 		)
	 * 		[, middleware, middleware, ...]
	 * );
	 *
	 * @param string 	$controller Controller name, ex. : 'MyController'
	 * @param array 	$routes 	List of routes and their parameters
	 * @param callable 				Globals middlewares, called for each route
	 */
	public function connectRoutes( $controller, array $routes )
	{
		## get global middlewares if exists

		if ( func_num_args() > 2 )
		{
			$globalMiddlewares = array_slice( func_get_args(), 2 );
		}

		## parse routes

		foreach ( $routes as $pattern => $params )
		{
			if ( !isset( $params['action'] ) )
			{
				throw new \Exception( 'route action parameter is required' );
			}

			## creates route

			$methodName = $params['action'];

			$routeCallable = function() use ( $controller, $methodName ) {
				$instance = $this->register( $controller );
				return call_user_func_array( array( $instance, $methodName ), func_get_args() );
			};

			$route = new Route( $pattern, $routeCallable );

			## binds HTTP methods

			$params['method'] = ( isset( $params['method'] ) && is_string( $params['method'] ) ) ? $params['method'] : 'GET';
			$methods = explode( '|', $params['method'] );

			foreach ( $methods as $m )
			{
				$route->via( $m );
			}

			## binds route name

			if ( isset( $params['name'] ) && is_string( $params['name'] ) )
			{
				$route->name( $params['name'] );
			}

			## binds route conditions

			if ( isset( $params['conditions'] ) && is_array( $params['conditions'] ) )
			{
				$route->conditions( $params['conditions'] );
			}

			## binds middlewares

			$middlewares = ( isset( $globalMiddlewares ) ) ? $globalMiddlewares : array();
			$middlewares = ( isset( $params['middlewares'] ) && is_array( $params['middlewares'] ) )
						   ? array_merge( $middlewares, $params['middlewares'] )
						   : $middlewares;

			if ( !empty( $middlewares ) )
			{
				$this->bindMiddlewares( $route, $middlewares );
			}

			## map to router

			$this->_slim->router()->map( $route );
		}
	}

	/**
	 * Calls a method/action of a controller
	 *
	 * Usage :
	 * callAction( 'MyController', 'myAction' );
	 * callAction( 'MyController', 'myAction', array( 'my', 'parameters', 11 ) );
	 *
	 * @param string $controller Controller name
	 * @param string $method Method name
	 * @param array $params Array of parameters
	 */
	public function callAction( $controller, $method, array $params = array() )
	{
		$instance = $this->register( $controller );
		return call_user_func_array( array( $instance, $method ), $params );
	}
} 
