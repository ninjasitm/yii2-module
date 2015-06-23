<?php

namespace nitm\helpers\alerts;

use Yii;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use yii\base\Event;
use nitm\helpers\Cache;
use nitm\models\Alerts;
use nitm\models\Entity;

/**
 * This is the alert dispatcher class.
 */
class Dispatcher extends \yii\base\Component
{
	public $mode;
	public $mailPath =' @app/mail';
	public $mailLayoutsPath = '@nitm/mail/layouts';
	public $mailViewsPath = '@nitm/views/alerts';
	public $useFullnames = true;
	public $defaultMessage = '%user% performed %action% on %id%';
	public $defaultMobileMessage = '%user% performed %action% on %id%';
	public $defaultSubject = '%user% performed %action% on %id%';
	public $matchCriteria = [
		'remote_for',
		'remote_id',
		'remote_type',
		'action',
		'priority',
	];	
	
	protected static $is = 'alerts';
	protected static $_subject;
	protected static $_body;
	
	protected $_data = [];
	protected $_originUserId;
	protected $_message;
	protected $_notifications = [];
	protected $_sendCount = 0;
	
	private $_event;
	private $_prepared = false;
	private $_alerts;
	private $_alertStack = [];
	private $_oldLayouts = [];
	private $_supportedLayouts = ['html', 'text'];
	
	const BATCH = 'batch';
	const SINGLE = 'single';
	
	public function init()
	{
		$this->_data = new DispatcherData;
	}
	
	public function reset()
	{
		$this->_data = new DispatcherData;
		$this->_prepared = false;
		$this->resetMailerLayout();
		$this->_message = null;
		$this->_sendCount = 0;
	}
	
	public function start($event, $for='any', $priority='any')
	{
		if(!\Yii::$app->getModule('nitm')->enableAlerts)
			return;

		if($event->handled)
			return;

		$this->_event = $event;

		$this->_data->processEventData($this->_event->data, $this);
		
		$this->_alertStack[$this->_data->getKey($this->_event->sender)] = [
			'remote_type' => $this->_event->sender->isWhat(),
			'remote_for' => $for,
			'priority' => $priority,
			'action' => $this->_event->sender->getScenario()
		];
	}
	
	/**
	 * Process the alerts according to $message and $parameters
	 * @param array $event = The event triggering the action
	 * @param array $options = the subject and mobile/email messages:
	 * [
	 *		'subject' => String
	 *		'message' => [
	 *			'email' => The email message
	 *			'mobile' => The mobile/text message
	 *		]
	 * ]
	 */
	public function process($event)
	{
		if(!\Yii::$app->getModule('nitm')->enableAlerts)
			return;
		
		if($event->handled)
			return;
			
		if($event->sender->hasMethod('getAlertOptions'))
			$event->data = array_replace_recursive($event->sender->getAlertOptions($event), (array)$event->data);
		
		$this->_event = $event;
		$this->prepare($event);
		
		$this->_data->criteria('remote_id', $this->_event->sender->getId());
		if($this->_data->criteria('action')) {
			$this->prepare($event);
			if($this->isPrepared()) {
				//First check to see if this specific alert exits
				if(count($this->_event->data))
					$this->sendAlerts($this->_event->data, ArrayHelper::getValue($this->_event->data, 'owner_id', null));
				$event->handled = true;
			} else 
				if((defined('YII_ENV') && YII_ENV == 'dev') && (defined('YII_DEBUG') && YII_DEBUG))
					throw new \yii\base\Exception("No alert preparation was done!");
		} else
			if((defined('YII_ENV') && YII_ENV == 'dev') && (defined('YII_DEBUG') && YII_DEBUG))
				throw new \yii\base\Exception("Need an action to process the alert");
	}
	
