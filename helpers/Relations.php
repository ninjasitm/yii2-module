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
			if(is_object($ret_val) && $model->hasAttribute($name)) $ret_val->load($model->$name, false);
			break;
			
			/**
			 * This provides support for ElasticSearch which doesn't properly populate records. May be bad codding but for now this works
			 */
			case $model->hasAttribute($name):
			switch($array === true)
			{
				case true:
				$ret_val = array_map(function ($attributes) use ($className) {
					return new $className($attributes);
				}, (array)$model->$name);
				break;
				
				default:
				$ret_val = is_string($className) ? new $className($model->$name) : $className;
				break;
			}
			break;
			
			default:
			$ret_val = is_string($className) ? new $className($options) : $className;
			break;
		}
		return $ret_val;
	}
}

?>