<?php

/**
 * @package mhdevent/yii2-module
 *
 * Log traits for the Data model
 */
namespace nitm\traits;

use nitm\helpers\Cache as RealCache;
use yii\helpers\ArrayHelper;

trait Log {

    /**
	 * Log a transaction to the logger
	 * @param string $action
	 * @param string $message
	 * @param int $level
	 * @param string|null $table
	 * @param string|null $db
	 * @param string $category
	 * @param string $internalCategory
	 * @param string $collectionName
	 * @return boolean
	 */
	protected static function log($action, $message, $level=1, $options=[])
	{
		if(\Yii::$app->getModule('nitm')->enableLogger)
		{
			$options = array_merge([
				'internal_category' => 'user-activity',
				'category' => 'Model Activity',
				'table_name' => static::tableName(),
				'message' => $message,
				'action' => $action,
			], $options);
			return \Yii::$app->getModule('nitm')->log($level, $options, static::className());
		}
		return false;
	}

	/**
	 * Commit the logs to the database
	 * @return boolean
	 */
	protected static function commitLog()
	{
		return \Yii::$app->getModule('nitm')->commitLog();
	}

}