	public function prepare($event)
	{
		$this->_data->processEventData($this->_event->data, $this);
		$basedOn = array_merge(
			(array)ArrayHelper::remove($this->_alertStack, $this->_data->getKey($this->_event->sender)), 
			(array)$this->_event->data
		);
		
		if(is_array($basedOn))
		{
			$basedOn['action'] = ArrayHelper::getValue($basedOn, 'action', ($this->_event->sender->isNewRecord === true ? 'create' : 'update'));
			$this->_data->reportedAction = $basedOn['action'].'d';
			
			$this->_data->criteria(array_intersect_key(ArrayHelper::getValue($basedOn, 'criteria', []), array_flip($this->matchCriteria)));
			
			$this->_event->data = array_diff_key($basedOn, array_flip($this->matchCriteria));
			$this->_prepared = true;
		}
	}
	
	public function isPrepared()
	{
		return $this->_prepared === true;
	}
	
	/**
	 * Find alerts for the specific criteia originally provided
	 * @param int $originUserId Is the ID of the user for the object which triggered this alert sequence
	 * @return \yii\db\Query
	 */
	public function findAlerts($originUserId)
	{
		$this->_originUserId = $originUserId;
		
		return $this->findSpecific($this->_data->criteria())
			->union($this->findOwner($this->_originUserId, $this->_data->criteria()))
			->union($this->findListeners($this->_data->criteria()))
			->union($this->findGlobal($this->_data->criteria()))
			->indexBy('user_id')
			->with('user')
			->asArray()
			->all();
	}
	
	/**
	 * Find alerts for the specific criteia originally provided
	 * @param array $criteria
	 * @return \yii\db\Query
	 */
	public static function findSpecific(array $criteria)
	{
		unset($criteria['user_id']);
		return Alerts::find()->select('*')
			->where($criteria)
			->andWhere([
				'user_id' => \Yii::$app->user->getIdentity()->getId()
			])
			->indexBy('user_id')
			->with('user');
	}
	
	/**
	 * If the user wants to recieve alerts that they send then do so
	 * @param array $criteria
	 * @return \yii\db\Query
	 */
	public static function findSelf($author_id, array $criteria)
	{
		$criteria['user_id'] = $author_id;
		$criteria['action'] = 'i_'.$this->_data->criteria('action');
		$criteria['remote_type'] = array_unique([ArrayHelper::getValue($criteria, 'remote_type', 'any'), 'any']);
		$criteria['remote_for'] = array_unique([ArrayHelper::getValue($criteria, 'remote_for', 'any'), 'any']);
		$criteria['priority'] = array_unique([ArrayHelper::getValue($criteria, 'priority', 'any'), 'any']);
		$remoteWhere = [];
		if(isset($criteria['remote_id']))
		{
			$remoteWhere = ['or', 'remote_id='.$criteria['remote_id'], ' remote_id IS NULL'];
			unset($criteria['remote_id']);
		}
		return Alerts::find()->select('*')
			->where($criteria)
			->andWhere($remoteWhere)
			->indexBy('user_id')
			->with('user');
	}
	
	/**
	 * Find alerts for the specific criteia originally provided
	 * @param array $criteria
	 * @return \yii\db\Query
	 */
	public static function findOwner($author_id, array $criteria)
	{
		$criteria['user_id'] = $author_id;
		$criteria['action'] .= '_my';
		$criteria['remote_type'] = array_unique([ArrayHelper::getValue($criteria, 'remote_type', 'any'), 'any']);
		$criteria['remote_for'] = array_unique([ArrayHelper::getValue($criteria, 'remote_for', 'any'), 'any']);
		$criteria['priority'] = array_unique([ArrayHelper::getValue($criteria, 'priority', 'any'), 'any']);
		$remoteWhere = [];
		if(isset($criteria['remote_id']))
		{
			$remoteWhere = ['or', 'remote_id='.$criteria['remote_id'], ' remote_id IS NULL'];
			unset($criteria['remote_id']);
		}
		return Alerts::find()->select('*')
			->where($criteria)
			->andWhere($remoteWhere)
			->indexBy('user_id')
			->with('user');
	}
	
