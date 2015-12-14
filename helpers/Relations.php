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
				if($model->hasAttribute($name) || $model->hasProperty($name) && (count($options) == 0)) {
					$attributes = $model->$name;
				} else
					$attributes = $options;
				switch($array === true)
				{
					case true:
					if(is_array($attributes) && (!is_array(current($attributes)) && !is_object(current($attributes))))
						$ret_val = array_map(function ($properties) use ($className) {
							$model = new $className();
							if(is_array($properties))
								$model->setAttributes($properties);
							return $model;
						}, (array)$attributes);
					else
						$ret_val = $attributes;
					break;

					default:
					if(is_array($attributes) && (!is_array(current($attributes)) && !is_object(current($attributes))))
						$ret_val = current($attributes);
					if(is_array($attributes) && isset($attributes['construct'])) {
						$construct = ArrayHelper::getValue($attributes, 'construct', []);
						$ret_val = is_string($className) ? new $className($construct) : $attributes;
					} else if(is_object($attributes))
						$ret_val = $attributes;
					else
						$ret_val = new $className;
					break;
				}
			}
			else
				$ret_val = null;
			$model->populateRelation($name, $ret_val);
			break;
		}
		return $ret_val = $array == true ? (array)$ret_val : $ret_val;
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

	public function getCachedRelation($idKey='id', $many=false, $modelClass=null, $relation=null, $options=[], &$model=null, $duration=120)
	{
		if(isset($this) && is_null($model))
			$model = $this;

		$many = $many === true ? true : false;
		$relation = is_null($relation) ? \nitm\helpers\Helper::getCallerName() : $relation;
		$modelClass = is_null($modelClass) ? $model->getRelation($relation)->modelClass : $modelClass;
		$key = Cache::cacheKey($model, $idKey, $relation, $many);

		if(Cache::exists($key)) {
			$ret_val = Cache::getModel($this, $key, $many, $modelClass, $relation, $options);
		}
		else {
			$ret_val = self::getRelatedRecord($relation, $model, $modelClass, $options, $many);
			self::setCachedRelation($idKey, $many, $modelClass, [$relation, $ret_val], $model, $duration);
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
	public function setCachedRelation($idKey='id', $many=false, $modelClass=null, $relation=null, &$model=null, $duration=120)
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

		return Cache::setModel(Cache::cacheKey($model, $idKey, $relation, $many), $related, $many, $duration, $modelClass);
	}

	/**
	 * Delete a cached relation. Either a model or array of models
	 * @param Object $model The model this relation is attached to
	 * @param string|array $idKey The properties that makeup the cacheKey
	 * @param string $relation The name of the relation
	 * @param boolean $many Is this an array of models?
	 * @param return boolean value was deleted
	 */
	public function deleteCachedRelation($idKey='id', $many=false, $modelClass=null, $relation=null, &$model=null)
	{
		return Cache::delete(Cache::cacheKey($model, $idKey, $relation, $many));
	}

	/**
	 * Resolve a cached relation. Either a model or array of models
	 * @param string|array $idKey  The properties that makeup the cacheKey
	 * @param boolean $many Is this an array of models?
	 * @param string $modelClass The string name of the class
	 * @param string $relation The name of the relation
	 * @param Object $model The model this relation is attached to
	 * @param return array|object of class modelClass
	 */
	public function resolveRelation($idKey, $modelClass, $useCache=false, $many=false, $options=[], $relation=null)
	{
		$relation = is_null($relation) ? \nitm\helpers\Helper::getCallerName() : $relation;
		if($useCache)
			return self::getCachedRelation($idKey, $many, $modelClass, $relation, [], $this, 120);
		else
			return self::getRelatedRecord($relation, $this, $modelClass, [], $many);
	}
}

?>
