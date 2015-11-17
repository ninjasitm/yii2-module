<?php

namespace nitm\helpers;

use yii\helpers\Inflector;

class Routes extends \yii\base\Object
{
	//The id of the module these routes will belong to
	public $moduleId;

	/**
	 * Parameter route mapping
	 * Format is an array as follows:
	 * $key is any unique key that groups routes
	 * $parameterizedRoute is the route that will be created for the given controllers
	 * [
	 * 	$key => $parameterizedRoute
	 * 	...
	 * ]
	 */
	public $map = [];

	public $controllers  = [];

	//Should routes be pluralized?
	public $pluralize = true;

	public $globalOnly = false;

	/**
	 * [create description]
	 * @method create
	 * @param  array $parameters The parameter controller map
	 * @param  array $map   	 The parameter route mapping. See $map property for specifics
	 * @param  string $moduleId  The ID of the module
	 * @param  boolean $pluralize Should routes be pluralized?
	 * @return array            Controller route mapping
	 */
	public function create($parameters, $map=[], $moduleId = null, $pluralize=null, $globalOnly=null)
	{
		$ret_val = [];
		$map = self::getMap($map);
		$parameters = self::getParameters($parameters);
		$moduleId = self::getModuleId($moduleId);
		$pluralize = self::getShouldPluralize($pluralize);
		$globalOnly = self::getIsGlobalOnly($globalOnly);
		foreach($parameters as $params=>$group)
		{
			//If there were no controller rgroups specified then the $group is the key for the parameters
			if(is_int($params)) {
				$params = $group;
				$controllers = self::getControllers();
			} else {
				$controllers = self::getControllersFromMap($group);
			}
			if(empty($controllers))
				continue;
			$parameterizedRoute = ArrayHelper::getValue($map, $params, null);
			if($params == 'none')
				$parameterizedRoute = [$parameterizedRoute => '<controller>/index'];
			if(is_null($parameterizedRoute)) {
				continue;
			}
			if(isset($moduleId) && !empty($moduleId) && ($globalOnly === false))
				$ret_val += self::getRoutes($controllers, $parameterizedRoute, $moduleId);
			$ret_val += self::getRoutes($controllers, $parameterizedRoute, $moduleId, true);
		}
		ksort($ret_val);
		return $ret_val;
	}

	/**
	 * Add rules to the route being constructed
	 * @method addRules
	 * @param  string   $controller The name of the controller we're adding rules for
	 * @param  array   $rules       The rules to add to the $map
	 * @param  array   $map         The rule mapping to append the $rules to
	 * @param  array   $controllers The array of controllers to append controller to
	 */
	public function addRules($controller, $rules, &$map=[], &$controllers=[])
	{
		if(isset($this) && empty($map))
			$map =& $this->map;
		$map += $rules;
		if(isset($this) && empty($controllers))
			$controllers =& $this->controllers;
		if(is_string($controller))
			array_push($controllers, $controller);
		else if(is_array($controller))
			$controllers += $controller;
		return true;
	}

	/**
	 * Get the controllers from a mapped controller list
	 * @method getControllersFromMap
	 * @param  array                $group The controller grouping according to static::pluralize format
	 * @return array                       The controllers
	 */
	protected function getControllersFromMap($group)
	{
		$ret_val = [];
		foreach((array)$group as $alias=>$controllers) {
			if(isset($controllers['alias']))
				$controllers = [$alias => $controllers];
			$ret_val = array_merge($ret_val, (array)$controllers);
		}
		return $ret_val;
	}

	public function getControllerMap($for=[], $controllers=[])
	{
		$allControllers = self::getControllers($controllers);
		return array_intersect_key($allControllers, array_flip($for));
	}