	/**
	 * This searches for users who are listening for activity 
	 * Based on the remote_type, action and priority
	 * @param array $criteria
	 * @return \yii\db\Query
	 */
	public static function findListeners(array $criteria)
	{
		unset($criteria['user_id']);
		$criteria['remote_type'] = array_unique([ArrayHelper::getValue($criteria, 'remote_type', 'any'), 'any']);
		$criteria['remote_for'] = array_unique([ArrayHelper::getValue($criteria, 'remote_for', 'any'), 'any']);
		$criteria['action'] = array_unique([ArrayHelper::getValue($criteria, 'action', 'any'), 'any']);
		$criteria['priority'] = array_unique([ArrayHelper::getValue($criteria, 'priority', 'any'), 'any']);
		$remoteWhere = [];
		if(isset($criteria['remote_id']))
		{
			$remoteWhere = ['or', 'remote_id='.$criteria['remote_id'], ' remote_id IS NULL'];
			unset($criteria['remote_id']);
		}
		
		return Alerts::find()->select('*')
			->orWhere($criteria)
			->andWhere($remoteWhere)
			->andWhere([
				'not', ['user_id' => \Yii::$app->user->getIdentity()->getId()]
			])
			->indexBy('user_id')
			->with('user');
	}
	
	/**
	 * Find global listeners for this criteria 
	 * @param array $criteria
	 * @return \yii\db\Query
	 */
	public static function findGlobal(array $criteria)
	{
		$criteria['global'] = true;
		$criteria['user_id'] = null;
		$criteria['remote_type'] = [ArrayHelper::getValue($criteria, 'remote_type', 'any'), 'any'];
		$criteria['action'] = [ArrayHelper::getValue($criteria, 'action', 'any'), 'any'];
		$criteria['priority'] = [ArrayHelper::getValue($criteria, 'priority', 'any'), 'any'];
		unset($criteria['remote_id']);
		return Alerts::find()->select('*')
			->orWhere($criteria)
			->indexBy('user_id')
			->with('user');
	}
	
	public function sendAlerts($compose, $ownerId)
	{
		$alerts = $this->findAlerts($ownerId);
		
		$to = [
			'global' => [],
			'individual'=> [],
			'owner' => []
		];
		//Build the addresses
		switch(is_array($alerts) && count($alerts))
		{
			case true:
			$this->setMailerLayout();
			//Organize by global and individual alerts
			foreach($alerts as $idx=>$alert)
			{
				switch(1)
				{
					case $alert['global'] == true:
					/**
					 * Only send global emails based on what the user preferrs in their profile. 
					 * For specific alerts those are based ont he alert settings
					 */
					$users = $this->_data->getUsers();
					$to['global'] = array_merge_recursive($to['global'], $this->_data->getAddresses($alert['methods'], $users, true));
					break;
					
					case $alert['user']['id'] == $this->_originUserId:
					$to['owner'] = array_merge_recursive($to['owner'], $this->_data->getAddresses($alert['methods'], $alert['user']));
					break;
					
					default:
					$to['individual'] = array_merge_recursive($to['individual'], $this->_data->getAddresses($alert['methods'], $alert['user']));;
					break;
				}
			}
			
			foreach($to as $scope=>$types)
			{
				if(count($types)) {
					$this->sendAs($scope, $types, $compose);
				}
			}
			
			$this->sendNotifications();
		}
		
		if(\Yii::$app->getModule('nitm')->enableLogger) {
			$logger = \Yii::$app->getModule('nitm')->logger;
			$logger->log([
				'message' => "Sent ".$this->_sendCount." alerts to destinations.\n\nCriteria: ".json_encode($this->_data->criteria(), JSON_PRETTY_PRINT)."\n\nRecipients: ".json_encode(array_map(function (&$group) {
					return array_map(function ($recipients) {
						return array_map(function($recipient) {
							return ArrayHelper::getValue($recipient, 'user', []);
						}, $recipients);
					}, $group);
				}, $to), JSON_PRETTY_PRINT),
				'level' => 1,
				'internal_category' => 'user-activity',
				'category' => 'Dispatch',
				'timestamp' => time(),
				'action' => 'dispatch-alerts', 
				'table' => Alerts::tableName(),
			], 'nitm-alerts-log');
			$logger->flush(true);
		}
			
		$this->reset();
		return true;
	}
	
