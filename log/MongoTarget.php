<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace nitm\log;

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
            if (!is_string($message['message'])) {
                $text = VarDumper::export($message['message']);
            }
			
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
			if($currentIndexes[$collection] != $indexes){
				$previous = $this->db->getCollection($collection)->dropAllIndexes();
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
    }
	
    /**
	 * ***Modified this version to support level and category checking
     * Processes the given log messages.
     * This method will filter the given messages with [[levels]] and [[categories]].
     * And if requested, it will also export the filtering result to specific medium (e.g. email).
     * @param array $messages log messages to be processed. See [[Logger::messages]] for the structure
     * of each message.
     * @param boolean $final whether this method is called at the end of the current application
     */
    public function collect($messages, $final)
    {
		/**
		 * Extract the keys for each log entry so that filter can work properly
		 */
		$extracted = [];
		array_walk($messages, function ($value, $key) use(&$extracted){
			$extracted['keys'][$key] = array_keys($value);
			$extracted['values'][$key] = array_values($value);
		});
		
		/**
		 * Now remap the keys to the messages so that insert can work properly
		 */
		if(is_array($values = ArrayHelper::getValue($extracted, 'values', [])))
			if(is_array($filtered = $this->filterMessages($values, $this->getLevels(), $this->categories, $this->except))) {
				$filtered = array_merge($this->messages, $filtered);
			
				array_walk($filtered, function ($value, $key) use(&$messages, $extracted){
					$messages[$key] = array_combine($extracted['keys'][$key], $value);
				});
			}
		
		$this->messages = $messages;
		
		$extracted = $messages = [];
		
        $count = count($this->messages);
        if ($count > 0 && ($final || $this->exportInterval > 0 && $count >= $this->exportInterval)) {
            if (($context = $this->getContextMessage()) !== '') {
                $this->messages[] = [$context, Logger::LEVEL_INFO, 'application', YII_BEGIN_TIME];
            }
            // set exportInterval to 0 to avoid triggering export again while exporting
            $oldExportInterval = $this->exportInterval;
            $this->exportInterval = 0;
            $this->export();
            $this->exportInterval = $oldExportInterval;
        }
    }
	
	protected function getContextMessage()
	{
		if($this->enableContextMessage)
			return parent::getContextMessage();
		else return '';
	}
}
