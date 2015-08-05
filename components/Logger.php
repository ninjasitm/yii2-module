<?php

namespace nitm\components;

use yii\base\Model;
use yii\helpers\ArrayHelper;
use nitm\helpers\Session;
use nitm\helpers\Network;

class Logger extends \yii\log\Logger
{
	use \nitm\traits\EventTraits;
	
	//constant data
	const LT_FILE = 'file';
	const LT_DB = 'db';
	const LT_MONGO = 'mongo';
	
	//public data
	public $db;
	public $targets;
	public $level = 0;
	public $traceLevel = 1;
	public $dbName;
	public $collectionName = 'no-log-selected';
	public $oldCollectionName;
	public $type;
	public $currentUser;
	
	//For Logger events
	const EVENT_START = 'nitm.logger.process';
	const EVENT_PROCESS = 'nitm.logger.process';
	const EVENT_END = 'nitm.logger.end';
	
	public function init()
	{
		parent::init();
		$this->currentUser = (\Yii::$app->hasProperty('user') && \Yii::$app->user->getId()) ? \Yii::$app->user->getIdentity() : new \nitm\models\User(['username' => (php_sapi_name() == 'cli' ? 'console' : 'web')]);
			
		if(!$this->dispatcher instanceof \yii\log\Dispatcher)
			$this->initDispatcher();
			
		$this->attachToEvents([
			self::EVENT_START => [$this, 'process'],
			self::EVENT_PROCESS => [$this, 'process'],
			self::EVENT_END => [$this, 'flush']
		]);
	}
	
	public function initTargets($refresh=false)
	{
		switch($this->type)
		{
			case self::LT_MONGO:
			$class = MongoTarget::className();
			break;
			
			case self::LT_DB:
			$class = DbTarget::className();
			break;
			
			default:
			$class = FileTarget::className();
			break;
		}
		return \Yii::createObject(array_merge(['class' => $class, 'levels' => 1], ArrayHelper::toArray($this->db)));
	}
	
	public function initDispatcher()
	{
		if(\Yii::$app->get('log') && is_array($this->dispatcher) && isset($this->dispatcher['targets'])) {
			$targets = array_map(function ($config) {
				return \Yii::createObject($config);
			}, $this->dispatcher['targets']);
			$this->dispatcher = \Yii::$app->log;
			$this->dispatcher->targets = array_merge($this->dispatcher->targets, $targets);
		}
		else if(is_array($this->dispatcher))
		{
			$this->dispatcher = \Yii::createObject(array_merge([
				'class' => '\yii\log\Dispatcher'
			], ArrayHelper::toArray($this->dispatcher)));
		}
		else
			$this->dispatcher = \Yii::$app->log;
		return $this;
	}
	
	/**
	 * Temporarily change the collection name
	 * @param string $newName
	 */
	public function changeCollectionName($newName=null)
	{
		if(is_null($newName)) {
			$this->collectionName = $this->oldCollectionName;
			$this->oldCollectionName = null;
		}
		else {
			$this->oldCollectionName = $this->collectionName;
			$this->collectionName = $newName;
		}
		foreach($this->dispatcher->targets as $target)
			$target->logTable = $this->collectionName;
	}
	
	//- end write
	
	/**
	  * Proces a log event
	  * @param \yii\base\Event $event
	  * @return boolean
	  */
	public function process($event)
	{
		$event->handled = $this->log($event->data, ArrayHelper::remove($event->data, 'collectionName', null));
		return $event->handled;
	}
	
	/**
	 * Log data
	 * @param array $array
	 * @param string $collectionName
	 * @return Logger $this
	 */
	public function log($array, $collectionName=null)
	{
		if(is_array($array) && $array != [])
		{
			$keys = array_flip([
				'message', 'level', 'internal_category', 'timestamp', 'category'
			]);
			$array = array_replace($keys, array_intersect_key((array)$array, $keys)) + (array)array_diff_key((array)$array, $keys) + $this->getBaseInfo();
			
			if(is_string($collectionName))
				$this->messages[$collectionName.':'.uniqid()] = $array;
			else
				$this->messages[] = $array;
		}
		return $this;
	}
	//end fucntion
	
	/**
	 * Determine whether this level is loggable
	 * @param int $level
	 * @return boolean the data can be stored for logging
	 */
	public function canLog($level=null)
	{
		if($level != null && $level >= 0)
			return (int)$level <= (int)$this->level;
		else
			return false; 
	}
	
	/**
	 * Return some general log info
	 * @return array
	 */
	protected function getBaseInfo()
	{
		$parser = (\UAParser\Parser::create());
		$r = $parser->parse(!\Yii::$app->request->userAgent ? $_SERVER['SERVER_SOFTWARE'] : \Yii::$app->request->userAgent);
		return [
			'ua_family' => $r->ua->family,
			'ua_version' => implode('.', array_filter([$r->ua->major, $r->ua->minor])).($r->ua->patch ? '-'.$r->ua->patch : ''),
			'os_family' => $r->os->family,
			'os_version' => implode('.', array_filter([$r->os->major, $r->os->minor])).($r->os->patch ? '-'.$r->os->patch : ''),
			'device_family' => $r->device->family,
			'request_method' => \Yii::$app->request->method,
			'user_agent' => \Yii::$app->request->userAgent,
			'action' => 'log',
			'db_name' => \nitm\models\DB::getDefaultDbName(),
			'table_name' => 'logger',
			'user' => $this->currentUser->username,
			'user_id' => $this->currentUser->getId(),
			'ip_addr' => !\Yii::$app->request->userIp ? 'localhost' : \Yii::$app->request->userIp,
			'host' => !\Yii::$app->request->userHost ? 'localhost' : \Yii::$app->request->userHost,
			'error_level' => 0
		];
	}
}
// end log class 
?>