	protected function setMailerLayout()
	{
		foreach($this->_supportedLayouts as $layout)
		{
			$property = $layout.'Layout';
			$this->_oldLayouts[$property] = \Yii::$app->mailer->$property;
			\Yii::$app->mailer->$property = rtrim($this->mailLayoutsPath).DIRECTORY_SEPARATOR.$layout;
		}
	}
	
	protected function resetMailerLayout()
	{
		foreach($this->_oldLayouts as $layout=>$path)
		{
			\Yii::$app->mailer->$layout = $path;
		}
	}
	
	/**
	 * Send emails using BCC
	 * @param string $scope Individual, Owner, Global...etc.
	 * @param array $types the types of emails that are being sent out
	 * @param array $compose
	 * @return boolean
	 */
	protected function sendAs($scope, $types, $compose)
	{
		$ret_val = false;
		switch(is_array($types))
		{
			case true:
			$ret_val = true;
			//Send the emails/mobile alerts
			
			//Get the subject
			static::$_subject = $this->_data->extractParam('view', ArrayHelper::getValue($compose, 'subject', $this->defaultSubject));
			
			foreach($types as $type=>$unmappedAddresses)
			{
				$addresses = $this->_data->getAddressNameMap($unmappedAddresses);
				switch($this->mode)
				{
					case 'single':
					foreach($addresses as $name=>$email)
					{
						$address = [$name => $email];
						$this->formatMessage($type, $scope, ArrayHelper::getValue($compose, 'message.'.$type, $this->defaultMessage), $address, current($unmappedAddresses)['user']);
						$this->send();
					}
					break;
						
					default:
					$this->formatMessage($type, $scope, ArrayHelper::getValue($compose, 'message.'.$type, $this->defaultMessage), array_slice($addresses, 0, 1));
					$this->send();
					break;
				}
				$this->addNotification($this->getMobileMessage(ArrayHelper::getValue($compose, 'message.mobile', $this->defaultMobileMessage)), $unmappedAddresses);
			}
			break;
		}
		return $ret_val;
	}
	
	protected function formatMessage($type, $scope, $message, $address, $user=null)
	{
		$params = [
			"subject" => self::$_subject,
			"content" => @$this->_data->extractParam('view', $message)
		];
		
		switch($scope)
		{
			case 'owner':
			$this->_data->variables('%bodyDt%', 'your');
			$this->_data->variables('%subjectDt%', $this->_data->variables('%bodyDt%'));
			break;
			
			default:
			$this->_data->variables('%bodyDt%', (($this->_data->criteria('action') == 'create') ? 'a' : 'the'));
			$this->_data->variables('%subjectDt%', $this->_data->variables('%bodyDt%'));
			break;
		}
		
		if(!is_null($user))
			$params['greeting'] = "Dear ".($this->useFullnames ? $user['profile']['name'] : $user['username']).", <br><br>";
			
		$params['title'] = $params['subject'];
		switch($type)
		{
			case 'email':
			$view = ['html' => rtrim($this->mailViewsPath).DIRECTORY_SEPARATOR.'message/email'];
			$params['content'] = $this->getEmailMessage($params['content'], $user, $scope);
			break;
			
			case 'mobile':
			//140 characters to be able to send a single SMS
			$params['content'] = $this->getMobileMessage($params['content']);
			$params['title'] = '';
			$view = ['text' => rtrim($this->mailViewsPath).DIRECTORY_SEPARATOR.'message/mobile'];
			break;
		}
		$params = $this->_data->replaceCommon($params);
		$this->_message = \Yii::$app->mailer->compose($view, $params)->setTo($address);
		switch($type)
		{
			case 'email':
			$this->_message->setSubject($params['subject']);
			break;
			
			case 'mobile':
			$this->_message->setTextBody($params['content']);
			break;
		}
	}
	
