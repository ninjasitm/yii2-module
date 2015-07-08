<?php
namespace nitm\traits;

use nitm\helpers\Session;
use nitm\helpers\Helper;
use nitm\helpers\Cache as CacheHelper;
use nitm\helpers\ArrayHelper;

 /**
  * Configuration traits that can be shared
  */
trait Configer {
	
	/**
	 * Get a setting value 
	 * @param string $setting the locator for the setting
	 */
	public static function setting($setting=null)
	{
		$hierarchy = explode('.', $setting);
		switch($hierarchy[0])
		{
			case '@':
			array_pop($hierarchy[0]);
			break;
			
			case static::isWhat():
			case null:
			$hierarchy = sizeof($hierarchy) == 1 ? static::isWhat() : $hierarchy;
			break;
			
			default:
			array_unshift($hierarchy, static::isWhat());
			break;
		}		
		return \Yii::$app->getModule('nitm')->config->get(implode('.', $hierarchy));
	}
	
	/*
	 * Initialize configuration
	 * @param string $container
	 */
	public static function initConfig($container=null)
	{
		$module = \Yii::$app->getModule('nitm');
		$container = is_null($container) ? $module->config->container : $container;
		switch(1)
		{
			case !isset($model->config->settings[$container]):
			case !$module->config->exists($container):
			case $module->config->exists($container) && (count($module->config->get($container) == 0)):
			case ($container == $module->config->container) && (!$module->config->exists(Session::settings)):
			
			$module->config->setEngine($module->config->engine);
			$module->config->setType($module->config->engine, $container);
				
			if(!$module->config->exists($container)) {
				$config = $module->config->getConfig($module->config->engine, $container, true);
				$module->config->set($container, $config);
			}
			break;
		}
		
	}
}
?>