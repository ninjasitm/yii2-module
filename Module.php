<?php

namespace nitm;

use nitm\helpers\Session;
use nitm\models\DB;
use nitm\helpers\ArrayHelper;
use nitm\components\Logger;
use nitm\importer\Importer;
use nitm\components\Dispatcher;

class Module extends \yii\base\Module implements \yii\base\BootstrapInterface
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
	 * Should the configuration admin interface be enabled?
	 */
	public $enableConfigAdmin = false;

	/**
	 * Should the logging engine be loaded?
	 */
	public $enableLogger = true;

	/**
	 * Should the logging route be loaded?
	 */
	public $enableLogs = true;
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
		/**
		 * Aliases for nitm module
		 */
		\Yii::setAlias($this->id, realpath(__DIR__));
		//Check and start the session;
		Session::touchSession();
	}

	public function bootstrap($app)
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

		/**
		 * Setup urls
		 */
        $app->getUrlManager()->addRules($this->getUrls(), false);
	}

	public function getSearchClass($modelName)
	{
		return isset($this->searchClassMap[strtolower($modelName)]) ? $this->searchClassMap[strtolower($modelName)] : '\nitm\models\\'.\nitm\traits\Data::properName($modelName);
	}

	/**
	 * Generate routes for the module
	 * @method getUrls
	 * @param  string  $id The id of the module
	 * @return array     	The routes
	 */
	public function getUrls($id = 'nitm')
	{
		$parameters = [];
		$routeHelper = new \nitm\helpers\Routes([
			'moduleId' => $id,
			'map' => [
				'type' => '<controller>/<action>/<type>',
				'action-only' => '<controller>/<action>',
				'none' => '<controller>'
			],
			'controllers' => []
		]);
		$parameters = [];
		if($this->enableConfigAdmin) {
			$routeHelper->addRules('configuration', [
				'config-index' => ['configuration' => 'configuration/index'],
				'config-engine' => ['configuration/load/<engine>' => 'configuration'],
				'config-container' => ['configuration/load/<engine:\w+>/<container>' => 'configuration']
			]);
			$parameters += [
				'config-index' => ['configuration'],
				'config-engine' => ['configuration'],
				'config-container' => ['configuration']
			];
			$parameters['action-only'] = $routeHelper->getControllerMap(['configuration']);
		}
		if($this->enableLogs) {
			$routeHelper->addRules('log', [
				'log-index-base' => ['log' => 'log/index'],
				'log-type' => ['log/<type:(!index)>' => 'log/index'],
				'log-index' => ['log/index' => 'log/index'],
				'log-index-type' => 'log/index/<type>'
			]);
			$parameters += [
				'log-index-base' => ['log'],
				'log-type' => ['log'],
				'log-index' => ['log'],
				'log-index-type' => ['log']
			];
		}
		$routes = $routeHelper->create($parameters);
		return $routes;
	}

	/**
	 * Can the module log this information?
	 * @method canLog
	 * @param  int $level The log level
	 * @return boolean        Whether the module can log
	 */
	public function canLog($level=null)
	{
		return (bool) ($this->enableLogger ? $this->logger->canLog($level) : false);
	}

	/**
	 * Commit the entire log tree
	 * @method commitLog
	 * @return boolean    The result of the log operation
	 */
	public function commitLog()
	{
		return $this->enableLogger ? $this->logger->trigger(Logger::EVENT_END) : false;
	}

	/**
	 * Add a message to the log queue
	 * @method log
	 * @param  string|int $level   The level to log at
	 * @param  array $options The optional extra data to log
	 * @param  object $sender  The model to use for this log operation
	 * @return boolean          [description]
	 */
	public function log($level, $options, $sender)
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
				$this->logger->log($options, $collectionName);
			} catch (\Exception $e) {
				//if(defined("YII_DEBUG"))
					throw $e;
			}
		}
		return true;
	}

	public function getCollectionName(&$from=[])
	{
		return ArrayHelper::remove($from, 'collection_name', (($this->logCollections != []) ? $this->logCollections[0] : 'nitm-log'));
	}

	/**
	 * Can this user send this alert?
	 * @method canSendAlert
	 * @return boolean       Can the user send the alert?
	 */
	public function canSendAlert()
	{
		$ret_val =  $this->enableAlerts
			&& \Yii::$app->getRequest()->get(Dispatcher::SKIP_ALERT_FLAG) != true;
		return (bool) $ret_val;
	}

	/**
	 * Get the alert object
	 * @method getAlert
	 * @param  [type]   $force [description]
	 * @return [type]          [description]
	 */
	public function getAlert($force=false)
	{
		if(!isset($this->alerts)) {
			$this->alerts = \Yii::createObject(array_merge([
				'class' => '\nitm\components\Dispatcher',
			], (array)$this->alerts));
		}
		return $this->alerts;
	}

	/**
	 * Get the logger
	 * @method getLogger
	 * @param  [type]    $force [description]
	 * @return [type]           [description]
	 */
	public function getLogger($force=false)
	{
		if(!isset($this->logger)) {
			$this->logger = \Yii::createObject(array_merge([
				'class' => '\nitm\components\Configer',
			], (array)$this->logger));
		}
		return $this->logger;
	}

	/**
	 * Get the config component
	 * @method getConfiger
	 * @param  [type]      $force [description]
	 * @return [type]             [description]
	 */
	public function getConfiger($force=false)
	{
		if(!isset($this->config)) {
			$this->config = \Yii::createObject(array_merge([
				'class' => '\nitm\components\Configer',
			], (array)$this->config));
		}
		return $this->config;
	}
}