	protected function send()
	{
		if(!is_null($this->_message))
		{
			$this->_message->setFrom(\Yii::$app->params['components.alerts']['sender'])
				->send();
			$this->_message = null;
			$this->_sendCount++;
			return true;
		}
		else
			return false;
	}
	
	protected function addNotification($message, $addresses)
	{
		foreach((array)$addresses as $address)
		{
			$userId = $address['user']['id'];
			switch(isset($this->_notifications[$userId]))
			{
				case false:
				$this->_notifications[$userId] = [
					$message,
					$this->_data->criteria('priority'),
					$userId 
				];
				break;
			}
		}
	}
	
	protected function sendNotifications()
	{
		switch(is_array($this->_notifications) && !empty($this->_notifications))
		{
			case true:
			$keys = [
				'message',
				'priority',
				'user_id'
			];
			\nitm\widgets\models\Notification::find()->createCommand()->batchInsert(
				\nitm\widgets\models\Notification::tableName(), 
				$keys, 
				array_values($this->_notifications)
			)->execute();
			break;
		}
	}
	
	protected function getMobileMessage($original)
	{
		switch(is_array($original))
		{
			case true:
			$original = $this->_data->extractParam('view', $original, 'mobile');
			break;
		}
		$original = $this->_data->replaceCommon($original);
		//140 characters to be able to send a single SMS
		return strip_tags(strlen($original) <= 140 ? $original : substr($original, 0, 140).'...');
	}
	
	protected function getEmailMessage($original, $user, $scope)
	{
		//140 characters to be able to send a single SMS
		$alertAttributes = null;
		if(is_array($user) && count($user)) {
			$alert = ArrayHelper::getValue($this->_alerts, $user['id'], null);
		}
			
		return nl2br($original.$this->getFooter($scope, $alert));
	}
	
	private function getFooter($scope, $alert=null)
	{	
		$alert = is_array($alert) ? $alert : $this->_data->criteria();
		switch($scope)
		{
			case 'global':
			$footer = "\nYou are receiving this because of a global alert matching: ";
			break;
			
			default:
			$footer = "\nYou are receiving this because your alert settings matched: ";
			break;
		}
		if(isset($alert['priority']) && !is_null($alert['priority']))
		$footer .= "Priority: <b>".ucfirst($alert['priority'])."</b>, ";
		if(isset($alert['remote_type']) && !is_null($alert['remote_type']))
		$footer .= "Type: <b>".ucfirst($alert['remote_type'])."</b>, ";
		if(isset($alert['remote_id']) && !is_null($alert['remote_id']))
		$footer .= "Id: <b>".$alert['remote_id']."</b>, ";
		if(isset($alert['remote_for']) && !is_null($alert['remote_for']))
		$footer .= "For: <b>".ucfirst($alert['remote_for'])."</b>, ";
		if(isset($alert['action']) || !empty($this->_data->getReportedAction($this->_event)))
		$footer .= "and Action <b>".Alerts::properName($this->_data->getReportedAction($this->_event))."</b>";
		$footer .= ". Go ".Html::a("here", \Yii::$app->urlManager->createAbsoluteUrl("/user/settings/alerts"))." to change your alerts";
		$footer .= "\n\nSite: ".Html::a(\Yii::$app->urlManager->createAbsoluteUrl('/'), \Yii::$app->homeUrl);
			
		return Html::tag('small', $footer);
	}
}
