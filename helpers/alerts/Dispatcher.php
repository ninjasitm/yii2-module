<?php

namespace nitm\helpers\alerts;

use Yii;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use nitm\helpers\Cache;
use nitm\widgets\models\Alerts;

/**
 * This is the alert dispatcher class.
 */
class Dispatcher extends \yii\base\Component
{
	public $mode;
	public $useFullnames = true;
	public $reportedAction;
	public static $usersWhere = [];
	
	protected static $is = 'alerts';
	protected static $_subject;
	protected static $_body;
	
	protected $_criteria = [];
	protected $_originUserId;
	protected $_message;
	protected $_notifications = [];
	protected $_sendCount = 0;
	
	private $_prepared = false;
	private $_variables = [];
	private $_alerts;
	private $_alertStack = [];
	
	const BATCH = 'batch';
	const SINGLE = 'single';
	const UNDEFINED = '__undefined__';
	
	const EVENT_PREPARE = 'prepare';
	const EVENT_PROCESS = 'process';
	
	public function init()
	{
		$this->initEvents();
	}
	
	public static function supportedMethods()
	{
		return [
			'any' => 'Any Method',
			'email' => 'Email',
			'mobile' => 'Mobile/SMS'
		];
	}
	
	protected function initEvents()
	{
		$this->on(self::EVENT_PREPARE, [$this, 'prepareAlerts']);
		$this->on(self::EVENT_PROCESS, [$this, 'processAlerts']);
	}
	
	public function reset()
	{
		$this->_variables = [];
		$this->_criteria = [];
		$this->reportedAction = '';
		$this->_prepared = false;
	}
	
	protected function getKey($model)
	{
		return implode('-', [
			$model->getScenario(),
			$model->isWhat(), 
			$model->getId()
		]);
	}
	
