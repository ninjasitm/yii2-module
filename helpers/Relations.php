<?php

namespace nitm\helpers;

/**
 * Helper functions for handling relations
 */

class Relations
{
	public static function getRelatedRecord($name, $model, $className=null, $options=[], $array=false)
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
			if(class_exists($className))
			{
				if($model->hasAttribute($name) || $model->hasProperty($name) && (sizeof($options) == 0))
					$attributes = $model->$name;
				else
					$attributes = $options;
				switch($array === true)
				{
					case true:
					$ret_val = array_map(function ($attributes) use ($className) {
						return new $className($attributes);
					}, (array)$attributes);
					break;
					
					default:
					if(is_object($attributes) && $attributes->className() == $className)
						$ret_val = $attributes;
					else
					{
						$construct = !is_array($attributes) ? [] : $attributes;
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
}

?>