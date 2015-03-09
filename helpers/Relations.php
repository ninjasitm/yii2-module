<?php

namespace nitm\helpers;

/**
 * Helper functions for handling relations
 */

class Relations
{
	/**
	 * Get a relation. Either a model or array of models
	 * @param string $name The name of the relation
	 * @param Object $model The model this relation is attached to
	 * @param string $className The string name of the class
	 * @param array $options The options usedfor custructing a model if necessary
	 * @param boolean $many Is this an array of models?
	 * @param return array|object of class modelClass
	 */
	public static function getRelatedRecord($name, &$model, $className=null, $options=[], $array=false)
	{
		switch(1)
		{
			case isset($model->getRelatedRecords()[$name]) && !empty($model->getRelatedRecords()[$name]):
			$ret_val = $model->getRelatedRecords()[$name];
			/**
			 * A little hack for elasticSearch since the relations are stored as nested objects
			 * Pulling relations directly doesn't always work. need to investigate
			 */
			if(is_object($ret_val) && $model->hasAttribute($name) && is_array($model->$name)) $ret_val->load($model->$name, false);
			break;
			
			/**
			 * This provides support for ElasticSearch which doesn't properly populate records. May be bad codding but for now this works
			 */
			default:
			if(isset($className) && class_exists((string)$className))
			{
				if($model->hasAttribute($name) || $model->hasProperty($name) && (count($options) == 0))
					$attributes = $model->$name;
				else
					$attributes = $options;
				switch($array === true)
				{
					case true:
					$ret_val = array_map(function ($properties) use ($className) {
						$model = new $className();
						if(is_array($properties))
							$model->setAttributes($properties);
						return $model;
					}, (array)$attributes);
					break;
					
					default:
					if(is_object($attributes) && $attributes->className() == $className)
						$ret_val = $attributes;
					else
					{
						$construct = ArrayHelper::getValue($attributes, 'construct', []);
						$ret_val = is_string($className) ? new $className($construct) : $attributes;
					}
					break;
				}
			}
			else
				$ret_val = null;
			break;
		}
		$model->populateRelation($name, $ret_val);
		return $ret_val;
	}
	
	/**
	 * Get a cached relation. Either a model or array of models
	 * @param string|array $idKey  The properties that makeup the cacheKey
	 * @param boolean $many Is this an array of models?
	 * @param string $modelClass The string name of the class
	 * @param string $relation The name of the relation
	 * @param Object $model The model this relation is attached to
	 * @param return array|object of class modelClass
	 */
	
	public function getCachedRelation($idKey='id', $many=false, $modelClass=null, $relation=null, $options=[], &$model=null)
	{
		if(isset($this) && is_null($model))
			$model = $this;
		
		$many = $many === true ? true : false;
		$relation = is_null($relation) ? \nitm\helpers\Helper::getCallerName() : $relation;
		$modelClass = is_null($modelClass) ? $model->getRelation($relation)->modelClass : $modelClass;
		$key = Cache::cacheKey($model, $idKey, $relation, $many);
		
		if(Cache::exists($key))
		{
			//Disabled due to Yii framework inability to return statistical relations
			//if(static::className() != $className)
				//$ret_val->with(['count', 'newCount']);
			$cacheFunction = $many === true ? 'getCachedModelArray' : 'getCachedModel';
			$ret_val = Cache::$cacheFunction($this, $key, $modelClass, $relation, $options);
		}
		else
		{
			$ret_val = self::getRelatedRecord($relation, $model, $modelClass, $options, $many);
			self::setCachedRelation($idKey, $many, $modelClass, [$relation, $ret_val], $model);
		}
		return $ret_val;
	}
	
	/**
	 * Set a cached relation. Either a model or array of models
	 * @param string|array $idKey  The properties that makeup the cacheKey
	 * @param boolean $many Is this an array of models?
	 * @param string $modelClass The string name of the class
	 * @param string $relation The name of the relation
	 * @param Object $model The model this relation is attached to
	 * @param return array|object of class modelClass
	 */
	public function setCachedRelation($idKey='id', $many=false, $modelClass=null, $relation=null, &$model=null)
	{
		if(isset($this) && is_null($model))
			$model = $this;
		
		if(is_array($relation)) {
			$related = array_pop($relation);
			$relation = array_pop($relation);
		}
		else {
			$relation = is_null($relation) ? \nitm\helpers\Helper::getCallerName() : $relation;
			$related = self::getRelatedRecord($relation, $model, $modelClass, [], $many);
		}
		$modelClass = is_null($modelClass) ? $model->getRelation($relation)->modelClass : $modelClass;
		$cacheFunction = $many === true ? 'setCachedModelArray' : 'setCachedModel';
		
		return Cache::$cacheFunction(Cache::cacheKey($model, $idKey, $relation, $many), $related, $modelClass);
	}
}

?>