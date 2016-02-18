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
	public function setting($setting='@')
	{
		$module = \Yii::$app->getModule('nitm');
		if($module && $module->hasComponent('config'))
		{
			$hierarchy = explode('.', $setting);
			$isWhat = isset($this) ? $this->isWhat(true) : static::isWhat(true);
			switch($hierarchy[0])
			{
				case '@':
				array_pop($hierarchy);
				break;

				case $isWhat:
				case null:
				$hierarchy = sizeof($hierarchy) == 1 ? $isWhat : $hierarchy;
				break;

				default:
				array_unshift($hierarchy, $isWhat);
				break;
			}
			return $module->config->get(implode('.', array_filter($hierarchy)));
		}
		return null;
	}

	/*
	 * Initialize configuration
	 * @param string $container
	 */
	public static function initConfig($container=null)
	{
		$module = \Yii::$app->getModule('nitm');
		if($module && $module->hasComponent('config'))
		{
			$container = is_null($container) ? $module->config->container : $container;
			if(!$module->config->containerExists($container))
			{
				$module->config->setType($container);
				$config = $module->config->getConfig($container, true);
				$module->config->set($container, $config);
			}
		}
	}
}
?>
