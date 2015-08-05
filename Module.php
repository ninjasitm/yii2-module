<?php

namespace nitm;

use nitm\helpers\Session;
use nitm\models\DB;
use nitm\helpers\ArrayHelper;
use nitm\components\Logger;
use nitm\importer\Importer;
use nitm\helpers\alerts\Dispatcher;

class Module extends \yii\base\Module
{	
	/**
	 * @string the module id
	 */
	public $id = 'nitm';
	
	public $controllerNamespace = 'nitm\controllers';
	
	public $useFullnames;
	
	/**
	 * Model caching options
	 * Disable this if using a slower caching system
	 */
	public $useModelCache = true;
	
	/**
	 * Should the configuration engine be loaded?
	 */
	public $enableConfig = true;
	
	/**
	 * Should the logging engine be loaded?
	 */
	public $enableLogger = true;
	
	/**
	 * Should the importing engine be loaded?
	 */
	public $enableImporter = true;
	
	/**
	 * Should the alerts engine be loaded?
	 */
	public $enableAlerts = true;
	
	/*
	 * @var array options for nitm\models\Configer
	 */
	public $config;
	
	/*
	 * @var array options for nitm\models\Logger
	 */
	public $logger;
	
	/**
	 * The log collections that can be displayed
	 */
	public $logCollections = ['nitm-log'];
	
	/*
	 * @var array options for nitm\models\Alerts
	 */
	public $alerts;
	
	/**
	 * @var array options for importer
	 */
	public $importer;
	
	/**
	 * Use this to map the carious types to their appropriate classes
	 */
	public $classMap = [];
	
	/*
	 * @var array The arrap mapping for search classes
	 */
	public $searchClassMap = [];
	
	//For Logger events
	const LOGGER_EVENT_PREPARE = 'nitm.logger.prepare';
	const LOGGER_EVENT_PROCESS = 'nitm.logger.process';

	public function init()
	{
		parent::init();
		// custom initialization code goes here
		$this->bootstrap();
		
		/**
		 * Aliases for nitm module
		 */
		\Yii::setAlias('nitm', realpath(__DIR__));
		//Check and start the session;
		Session::touchSession();
	}
	
	public function getSearchClass($modelName)
	{
		return isset($this->searchClassMap[strtolower($modelName)]) ? $this->searchClassMap[strtolower($modelName)] : '\nitm\models\\'.\nitm\traits\Data::properName($modelName);
	}
	
	protected function bootstrap()
	{
		if($this->enableConfig)
			$this->config = \Yii::createObject(array_merge([
				'class' => '\nitm\components\Configer',
				'engine' => 'db',
				'container' => 'globals'
			], (array)$this->config));
		
		if($this->enableLogger)
			$this->logger = \Yii::createObject(array_merge([
				'class' => '\nitm\components\Logger',
				'dbName' => DB::getDefaultDbName(),
			], (array)$this->logger));
		
		if($this->enableAlerts)
			$this->alerts = \Yii::createObject(array_merge([
				'class' => '\nitm\components\Dispatcher',
			], (array)$this->alerts));
		
		if($this->enableImporter)
			$this->importer = \Yii::createObject(array_merge([
				'class' => '\nitm\importer\Importer',
			], (array)$this->importer));
	}
	
	/**
	 * Determine whether this level is loggable
	 */
	public function canLog($level=null)
	{
		return $this->enableLogger ? $this->logger->canLog($level) : false;
	}
	
	public function commitLog()
	{
		return $this->enableLogger ? $this->logger->trigger(Logger::EVENT_END) : false;
	}
	
	public function log($level, $options, $modelClass)
	{
		if($this->canLog($level)) {
			try {
				
				$collectionName = $this->getCollectionName($options);
				$options = array_merge([
					'db_name' => \nitm\models\DB::getDbName(),
					'level' => $level,
					'timestamp' => time(),
					'collectionName' => $collectionName
				], $options);
				$this->logger->trigger(Logger::EVENT_PROCESS, new \yii\base\Event([
					'data' => $options
				]));
			} catch (\Exception $e) {
				if(defined("YII_DEBUG"))
					throw $e;
			}
		}
		return false;
	}
	
	public function getCollectionName(&$from=[])
	{
		return ArrayHelper::remove($from, 'collection_name', (($this->logCollections != []) ? $this->logCollections[0] : 'nitm-log'));
	}
}