	public function prepareAlerts($event, $for='any', $priority='any')
	{
		if(!\Yii::$app->getModule('nitm')->enableAlerts)
			return;
		if($event->handled)
			return;
			
		$this->processEventData($event);
		$this->_alertStack[$this->getKey($event->sender)] = [
			'remote_type' => $event->sender->isWhat(),
			'remote_for' => $for,
			'priority' => $priority,
			'action' => $event->sender->getScenario()
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
	public function processAlerts($event, $options=[])
	{
		if(!\Yii::$app->getModule('nitm')->enableAlerts)
			return;
		if($event->handled)
			return;
		
		$this->criteria('remote_id', $event->sender->getId());
		switch(!$this->criteria('action'))
		{
			case false:
			$this->prepare($event);
			switch($this->isPrepared())
			{
				case true:
				//First check to see if this specific alert exits
				if(count($options))
					$this->sendAlerts($options, ArrayHelper::getValue($options, 'owner_id', null));
				$event->handled = true;
				break;
				
				default:
				throw new \yii\base\Exception("No alert preparation was done!");
				break;
			}
			break;
			
			default:
			throw new \yii\base\Exception("Need an action to process the alert");
			break;
		}
	}
	
	protected function processEventData($event)
	{
		$runtimeModifiable = [
			'variables',
			'usersWhere',
			'reportedAction',
			'criteria'
		];
		foreach($runtimeModifiable as $property)
		{
			switch($property)
			{
				case 'reportedAction':
				$this->$property = ArrayHelper::remove($event->data, $property);
				break;
				
				default:
				$params = ArrayHelper::getValue($event, 'data.'.$property, null);
				unset($event->data[$property]);
				switch($property)
				{
					case 'variables':
					case 'andWhere':
					$params = [$params];
					break;
				}
				if(count($params))
					call_user_func_array([$this, $property], $params);
				break;
			}
		}
	}
	
	public function variables($variables)
	{
		$this->addVariables($variables);
	}
	
	public function addVariables(array $variables)
	{
		$variables = is_array(current($variables)) ? array_pop($variables) : $variables;
		$this->_variables = array_merge($variables, $this->_variables);
	}
	
	public function resetVariables()
	{
		$this->_variables = [];
	}
	
	public function prepare($event)
	{
		$this->processEventData($event);
		$basedOn = array_merge(
			(array)ArrayHelper::remove($this->_alertStack, $this->getKey($event->sender)), 
			(array)$event->data
		);
		
		if(is_array($basedOn))
		{
			$basedOn['action'] = $event->sender->isNewRecord === true ? 'create' : 'update';
			$this->reportedAction = $basedOn['action'].'d';
			$this->_criteria = $basedOn;
			$this->_prepared = true;
		}
	}
	
	public function usersWhere($where=[])
	{
		//$userClass = \Yii::$app->user->identity->className();
		//$userClass::$usersWhere = $where;
	}
	
	public function isPrepared()
	{
		return $this->_prepared === true;
	}
	
	public function criteria($_key, $_value='__undefined__')
	{
		$ret_val = [];
		$key = is_array($_key) ? $_key : [$_key];
		$value = is_array($_value) ? $_value : [$_value];
		foreach($key as $idx=>$k)
		{
			switch($value[$idx])
			{
				case self::UNDEFINED:
				$ret_val[$k] = isset($this->_criteria[$k]) ? $this->_criteria[$k] : self::UNDEFINED;
				break;
				
				default:
				$this->_criteria[$k] = $value[$idx];
				break;
			}
		}
		return (is_array($ret_val) && sizeof($ret_val) == 1) ? array_pop($ret_val) : $ret_val;
	}
	
	/**
	 * Find alerts for the specific criteia originally provided
	 * @param int $originUserId Is the ID of the user for the object which triggered this alert sequence
	 * @return \yii\db\Query
	 */
	public function findAlerts($originUserId)
	{
		$this->_originUserId = $originUserId;
		return $this->findSpecific($this->_criteria)
			->union($this->findOwner($this->_originUserId, $this->_criteria))
			->union($this->findListeners($this->_criteria))
			->union($this->findGlobal($this->_criteria))
			->indexBy('user_id')
			->with('user')->all();
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
				'user_id' => \Yii::$app->user->getId()
			])
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
		$criteria['remote_type'] = [$criteria['remote_type'], 'any'];
		$criteria['remote_for'] = [$criteria['remote_for'], 'any'];
		$criteria['priority'] = [$criteria['priority'], 'any'];
		$remoteWhere = [];
		if(isset($criteria['remote_id']))
		{
			$remoteWhere = ['or', '`remote_id`='.$criteria['remote_id'], ' `remote_id` IS NULL'];
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
		$criteria['remote_type'] = [$criteria['remote_type'], 'any'];
		$criteria['remote_for'] = [$criteria['remote_for'], 'any'];
		$criteria['action'] = [$criteria['action'], 'any'];
		$criteria['priority'] = [$criteria['priority'], 'any'];
		$remoteWhere = [];
		if(isset($criteria['remote_id']))
		{
			$remoteWhere = ['or', '`remote_id`='.$criteria['remote_id'], ' `remote_id` IS NULL'];
			unset($criteria['remote_id']);
		}
		return Alerts::find()->select('*')
			->orWhere($criteria)
			->andWhere($remoteWhere)
			->andWhere([
				'not', ['user_id' => \Yii::$app->user->getId()]
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
		$criteria['global'] = 1;
		$criteria['user_id'] = null;
		$criteria['remote_type'] = [$criteria['remote_type'], 'any'];
		$criteria['action'] = [$criteria['action'], 'any'];
		$criteria['priority'] = [$criteria['priority'], 'any'];
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
			//Organize by global and individual alerts
			foreach($alerts as $idx=>$alert)
			{
				switch(1)
				{
					case $alert->global == 1:
					/**
					 * Only send global emails based on what the user preferrs in their profile. 
					 * For specific alerts those are based ont he alert settings
					 */
					$to['global'] = array_merge_recursive($to['global'], $this->getAddresses($alert->methods, $this->getUsers(), true));
					break;
					
					case $alert->user->getId() == $this->_originUserId:
					$to['owner'] = array_merge_recursive($to['owner'], $this->getAddresses($alert->methods, [$alert->user]));
					break;
					
					default:
					$to['individual'] = array_merge_recursive($to['individual'], $this->getAddresses($alert->methods, [$alert->user]));
					break;
				}
			}
			
			foreach($to as $scope=>$types)
			{
				if(count($types))
				{
					switch($this->mode)
					{
						case self::SINGLE;
						$this->sendAsSingle($scope, $types, $compose);
						break;
						
						default:	
						$this->sendAsBatch($scope, $types, $compose);
						break;
					}
				}
			}
			
			$this->sendNotifications();
		}
		
		if(\Yii::$app->getModule('nitm')->enableLogger) {
			$logger = \Yii::$app->getModule('nitm')->logger;
			$logger->log([
				'message' => "Sent ".$this->_sendCount." alerts to destinations.\n\nCriteria: ".json_encode($this->_criteria, JSON_PRETTY_PRINT)."\n\nRecipients: ".json_encode(array_map(function (&$group) {
					return array_map(function (&$recipients) {
						return array_map(function(&$recipient) {
							ArrayHelper::remove($recipient, 'user');
							return $recipient;
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
	
	/**
	 * Send emails using BCC
	 * @param string $scope Individual, Owner, Global...etc.
	 * @param array $types the types of emails that are being sent out
	 * @param array $compose
	 * @return boolean
	 */
	protected function sendAsBatch($scope, $types, $compose)
	{
		$ret_val = false;
		switch(is_array($types))
		{
			case true:
			$ret_val = true;
			//Send the emails/mobile alerts
			self::$_subject = is_array($compose['subject']) ? \Yii::$app->mailer->render($compose['subject']['view']) : $compose['subject'];
			foreach($types as $type=>$unMappedAddresses)
			{
				$addresses = $this->getAddressNameMap($unMappedAddresses);
				$params = [
					"subject" => self::$_subject,
					"content" => is_array($compose['message'][$type]) ? \Yii::$app->mailer->render($compose['message'][$type]['view']) : $compose['message'][$type]
				];
				switch($scope)
				{
					case 'owner':
					$this->_variables['%bodyDt%'] = 'your';
					$this->_variables['%subjectDt%'] = $this->_variables['%bodyDt%'];
					break;
					
					default:
					$this->_variables['%bodyDt%'] = (($this->criteria('action') == 'create') ? 'a' : 'the');
					$this->_variables['%subjectDt%'] = $this->_variables['%bodyDt%'];
					break;
				}
				$params['title'] = $params['subject'];
				switch($type)
				{
					case 'email':
					$view = ['html' => '@app/views/alerts/message/email'];
					$params['content'] = $this->getEmailMessage($params['content']);
					break;
					
					case 'mobile':
					//140 characters to be able to send a single SMS
					
					$params['content'] = $this->getMobileMessage($params['content']);
					$params['title'] = '';
					$view = ['text' => '@app/views/alerts/message/mobile'];
					break;
				}
				$params = $this->replaceCommon($params);
				$this->_message = \Yii::$app->mailer->compose($view, $params)->setTo(array_slice($addresses, 0, 1));
				switch($type)
				{
					case 'email':
					$this->_message->setSubject($params['subject']);
					break;
						
					case 'mobile':
					$this->_message->setTextBody($params['content']);
					break;
				}
				switch(sizeof($addresses) >= 1)
				{
					case true:
					$this->_message->setBcc($addresses);
					break;
				}
				$this->send();
				$this->addNotification($this->getMobileMessage($compose['message']['mobile']), $unMappedAddresses);
			}
			break;
		}
		return $ret_val;
	}
	
	/**
	 * Send emails using BCC
	 * @param string $scope Individual, Owner, Global...etc.
	 * @param array $types the types of emails that are being sent out
	 * @param array $compose
	 * @return boolean
	 */
	protected function sendAsSingle($scope, $types, $compose)
	{
		switch(is_array($types))
		{
			case true:
			$ret_val = true;
			//Send the emails/mobile alerts
			self::$_subject = is_array($compose['subject']) ? \Yii::$app->mailer->render($compose['subject']['view']) : $compose['subject'];
			foreach($types as $type=>$unMappedAddresses)
			{
				$addresses = $this->getAddressNameMap($unMappedAddresses);
				foreach($addresses as $name=>$email)
				{
					$address = [$name => $email];
					$params = [
						"subject" => self::$_subject,
						"content" => (is_array($compose['message'][$type]) ? \Yii::$app->mailer->render($compose['message'][$type]['view']) : $compose['message'][$type])
					];
					switch($scope)
					{
						case 'owner':
						$this->_variables['%bodyDt%'] = 'your';
						$this->_variables['%subjectDt%'] = $this->_variables['%bodyDt%'];
						break;
						
						default:
						$this->_variables['%bodyDt%'] = (($this->criteria('action') == 'create') ? 'a' : 'the');
						$this->_variables['%subjectDt%'] = $this->_variables['%bodyDt%'];
						break;
					}
					$params['greeting'] = "Dear ".current($unMappedAddresses)['user']->username.", <br><br>";
					$params['title'] = $params['subject'];
					switch($type)
					{
						case 'email':
						$view = ['html' => '@nitm/views/alerts/message/email'];
						$params['content'] = $this->getEmailMessage($params['content'], current($unMappedAddresses)['user'], $scope);
						break;
						
						case 'mobile':
						//140 characters to be able to send a single SMS
						$params['content'] = $this->getMobileMessage($params['content']);
						$params['title'] = '';
						$view = ['text' => '@nitm/views/alerts/message/mobile'];
						break;
					}
					$params = $this->replaceCommon($params);
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
					$this->send();
				}
				$this->addNotification($this->getMobileMessage($compose['message']['mobile']), $unMappedAddresses);
			}
			break;
		}
		return $ret_val;
	}

    /**
     * @return array
     */
    protected function getUsers($options=[])
    {
		$userClass = \Yii::$app->user->identity->className();
		$key = 'alerts.users';
        switch(Cache::exists($key))
		{
			case true:
			$ret_val = Cache::getModelArray($key, $options);
			break;
			
			default:
			$ret_val = $userClass::find()->with('profile')->where(static::$usersWhere)->all();
			Cache::setModelArray($key, $ret_val);
			break;
		}
		return $ret_val;
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
			$userId = $address['user']->getId();
			switch(isset($this->_notifications[$userId]))
			{
				case false:
				$this->_notifications[$userId] = [
					$message,
					$this->criteria('priority'),
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
	
	protected function getAddressNameMap($addresses)
	{
		$ret_val = [];
		foreach($addresses as $address)
		{
			unset($address['user']);
			$ret_val[key($address)] = $address[key($address)];
		}
		return $ret_val;
	}
	
	protected function getAddressIdMap($addresses)
	{
		$ret_val = [];
		foreach($addresses as $address)
		{
			$user = $address['user'];
			unset($address['user']);
			$ret_val[key($address)] = $user->getId();
		}
		return $ret_val;
	}
	
	public static function filterMethods($value)
	{
		$ret_val = [];
		$value = is_array($value) ? $value : [$value];
		foreach($value as $method)
		{
			switch(array_key_exists($method, static::supportedMethods()))
			{
				case true:
				$ret_val[] = $method;
				break;
			}
		}
		return implode(',', (empty($ret_val) ? ['email'] : $ret_val));
	}
	
	protected function replaceCommon($string)
	{
		$string = is_array($string) ? $string : [$string];
		$stringPlaceholders = array_map(function ($value) {
			preg_match_all("/%([\w+\:]+)%/", $value, $matches);
			if(sizeof($matches[1]) >= 1)
				return $matches[1];
			else
				return false;
		}, $string);
		$variables = array_merge($this->defaultVariables(), $this->_variables);
		array_walk($stringPlaceholders, function ($value, $key) use (&$variables) {
			if(!$value)
				return;
			array_walk($value, function ($v) use(&$variables) {
				$v = explode(':', $v);
				$k = $v[0];
				$f = sizeof($v) == 1 ? null : $v[1];
				if(isset($variables['%'.$k.'%']))
				{
					$realValue = $variables['%'.$k.'%'];
					$variables['%'.implode(':', $v).'%'] = is_null($f) == 1 ? $realValue : $f($realValue);
				}
			});
		});
		$ret_val = str_replace(array_keys($variables), array_values($variables), $string);
		return (is_array($ret_val) && sizeof($ret_val) == 1) ? array_pop($ret_val) : $ret_val;
	}
	
	protected function getMobileMessage($original)
	{
		switch(is_array($original))
		{
			case true:
			$original = \Yii::$app->mailer->render($original['view']);
			break;
		}
		$original = $this->replaceCommon($original);
		//140 characters to be able to send a single SMS
		return strip_tags(strlen($original) <= 140 ? $original : substr($original, 0, 136).'...');
	}
	
	protected function getEmailMessage($original, $user, $scope)
	{
		//140 characters to be able to send a single SMS
		return nl2br($original.$this->getFooter($scope, isset($this->_alerts[$user->getId()]) ? $this->_alerts[$user->getId()]->getAttributes() : null));
	}
	
	private function defaultVariables()
	{
		return [ 
			'%who%' => '@'.\Yii::$app->user->identity->username,
			'%when%' => date('D M jS Y @ h:iA'), 
			'%today%' => date('D M jS Y'),
			'%priority%' => ($this->criteria('priority') == 'any') ? 'Normal' : ucfirst($this->criteria('priority')),
			'%action%' => $this->reportedAction,
			'%remoteFor%' => ucfirst($this->criteria('remote_for')),
			'%remoteType%' => ucfirst($this->criteria('remote_type')),
			'%remoteId%' => $this->criteria('remote_id'),
			'%id%' => $this->criteria('remote_id')
		];
	}
	
	private function getAddresses($method=null, $users=[], $global=false)
	{
		$method = (string)$method;
		$ret_val = [];
		switch($global)
		{
			case true:
			$users = $this->getUsers();
			break;
		}
		$methods = ($method == 'any' || is_null($method)) ? array_keys(static::supportedMethods()) : explode(',', $method);
		if(in_array('any', $methods))
			unset($methods[array_search('any', $methods)]);
		foreach($users as $user)
		{
			foreach($methods as $method)
			{
				if($user->getId() == \Yii::$app->user->getId())
					continue;
				switch($method)
				{
					case 'email':
					switch(1)
					{
						case ($uri = (is_object($user->profile) ? $user->profile->getAttribute('public_email') : $user->email)) != '':
						break;
						
						default:
						$uri = $user->email;
						break;
					}
					break;
					
					default:
					$uri = is_object($user->profile) ? $user->profile->getAttribute($method.'_email') : null;
					break;
				}
				if(!empty($uri))
				{
					$name = $user->fullName();
					$id = !$user->getId() ? 'global' : $user->getId();
					$ret_val[$method][$id] = [$uri => (!$name ? $uri : $name), 'user' => $user];
				}
			}
		}
		return $ret_val;
	}
	
	private function getFooter($scope, $alert=null)
	{	
		$alert = is_array($alert) ? $alert : $this->_criteria;
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
		if(isset($alert['action']) || !empty($this->reportedAction))
		$footer .= "and Action <b>".Alerts::properName($this->reportedAction)."</b>";
		$footer .= ". Go ".Html::a("here", \Yii::$app->urlManager->createAbsoluteUrl("/user/settings/alerts"))." to change your alerts";
		$footer .= "\n\nSite: ".Html::a(\Yii::$app->urlManager->createAbsoluteUrl('/'), \Yii::$app->homeUrl);
			
		return Html::tag('small', $footer);
	}
	
	protected function getReportedAction($event)
	{
		switch($event->sender->getScenario())
		{
			case 'resolve':
			$this->reportedAction = $event->sender->resolved == 1 ? 'resolved' : 'un-resolved';
			break;
			
			case 'complete':
			$this->reportedAction = $event->sender->completed == 1 ? 'completed' : 'in-completed';
			break;
			
			case 'close':
			$this->reportedAction = $event->sender->closed == 1 ? 'closed' : 're-opened';
			break;
			
			case 'disable':
			$this->reportedAction = $event->sender->disabled == 1 ? 'disabled' : 'enabled';
			break;
		}
	}
}
