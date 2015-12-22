<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace nitm\components\log;

use Yii;
use yii\mongodb\Connection;
use yii\base\InvalidConfigException;
use yii\di\Instance;
use yii\helpers\VarDumper;
use yii\helpers\ArrayHelper;

/**
 * DbTarget stores log messages in a database table.
 *
 * The database connection is specified by [[db]]. Database schema could be initialized by applying migration:
 *
 * ```
 * yii migrate --migrationPath=@yii/log/migrations/
 * ```
 *
 * If you don't want to use migration and need SQL instead, files for all databases are in migrations directory.
 *
 * You may change the name of the table used to store the data by setting [[logTable]].
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class MongoTarget extends \yii\log\DbTarget
{
	use \nitm\traits\LogTarget;
	
	public $logTable = 'nitm-log';
	public $enableContextMessage = false;

    /**
     * Initializes the DbTarget component.
     * This method will initialize the [[db]] property to make sure it refers to a valid DB connection.
     * @throws InvalidConfigException if [[db]] is invalid.
     */
    public function init()
    {
		if(!($this->db instanceof Connection))
			$this->initDb(true);
    }

	public function initDb($refresh=false)
	{
		if(($this->db instanceof Connection) && !$refresh)
			return $this;

		$this->db = \Yii::createObject(array_merge(['class' => Connection::className()], ArrayHelper::toArray($this->db)));
		return $this;
	}

    /**
     * Stores log messages to DB.
     */
    public function export()
    {
		$indexes = [];
		$currentIndexes = [];

        foreach ($this->messages as $collection=>$message) {
			$collection = is_string($collection) ? array_shift(explode(':', $collection)) : $this->logTable;

			if(!isset($currentIndexes[$collection])) {
				$currentIndexes[$collection] = ($found = array_map(function ($key) {
					return key($key['key']);
				}, $this->db->getCollection($collection)->mongoCollection->getIndexInfo())) != [] ? $found : array_keys($message);
			}

			$message['timestamp'] = !isset($message['timestamp']) ? microtime(true) : $message['timestamp'];

			/**
			 * Gather the indexes
			 */
			$indexes = array_replace($indexes, array_keys($message));

			/**
			 * If new indexes are introduced update the index
			 */
			if($currentIndexes[$collection] != $indexes) {
				try {
					$previous = $this->db->getCollection($collection)->dropAllIndexes();
				} catch(\Exception $e){}
				foreach($indexes as $index) {
					if(is_numeric($message[$index]) || (is_string($message[$index]) && strlen($message[$index]) <= 32))
					{
						try {
							$this->db->getCollection($collection)->createIndex($index);
						} catch(\Exception $e) {
							if(defined('YII_DEBUG')) {
								print_r($e);
								exit;
							}
						}
					}
				}
				$currentIndexes[$collection] = $indexes;
			}

			$this->db->getCollection($collection)->insert($message);
        }
		$this->messages = [];
    }

	protected function getContextMessage()
	{
		if($this->enableContextMessage)
			return parent::getContextMessage();
		else return '';
	}
}
