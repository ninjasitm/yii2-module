<?php

namespace nitm;

use nitm\helpers\Session;
use nitm\models\DB;
use nitm\helpers\ArrayHelper;

class Module extends \yii\base\Module
{	
	/**
	 * @string the module id
	 */
	public $id = 'nitm';
	
	public $controllerNamespace = 'nitm\controllers';
	
	public $useFullnames;
	
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
	 * The log collections that can be displayed
	 */
	public $logCollections = ['nitm-log'];
	
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
	
	/*
	 * @var array options for nitm\models\Alerts
	 */
	public $alerts;
	
	/**
	 * @var array options for importer
	 */
	public $importer;
	
	/*
	 * @var array The arrap mapping for search classes
	 */
	public $searchClassMap = [
	];

	public function init()
	{
		parent::init();
		// custom initialization code goes here
		
		$this->bootstrap();
		Session::del(Session::current);
		
		/**
		 * Aliases for nitm module
		 */
		\Yii::setAlias('nitm', realpath(__DIR__));
	}
	
	public function getSearchClass($modelName)
	{
		return isset($this->searchClassMap[strtolower($modelName)]) ? $this->searchClassMap[strtolower($modelName)] : '\nitm\models\\'.\nitm\traits\Data::properName($modelName);
	}
	
	protected function bootstrap()
	{
		if($this->enableConfig)
			$this->config = \Yii::createObject(array_merge([
				'class' => '\nitm\models\Configer',
				'dir' => './config/ini/',
				'engine' => 'db',
				'container' => 'globals'
			], (array)$this->config));
		
		if($this->enableLogger)
			$this->logger = \Yii::createObject(array_merge([
				'class' => '\nitm\log\Logger',
				'dbName' => DB::getDefaultDbName(),
			], (array)$this->logger));
		
		if($this->enableAlerts)
			$this->alerts = \Yii::createObject(array_merge([
				'class' => '\nitm\helpers\alerts\Dispatcher',
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
		if($this->enableLogger && ($this->logger instanceof \yii\log\Logger)) {
			if($level != null && $level >= 0)
				return (int)$level <= (int)$this->logger->level;
		}
		return false; 
	}
	
	public function commitLog()
	{
		return ($this->enableLogger) ? $this->logger->flush(true) : false;
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
				], $options);
				return $this->logger->log($options, $collectionName);
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
