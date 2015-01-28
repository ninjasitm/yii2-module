<?php

namespace nitm\helpers;

use yii\db\ActiveRecord;
use yii\base\Model;
use yii\helpers\ArrayHelper;
use nitm\helpers\Helper;

/*
 * Setup model based caching, as PHP doesn't support serialization of Closures
 */
class Cache extends Model
{
	public static $cache;
	private static $_cache = [];
	
	public function cacheKey($model, $idKey, $relation=null, $many=false)
	{
		$id = Helper::concatAttributes($model, $idKey);
		return ($many == true ? 'many' : 'one').'-'.$relation.'-'.(!$id ? $idKey.'-'.$model->getId() : $id);
	}
	
	/**
	 * Cache function that returns caching object
	 */
	public static function cache()
	{
		if(!isset(static::$cache))
		{
			static::$cache = \Yii::$app->hasProperty('cache') ? \Yii::$app->cache : new \yii\caching\FileCache;
		}
		return static::$cache;
	}
	
	public static function exists($key)
	{
		//return isset(static::$_cache[$key]);
		return static::$cache->exists($key);
	}
	
	public function deleteModel($key)
	{
		//echo "Deleting model for: $key<br>";
		if(static::exists($key))
			return static::cache()->delete($key);
		return false;
	}
	
	public static function setModel($key, $model, $duration=5000)
	{
		//static::$_cache[$key] = $model;
		//echo "Setting model for: $key<br>";
		static::$cache->set($key, $model, $duration);
	}
	
	public static function setModelArray($key, $array, $duration=5000)
	{
		static::setModel($key, $array, $duration);
	}
	
	/**
	 * Get a cached model
	 * @param string $key
	 * @return object
	 */
	public static function getModel($key)
	{
		$ret_val = null;
		if(static::$cache->exists($key))
			$ret_val = static::$cache->get($key);
		return $ret_val;
	}
	
	/**
	 * Get a cached array
	 * @param string $key
	 * @param string $property
	 * @return array
	 */
	public static function getModelArray($key, $property=null)
	{
		//PHP Doesn't support serializing of Closure functions so using local object store
		$array = [];
		//switch(isset(static::$_cache[$key]))
		if(static::$cache->exists($key))
			$ret_val = static::$cache->get($key);
		else
			$ret_val = [];
		return $ret_val;
	}
	
	/**
	 * Wrap setting the model to include th className
	 * @param string $key
	 * @param object $model
	 * @return boolean
	 */
	public static function setCachedModel($key, $model)
	{
		return static::setModel($key, [$model->className(), \yii\helpers\ArrayHelper::toArray($model)]);
	}
	
	/**
	 * Wrap setting the model to include th className
	 * @param string $key
	 * @param array $models
	 * @param string $modelClass
	 * @return boolean
	 */
	public static function setCachedModelArray($key, $models, $modelClass)
	{
		return static::setModel($key, [$modelClass, \yii\helpers\ArrayHelper::toArray($models)]);
	}
	
	public static function deleteCachedModel($key)
	{
		return static::deleteModel($key);
	}
	
	/**
	 * Get a cached model
	 * @param string $key
	 * @param string $property
	 * @param string $modelClass
	 * @return instanceof $modelClass
	 */
	public static function getCachedModel($sender, $key, $modelClass=null, $property=null, $options=[])
	{
		//PHP Doesn't support serializing of Closure functions so using local object store
		//switch(static::$cache->exists($key))
		$ret_val = null;
		switch(static::exists($key))
		{
			case true:
			$array = static::getModel($key);
			try {
				$ret_val = new $array[0](array_filter($array[1]));
			} catch (\Exception $e) {
			}
			//$ret_val = static::$cache->get($key);
			break;
			
			default:
			switch(1)
			{
				case !is_null($property) && !is_null($modelClass):
				switch($sender->hasProperty($property))
				{
					case true:
					$ret_val = \nitm\helpers\Relations::getRelatedRecord($property, $sender, $modelClass, @$options['construct']);
					break;
					
					default:
					switch(1)
					{
						case isset($options['find']):
						$find = $modelClass::find();
						foreach($options['find'] as $option=>$params)
						{
							$find->$option($params);
						}
						unset($options['find']);
						$ret_val = $find->one();
						$ret_val = !$ret_val ? new $modelClass(@$options['construct']) : $ret_val;
						break;
						
						case isset($options['construct']):
						$ret_val = new $modelClass($options['construct']);
						break;
						
						default:
						echo $modelClass;
						exit;
						$ret_val = new $modelClass($options);
						break;
					}
					break;
				}
				//static::$cache->set($key, $ret_val, 1000);
				static::setModel($key, [$modelClass, ArrayHelper::toArray($ret_val)]);
				break;
			}
			break;
		}
		return $ret_val;
	}
	
	/**
	 * Get a cached array
	 * @param string $key
	 * @param string $property
	 * @return array
	 */
	public static function getCachedModelArray($sender, $key, $modelClass=null, $property=null, $options=[])
	{
		//PHP Doesn't support serializing of Closure functions so using local object store
		//switch(static::$cache->exists($key))
		$ret_val = [];
		switch(static::exists($key))
		{
			case true:
			$array = static::getModelArray($key);
			
			if((class_exists($array[0])) && (is_array($array[1]) && count(array_filter($array[1])) >= 1))
			{
				try {
					foreach($array[1] as $attributes)
					{
						$ret_val[] = new $array[0]($attributes);
					}
				} catch (\Exception $e) {
					/**
					 * Most likely $array[1] is a single array with attributes
					 */
					 foreach($array[1] as $attributes)
					 {
					 	$ret_val[] = new $array[0]($attributes);
					 }
				}
			}
			else
				$ret_val = [];
			break;
			
			default:
			if(!is_null($property))
			{
				switch(1)
				{
					case array_key_exists($property, $sender->getRelatedRecords()):
					$ret_val = $sender->getRelatedRecords()[$property];
					break;
					
					case $sender->hasProperty($property) && is_array($sender->$property):
					$ret_val =  $sender->$property;
					break;
					
					default:
					$ret_val = $options;
					break;
				}
				//static::$cache->set($key, $ret_val, 1000);
				static::setModelArray($key, [$modelClass, ArrayHelper::toArray($ret_val)]);
				break;
			}
			break;
		}
		return $ret_val;
	}
}
?>