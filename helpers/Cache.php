<?php

namespace nitm\helpers;

use yii\db\ActiveRecord;
use yii\base\Model;
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
		return Cache::getKey($model, $idKey, $relation, $many);
	}
	
	public function getKey($model, $idKey, $relation=null, $many=false)
	{
		$ret_val = [($many == true ? 'many' : 'one'), $relation];
		;
		if(is_string($model) || is_numeric($model))
			$ret_val[] = $model;
		else if(!is_null($idKey) && !empty($id = Helper::concatAttributes($model, $idKey)))
			$ret_val[] = $id;

		return implode('-', $ret_val);
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
		return static::cache()->exists($key);
	}
	
	public static function get($key)
	{
		return static::cache()->get($key);
	}
	
	public static function set($key, $value, $duration=300)
	{
		return static::cache()->set($key, $value, $duration);
	}
	
	public static function delete($key)
	{
		return static::cache()->delete($key);
	}
	
	/**
	 * Wrap setting the model to include th className
	 * @param string $key
	 * @param object $model
	 * @return boolean
	 */
	public static function setModel($key, $model, $asArray=false, $duration=500)
	{
		if(!$asArray && is_object($model))
			$model = [$model->className(), \yii\helpers\ArrayHelper::toArray($model)];
		if($asArray && is_object($model))
			$model = [$model->className(), [\yii\helpers\ArrayHelper::toArray($model)]];
		else if(is_array($model) && current($model) instanceof \yii\base\Model)
			$model = [current($model)->className(), \yii\helpers\ArrayHelper::toArray($model)];
		return static::set($key, $model, $duration);
	}
	
	/**
	 * Get a cached array
	 * @param string $key
	 * @param string $property
	 * @return array
	 */
	public static function getModel($sender, $key, $asArray=false, $modelClass=null, $property=null, $options=[])
	{
		//PHP Doesn't support serializing of Closure functions so using local object store
		//switch(static::cache()->exists($key))
		$ret_val = [];
		switch(static::exists($key))
		{
			case true:
			$array = static::get($key);
			if(($array != []) && (class_exists($array[0])) && (is_array($array[1]) && count(array_filter($array[1])) >= 1))
			{
				$array[1] = is_array(current($array[1])) ? $array[1] : [$array[1]];
				try {
					foreach($array[1] as $attributes)
						$ret_val[] = new $array[0]($attributes);
				} catch (\Exception $e) {
					/**
					 * Most likely $array[1] is a single array with attributes
					 */
					 foreach($array[1] as $attributes)
					 {
						 $model = new $array[0];
						 foreach($attributes as $attribute=>$value)
						 	if($model->hasMethod('get'.$attribute))
								$model->populateRelation($attribute, $value);
							else if($model->hasAttribute($attribute))
								$model->setAttribute($attribute, $value);
							else if($model->hasProperty($attribute))
								$model->$attribute = $value;
					 }
				}
			} else {
				$modelClass = is_null($modelClass) ? get_called_class() : $modelClass;
				$ret_val = new $modelClass();
			}
			break;
			
			default:
			switch(1)
			{
				case array_key_exists($property, $sender->getRelatedRecords()):
				$ret_val = \nitm\helpers\Relations::getRelatedRecord($property, $sender, $modelClass, @$options['construct']);
				break;
				
				case $sender->hasProperty($property):
				case $sender->hasAttribute($property):
				$ret_val =  $sender->$property;
				break;
				
				default:
				switch(1)
				{
					case isset($options['find']):
					$find = $modelClass::find();
					
					foreach($options['find'] as $option=>$params)
						$find->$option($params);
						
					unset($options['find']);
					$ret_val = $find->one();
					$ret_val = !$ret_val ? new $modelClass(@$options['construct']) : $ret_val;
					break;
					
					case isset($options['construct']):
					$ret_val = new $modelClass($options['construct']);
					break;
					
					default:
					$ret_val = new $modelClass($options);
					break;
				}
				break;
			}
			static::set($key, [$modelClass, ArrayHelper::toArray($ret_val)], 300);
			break;
		}
		
		if(is_array($ret_val) && (count($ret_val)) == 1 && !$asArray)
			$ret_val = current($ret_val);
		else if($asArray)
			$ret_val = (is_array($ret_val) && (is_array(current($ret_val)) || is_object(current($ret_val)))) ? $ret_val : [$ret_val];
			
		return $ret_val;
	}
}
?>