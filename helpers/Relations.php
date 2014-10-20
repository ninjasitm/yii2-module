<?php

namespace nitm\helpers;

/**
 * This class makes it easier to instantiate an editor widget by providing options 
 * for differrent types of widgets. THis class is based on the Redactor editor
 * by imperavi
 *
 * This wrapper uses air buttons by default with a minimal toolbar
 */

class Relations
{
	public static function getRelatedRecord($name, $model, $className, $options=[])
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
			$ret_val = is_string($className) ? new $className($model->$name) : $className;
			break;
			
			default:
			$ret_val = is_string($className) ? new $className($options) : $className;
			break;
		}
		return $ret_val;
	}
}

?>