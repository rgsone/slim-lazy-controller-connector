<?php

/* #######################################################################
//////////////////////////////////////////////////////////////////////////

	Slim LazyControllerConnector is an extension for Slim Framework
	who provides a simple way to connect and 'lazy load' controller(s)
	with Slim route(s)

	@author	rgsone aka rudy marc
	@web	http://rgsone.com

//////////////////////////////////////////////////////////////////////////
####################################################################### */

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
	/** @var array Controllers list */
	protected $_controllers = array();
	/** @var string Namespace prefix */
	protected $_namespacePrefix;

	############################################################################
	//// CONSTRUCTOR ///////////////////////////////////////////////////////////
	############################################################################

	/**
	 * @param \Slim\Slim $slim Slim instance
	 */
	public function __construct( \Slim\Slim $slim )
	{
		$this->_slim = $slim;
		$this->_namespacePrefix = '\\';
	}

	############################################################################
	//// PRIVATE METHOD ////////////////////////////////////////////////////////
	############################################################################

	/**
	 * @param \Closure $callable
	 * @return callable
	 */
	protected function share( \Closure $callable )
	{
		return function () use ( $callable ) {

			static $object;

			if ( null === $object )
			{
				$object = $callable();
			}

			return $object;

		};
	}

	/**
	 * Return the same unique instance of asked controller (singleton)
	 * @param string $name Controller name
	 * @return object Instance of controller
	 */
	protected function getController( $name )
	{
		return $this->_controllers[ $this->_namespacePrefix . $name ]();
	}

	############################################################################
	//// PUBLIC METHOD /////////////////////////////////////////////////////////
	############################################################################

	/**
	 * Connects a route with her controller/action.
	 *
	 * Examples :
	 * connect( 'GET',      '/',		 	 'Controller:action' );
	 * connect( 'GET|POST', '/foo/bar/:id/', 'Controller:action' );
	 * connect( 'GET',      '/foo/',		 'Controller:action', array( function() { echo 'middleware'; } ) );
	 * connect( 'POST',     '/foo/bar/:id/', 'Controller:action', array( 'Middleware1:action', 'Middleware2:action' ) );
	 *
	 * @param string 	 $method 		HTTP Method,
	 * 									ex : 'GET' match GET method
	 * 									multiple methods are allowed, separate them by '|',
	 * 						 			ex : 'GET|POST|UPDATE' match GET, POST, and UPDATE method
	 *
	 * @param string 	 $pattern 		Route pattern, like Slim route pattern,
	 * 						  			ex : '/' or '/foo/bar/:id'
	 *
	 * @param string 	 $controller 	Controller and action to call,
	 * 							 		ex. : 'MyController:myAction'
	 *
	 * @param null|array $middleware 	List of middlewares to call
	 *
	 * @return \Slim\Route
	 */
	public function connect( $method, $pattern, $controller, $middleware = null )
	{
		$httpMethod = explode( '|', $method );

		$ctrlCall = explode( ':', $controller );
		$controllerName = $ctrlCall[0];
		$actionName = $ctrlCall[1];

		$this->register( $controllerName );

		$route = new Route( $pattern, function () use ( $controllerName, $actionName ) {

			$args = func_get_args();
			$ctrl = $this->getController( $controllerName );
			return call_user_func_array( array( $ctrl, $actionName ), $args );

		});

		foreach ( $httpMethod as $m )
		{
			$route->via( $m );
		}

		if ( null !== $middleware && is_array( $middleware ) )
		{
			foreach ( $middleware as $mw )
			{
				if ( is_callable( $mw ) )
				{
					$route->setMiddleware( $mw );
				}
				elseif ( is_string( $mw ) )
				{
					$mwCall = explode( ':', $mw );

					$this->register( $mwCall[0] );

					$route->setMiddleware( function() use ( $mwCall ) {
						call_user_func(array(
							$this->getController( $mwCall[0] ),
							$mwCall[1]
						));
					});
				}
			}
		}

		$this->_slim->router->map( $route );

		return $route;
	}

	/**
	 * Connects a controller with multiples routes.
	 *
	 * Usage :
	 *
	 * ControllerConnector::connectRoutes(
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
	 * @param string $controller Controller name, ex. : 'MyController'
	 * @param array $routes List of routes and their parameters
	 * @param callable Globals middlewares, called for each route
	 */
	public function connectRoutes( $controller, array $routes )
	{
		$this->register( $controller );

		if ( func_num_args() > 2 )
		{
			$globalsMiddlewares = array_slice( func_get_args(), 2 );
		}

		foreach ( $routes as $pattern => $params )
		{
			if ( !isset( $params['action'] ) )
			{
				throw new \Exception( 'route action parameter is require' );
			}

			$route = new Route( $pattern, function() use ( $params, $controller ) {

				$args = func_get_args();
				$ctrl = $this->getController( $controller );
				return call_user_func_array( array( $ctrl, $params['action'] ), $args );

			});

			$params['method'] = ( isset( $params['method'] ) && is_string( $params['method'] ) ) ? $params['method'] : 'GET';
			$methods = explode( '|', $params['method'] );

			foreach ( $methods as $m )
			{
				$route->via( $m );
			}

			if ( isset( $params['name'] ) && is_string( $params['name'] ) )
			{
				$route->name( $params['name'] );
			}

			if ( isset( $params['conditions'] ) && is_array( $params['conditions'] ) )
			{
				$route->conditions( $params['conditions'] );
			}

			$middlewares = ( isset( $globalsMiddlewares ) ) ? $globalsMiddlewares : array();

			$middlewares = ( isset( $params['middlewares'] ) && is_array( $params['middlewares'] ) )
						   ? array_merge( $middlewares, $params['middlewares'] )
						   : $middlewares;

			foreach ( $middlewares as $mw )
			{
				if ( is_callable( $mw ) )
				{
					$route->setMiddleware( $mw );
				}
				elseif ( is_string( $mw ) )
				{
					$call = explode( ':', $mw );
					$this->register( $call[0] );

					$route->setMiddleware( function() use ( $call ) {
						call_user_func(array(
							$this->getController( $call[0] ),
							$call[1]
						));
					});
				}

			}

			$this->_slim->router()->map( $route );
		}
	}

	/**
	 * Register a controller from his name.
	 * @param string $name Controller name, ex. : 'MyController'
	 */
	public function register( $name )
	{
		$controller = $this->_namespacePrefix . $name;

		if ( !array_key_exists( $controller, $this->_controllers ) )
		{
			$this->_controllers[ $controller ] = $this->share( function() use ( $controller ) {

				return new $controller( $this->_slim );

			});
		}
	}

	/**
	 * Calls a method/action of a registered controller
	 *
	 * Example :
	 * $lazyControllerConnector->register( 'MyController' );
	 * $lazyControllerConnector->callAction( 'MyController', 'myAction' );
	 *
	 * @param string $controllerName Controller name
	 * @param string $methodName Method name
	 * @param array|null $params Array of parameters of method if exists
	 */
	public function callAction( $controllerName, $methodName, $params = null )
	{
		$params = ( null !== $params ) ? $params : array();

		call_user_func_array(
			array(
				$this->getController( $controllerName ),
				$methodName
			),
			$params
		);
	}

	/**
	 * Defined a namespace préfix for controllers
	 * If controller namespace is \Foo\Bar\MyController, use
	 * setNamespacePrefix( '\\Foo\\Bar' );
	 * @param string $namespacePrefix Namespace prefix, ex. : '\\Foo\\Bar'
	 */
	public function setNamespacePrefix( $namespacePrefix )
	{
		$this->_namespacePrefix = $namespacePrefix . '\\';
	}
} 
