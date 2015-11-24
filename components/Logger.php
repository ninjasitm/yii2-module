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
	const EVENT_START = 'nitm.logger.prepare';
	const EVENT_PROCESS = 'nitm.logger.process';
	const EVENT_END = 'nitm.logger.end';

	public function init()
	{
		parent::init();
		$this->currentUser = (\Yii::$app->hasProperty('user') && \Yii::$app->user->getId()) ? \Yii::$app->user->getIdentity() : new \nitm\models\User(['username' => (php_sapi_name() == 'cli' ? 'console' : 'web')]);

		if(!$this->dispatcher instanceof \yii\log\Dispatcher)
			$this->initDispatcher();

		$this->attachToEvents([
			self::EVENT_START => [$this, 'prepare'],
			self::EVENT_PROCESS => [$this, 'process'],
			self::EVENT_END => [$this, 'flush']
		]);
	}

	public function initTargets($refresh=false)
	{
		switch($this->type)
		{
			case self::LT_MONGO:
			$class = log\MongoTarget::className();
			break;

			case self::LT_DB:
			$class = log\DbTarget::className();
			break;

			default:
			$class = log\FileTarget::className();
			break;
		}
		$options = ['class' => $class, 'levels' => 1];
		if($this->db && !empty($this->db))
			$options += ArrayHelper::toArray($this->db);
		return \Yii::createObject($options);
	}

	public function initDispatcher()
	{
		if(\Yii::$app->get('log') && is_array($this->dispatcher) && isset($this->dispatcher['targets'])) {
			$targets = array_map(function ($config) {
				return \Yii::createObject($config);
			}, $this->dispatcher['targets']);
			$this->dispatcher = \Yii::$app->log;
			$this->dispatcher->targets = $targets;
			\Yii::$app->log->targets = array_merge(\Yii::$app->log->targets, $targets);
		}
		else if(is_array($this->dispatcher))
		{
			$this->dispatcher = \Yii::createObject(array_merge([
				'class' => '\yii\log\Dispatcher'
			], ArrayHelper::toArray($this->dispatcher)));
		}
		else
			$this->dispatcher = \Yii::$app->log->dispatcher;
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
	 * Prepare a log event
	 */

	public function prepare($event) {
		return false;
	}

	/**
	  * Proces a log event
	  * @param \yii\base\Event $event
	  * @return boolean
	  */
	public function process($event)
	{
		$action = $event->sender->getScenario();
		$event->data = array_merge((array)$event->data,  [
			'internal_category' => $this->getCategoryText(ArrayHelper::getValue($event->data, 'internal_category', null), $event->sender->getScenario()),
			'level' => $this->getlevel($event->sender->getScenario()),
			'category' => $this->getCategory(ArrayHelper::getValue($event->data, 'category', null), $event->sender->getScenario()),
			'action' => \Yii::$app->getModule('nitm')->getAlert()->store()->getStoredAction($event),
			'timestamp' => microtime(true),
			'table_name' => $event->sender->isWhat(),
			'message' => implode(' ', [
				"Succesfully {$action}d",
				$event->sender->isWhat(),
				': '.$event->sender->title()."[".$event->sender->getId()."]\n\nChanged values: \n".json_encode(ArrayHelper::getValue($event, 'changedAttributes', ''), JSON_PRETTY_PRINT)
			])
		]);
		$event->handled = $this->log($event->data, ArrayHelper::remove($event->data, 'collectionName', null));
		return $event->handled;
	}

	protected function getLevel($scenario)
	{
		$ret_val = 1;
		switch($scenario)
		{
			case 'create':
			case 'update':
			case 'delete':
			case 'disable':
			case 'resolve':
			case 'close':
			case 'complete':
			case 'approve':
			$ret_val = 3;
			break;

			case 'view':
			$ret_val = 2;
			break;
		}
		return $ret_val;
	}

	protected function getCategoryText($category=null, $scenario=null)
	{
		switch($scenario)
		{
			case 'view':
			$ret_val = 'User Activity';
			break;

			default:
			$ret_val = is_null($category) ? 'User Action' : $category;
			break;
		}
		return $ret_val;
	}

	protected function getCategory($category=null, $scenario=null)
	{
		switch($scenario)
		{
			case 'view':
			$ret_val = 'user-ctivity';
			break;

			default:
			$ret_val = is_null($category) ? 'user-action' : $category;
			break;
		}
		return $ret_val;
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
				'message', 'level', 'category', 'timestamp', 'internal_category'
			]);
			$array = array_replace($keys, array_intersect_key((array)$array, $keys)) + (array)array_diff_key((array)$array, $keys) + $this->getBaseInfo();

			if(is_string($collectionName))
				\Yii::$app->get('log')->getLogger()->messages[$collectionName.':'.uniqid()] = $array;
			else
				\Yii::$app->get('log')->getLogger()->messages[] = $array;
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