	/**
	 * Get the route entry
	 * @method getRoute
	 * @param  array   $controllers The controllers that make use of this route
	 * @param  string|array   $route      The route specification.
	 *                                    If it is a string it'll be the key for the route.
	 *                                    If it is an array then the key will be the parameteriozed route and the value will be the destination
	 * @param  string   $moduleId    The moduleId to use
	 * @param  boolean   $global      Is this a global route? One not specified by the moduleId?
	 * @return array                 The calculated route
	 */
	protected function getRoutes($controllers, $route, $moduleId=null, $global=false)
	{
		$routes = [];
		if(is_array(current($controllers))) {
			$globalRoute = is_array($route) ? current($route) : null;
			//We're dealing with an aliased controller spec
			foreach($controllers as $alias=>$group)
			{
				unset($group['alias']);
				$realRoute = is_null($globalRoute) ? $alias : $globalRoute;
				preg_match('/(<(action):?([^>]+)?>)/', $realRoute, $actionMatches);
				$destinationArray = explode('/', $realRoute);
				$action = empty($action) ? '' : $action[0];
				//Check to see if a controller has been specified in the destination.
				preg_match('/(<(controller):?([^>]+)?>)/', $realRoute, $controllerSpec);
				//If <controller> was specified replace it with the alias
				$destination = empty($controllerSpec) ? $alias : preg_replace('/(<(controller):?([^>]+)?>)/', $alias, $realRoute);
				if(count($destinationArray) == 1)
					$destination .= '/<action>';
				$route = is_array($route) ? key($route) : $route;
				$routes += self::getRoute($group, [$route => $destination], $moduleId, $global);
			}
		} else {
			//We're dealing with independent controllers
			$routes += self::getRoute($controllers, $route, $moduleId, $global);
		}
		return $routes;
	}

	public function getRoute($controllers, $route, $moduleId=null, $global=false)
	{
		$moduleId = self::getModuleId($moduleId);
		$moduleId = is_string($moduleId) ? $moduleId.'/' : '';
		$key = is_array($route) ? key($route) : $route;
		$key = ($global ? $moduleId : '').str_replace(['%controllers%'], '('.implode('|', $controllers).')', $key);
		$destination = $moduleId.(is_array($route) ? current($route) : '<controller>/<action>');
		return [$key => $destination];
	}

	/**
	 * Pluralize the given controllers and index by original value
	 * @method pluralize
	 * @param  array    $controllers The controllers to pluralize
	 * @return array                 The pluralized controllers indexed by the original values
	 */
	public function pluralize(&$controllers=[])
	{
		$controllers = empty($controllers) && isset($this) ? $this->controllers : $controllers;
		$ret_val = [];
		foreach($controllers as $controller=>$options)
		{
			$controller = is_numeric($controller) ? $options : $controller;
			$ret_val[$controller] = [$controller, Inflector::pluralize($controller)];
			if(is_array($options)) {
				$alias = ArrayHelper::getValue($options, 'alias', false);
				if(is_array($alias))
					$ret_val[$controller] = array_shift(array_map(function($a) {return [$a, Inflector::pluralize($a)]; }, $alias));
				if($alias !== false)
					$ret_val[$controller]['alias'] = $controller;
			}
		}
		$controllers = self::getControllersFromMap($ret_val);
		if(isset($this))
			$this->controllers = $controllers;
		return $ret_val;
	}

	private function getModuleId($moduleId='')
	{
		return empty($moduleId) && isset($this) ? $this->moduleId : $moduleId;
	}

	private function &getParameters($parameters=[])
	{
		$ret_val = empty($parameters) && isset($this) ? $this->parameters : $parameters;
		return $ret_val;
	}

	public function &getControllers($controllers=[])
	{
		$ret_val = empty($controllers) && isset($this) ? $this->controllers : $controllers;
		return $ret_val;
	}

	private function &getMap($map=[])
	{
		$ret_val = empty($map) && isset($this) ? $this->map : $map;
		return $ret_val;
	}

	private function getShouldPluralize($pluralize=null)
	{
		return (boolean) ($pluralize === null && isset($this) ? $this->pluralize : $pluralize);
	}

	private function getIsGlobalOnly($globalOnly=null)
	{
		return (boolean) ($globalOnly === null && isset($this) ? $this->globalOnly : $globalOnly);
	}
}
?>
