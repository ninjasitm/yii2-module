<?php

namespace nitm\models\log;

use Yii;

/**
 * This is the model class for collection "lab1-provisioning-log".
 *
 * @property \MongoId|string $_id
 * @property mixed $message
 * @property mixed $level
 * @property mixed $internal_category
 * @property mixed $category
 * @property mixed $timestamp
 * @property mixed $action
 * @property mixed $db_name
 * @property mixed $table_name
 * @property mixed $user_id
 * @property mixed $ip_addr
 * @property mixed $host
 */
class Entry extends \nitm\search\BaseSearch
{
	public static $collectionName = 'nitm-log';
	public static $namespace = "\nitm\models\log";

	public function getStatus()
	{
		$ret_val = 'default';
		switch(1)
		{
			case $this->level >= 4:
			$ret_val = 'danger';
			break;

			case $this->level == 3:
			$ret_val = 'warning';
			break;

			case $this->level == 2:
			$ret_val = 'info';
			break;

			case $this->level == 1:
			$ret_val = 'success';
			break;

			default:
			$ret_val = 'default';
			break;
		}
		return $ret_val;
	}

}
