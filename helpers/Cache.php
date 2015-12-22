<?php

namespace nitm\helpers;

use yii\db\ActiveRecord;
use yii\base\Model;
use nitm\helpers\Helper;
use nitm\helpers\ArrayHelper;

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
	public static function setModel($key, $model, $asArray=false, $duration=500, $modelClass=null)
	{
		if(is_object($model)) {
			$parsedModel = static::parseBeforeSet($model);
			if(is_object($model) && $model instanceof \yii\base\Model)
				$className = $model->className();
			else if(is_object($model))
				$className = get_class($model);
			else if(is_array($model) && is_object(current($model)))
				$className = get_class(current($model));

			if($asArray && is_object($model))
				$parsedModel = [$parsedModel];
			else if(!$asArray && ArrayHelper::isIndexed($parsedModel))
				$parsedModel = current($model);

			$model = [
				'_class' => $className,
				'_data' => $parsedModel
			];
		} else {
			if($className = ArrayHelper::getValue($model, '_class') === null) {
				$model = [
					'_class' => $modelClass,
					'_data' => static::parseBeforeSet($model)
				];
			}
		}
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
		//PHP Doesn't support serializing of Closure functions

		$ret_val = [];
		switch(static::exists($key))
		{
			case true:
			$array = static::get($key);
			if(is_array($array)) {
				if(!isset($array['_class'])) {
					echo $key;
					print_r($array);
					exit;
				}
				if(class_exists($array['_class'])) {
					$model = \Yii::createObject($array['_class']);
					if(is_array($array['_data']) && count(array_filter($array['_data'])) >= 1) {
						$ret_val = static::parseAfterGet($array['_data'], $model);
					} else {
						$ret_val = $model;
					}
				}
			} else {
				$ret_val = null;
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
			static::set($key, [
				'_class' => $modelClass,
				'_data' => ArrayHelper::toArray($ret_val)
			], 300);
			break;
		}
		if(is_array($ret_val) && (count($ret_val)) == 1 && !$asArray)
			$ret_val = current($ret_val);
		else if($asArray)
			$ret_val = (is_array($ret_val) && (is_array(current($ret_val)) || is_object(current($ret_val)))) ? $ret_val : [$ret_val];

		return $ret_val;
	}

	protected static function parseBeforeSet($model)
	{
		$ret_val = $model;
		if(is_array($model)) {
			foreach($model as $idx=>$m)
				$ret_val[$idx] = static::parseBeforeSet($m);
		} else {
			if(is_callable($model)) {
				$model = call_user_func($model);
			}
			if(!is_object($model)) {
				return $model;
			}
			$attributes = $model->getAttributes();
			if(is_array($model->getRelatedRecords()))
				$attributes = array_merge($attributes, $model->getRelatedRecords());
			$ret_val = $attributes;
			foreach($attributes as $attribute=>$value)
			{
				if(($relation = \nitm\models\Data::hasRelation($attribute, $model)) !== null) {
					$ret_val[$attribute] = [
						'_relation' => true,
						'_many' => is_array($value),
						'_class' => $relation->modelClass,
						'_data' => static::parseBeforeSet($value)
					];
				} else {
					$ret_val[$attribute] = is_object($value) ? [
						'_class' => get_class($value),
						'_data' => ArrayHelper::toArray($value)
					] : $value;
				}
			 }
		 }
		 return $ret_val;
	}

	protected static function parseAfterGet($array, &$model, $modelClass=null)
	{
		if(ArrayHelper::isIndexed($array)) {
			$className = $model->className();
			$ret_val = array_map(function ($attributes) use($className) {
				return static::parseAfterGet($attributes, \Yii::createObject($className));
			}, $array);
			return $ret_val;
		} else {
			foreach((array)$array as $attribute=>$value)
			{
				if(is_array($value) && ArrayHelper::getValue($value, '_relation') === true) {
					//We already determined that this was a relation. Now is it an array of relations?
					if(ArrayHelper::getValue($value, '_many') === true) {
						$model->populateRelation($attribute, static::parseAfterGet( $value['_data'], \Yii::createObject($value['_class'])));
					} else {
						//If not it's a single related object. Create the object and the poplate any related information
						$model->populateRelation($attribute, static::parseAfterGet($value['_data'], \Yii::createObject($value['_class'])));
					}
				//Some caches support whole objects for the model. In that case simply set the model to the value and return it.
				} else if(is_object($value)) {
					$model = $value;
				} else {
					//We're populating properties for a regular object | model | attribute
					if(is_array($value) && ($modelClass = ArrayHelper::getValue($value, '_class')) !== false) {
						try {
							$model = \Yii::createObject($modelClass, $value);
							$value = $model;
						} catch (\Exception $e) {
							\Yii::warning($e);
						}
					}

					if($model->hasAttribute($attribute))
						$model->setAttribute($attribute, $value);
					else if($model->hasProperty($attribute)){
						try {
							$model->$attribute = $value;
						} catch (\Exception $e) {
							\Yii::warning($e);
						}
					}
				}
			}
			return $model;
		}
	}
}
?>
