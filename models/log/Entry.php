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
 * @property mixed $user
 * @property mixed $user_id
 * @property mixed $ip_addr
 * @property mixed $host
 */
class Entry extends \nitm\search\BaseMongo
{	
	public static $collectionName = 'nitm-log';
	public static $namespace = "\nitm\models\log";
	
    /**
     * @inheritdoc
     */
    public static function collectionName()
    {
        return static::$collectionName;
    }

    /**
     * @inheritdoc
     */
    public function attributes()
    {
        return [
            '_id',
            'message',
            'level',
            'internal_category',
            'category',
            'timestamp',
            'action',
            'db_name',
            'table_name',
            'user',
            'user_id',
            'ip_addr',
            'host',
			'request_method',
			'user_agent',
			'cookie_id',
			'fingerprint',
			'error_level',
			'ua_family',
			'ua_version',
			'os_family',
			'os_version',
			'device_family'
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['message', 'level', 'internal_category', 'category', 'timestamp', 'action', 'db_name', 'table_name', 'user', 'user_id', 'ip_addr', 'host', 'user_agent', 'request_method', 'cookie_id', 'fingerprint'], 'safe']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            '_id' => Yii::t('app', 'ID'),
            'message' => Yii::t('app', 'Message'),
            'level' => Yii::t('app', 'Log Level'),
            'internal_category' => Yii::t('app', 'Internal Category'),
            'category' => Yii::t('app', 'Category'),
            'request_method' => Yii::t('app', 'Request Method'),
            'user_agent' => Yii::t('app', 'User Agent'),
            'cookie_id' => Yii::t('app', 'Cookie ID'),
            'fingerprint' => Yii::t('app', 'Fingerprint'),
            'timestamp' => Yii::t('app', 'Timestamp'),
            'action' => Yii::t('app', 'Action'),
            'db_name' => Yii::t('app', 'Db Name'),
            'table_name' => Yii::t('app', 'Table Name'),
            'user' => Yii::t('app', 'User'),
            'user_id' => Yii::t('app', 'User ID'),
            'ip_addr' => Yii::t('app', 'Ip Addr'),
            'host' => Yii::t('app', 'Host'),
        ];
    }
	
	public function getStatus()
	{
		$ret_val = 'default';
		switch(1)
		{
			case $this->error_level >= 4:
			$ret_val = 'danger';
			break;
			
			case $this->error_level == 3:
			$ret_val = 'warning';
			break;
			
			case $this->error_level == 2:
			$ret_val = 'info';
			break;
			
			case $this->error_level == 1:
			$ret_val = 'success';
			break;
			
			default:
			$ret_val = 'default';
			break;
		}
		return $ret_val;
	}
	
}
