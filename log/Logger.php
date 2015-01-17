<?php

namespace nitm\log;

use yii\base\Model;
use yii\helpers\ArrayHelper;
use nitm\helpers\Session;
use nitm\helpers\Network;

class Logger extends \yii\log\Logger
{
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
	public $collectionName;
	public $oldCollectionName;
	public $type;
	public $currentUser;
	
	public function init()
	{
		parent::init();
		$this->currentUser = (\Yii::$app->hasProperty('user') && \Yii::$app->user->getId()) ? \Yii::$app->user->getIdentity() : new \nitm\models\User(['username' => (php_sapi_name() == 'cli' ? 'console' : 'web')]);
			
		if(!$this->dispatcher instanceof \yii\log\Dispatcher)
			$this->initDispatcher();
	}
	
	public function behaviors()
	{
		$behaviors = [
		];
		return array_merge(parent::behaviors(), $behaviors);
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
		if(is_array($this->dispatcher))
		{
			$this->dispatcher = \Yii::createObject(array_merge([
				'class' => '\yii\log\Dispatcher'
			], ArrayHelper::toArray($this->dispatcher)));
		}
		else 
			$this->dispatcher = \Yii::$app->log;
		return $this;
	}
	
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
	
	//function to add db transaction
	public function log($array, $collectionName=null)
	{
		if(\yii::$app->getModule('nitm')->enableLogger && count($array))
		{
			$parser = (\UAParser\Parser::create());
			$r = $parser->parse(!\Yii::$app->request->userAgent ? $_SERVER['SERVER_SOFTWARE'] : \Yii::$app->request->userAgent);
			$baseInfo = [
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
			
			$array = array_merge($baseInfo, (array)$array);
			
			if(is_string($collectionName))
				$this->messages[$collectionName.':'.uniqid()] = $array;
			else
				$this->messages[] = $array;
		}
		return $this;
	}
	//end fucntion
}
// end log class 
?>
