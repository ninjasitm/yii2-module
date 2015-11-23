<?php

namespace nitm\components\alerts;

use nitm\helpers\ArrayHelper;
use nitm\helpers\Cache;

/**
 * Functions relating to parsing data for alerts dispatcher
 */

class DispatcherData
{
	public $reportedAction;
	public static $usersWhere = [];

	protected $_criteria = [];
	protected $_variables = [];

	const UNDEFINED = '__undefined__';

	public static function getAddressNameMap($addresses)
	{
		$ret_val = [];
		foreach($addresses as $address)
		{
			unset($address['user']);
			$ret_val[key($address)] = $address[key($address)];
		}
		return $ret_val;
	}

	public function getAddressIdMap($addresses)
	{
		$ret_val = [];
		foreach($addresses as $address)
		{
			$user = $address['user'];
			unset($address['user']);
			$ret_val[key($address)] = $user['id'];
		}
		return $ret_val;
	}


	/**
	 * Return the value setup in the alerts module
	 */
	public static function supportedMethods()
	{
		return [
			'any' => 'Any Method',
			'email' => 'Email',
			'mobile' => 'Mobile/SMS'
		];
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

	public function replaceCommon($string)
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

	private function defaultVariables()
	{
		return [
			'%who%' => '@'.\Yii::$app->user->getIdentity()->username,
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

	public function processEventData($data, $handler)
	{
		$runtimeModifiable = [
			'variables',
			'usersWhere',
			'reportedAction',
			'action',
			'criteria'
		];

		foreach($runtimeModifiable as $property)
		{
			switch($property)
			{
				case 'reportedAction':
				if(!$this->$property)
					$this->$property = ArrayHelper::remove($data, $property);
				break;

				case 'action':
				break;

				default:
				$params = ArrayHelper::getValue($data, $property, null);
				unset($data[$property]);
				switch($property)
				{
					case 'variables':
					case 'andWhere':
					$params = [$params];
					break;
				}
				if(!is_array($params))
					$params = [$params];
				else
					$params = is_array(current($params)) ? $params : [$params];
				if(count($params))
					call_user_func_array([$this, $property], $params);
				break;
			}
		}
	}

	public function variables($key=null, $variables=null)
	{
		if(is_string($key) && !is_null($variables))
			$ret_val =ArrayHelper::setValue($this->_variables, $key, $variables);
		if(is_string($key) && is_null($variables))
			$ret_val = ArrayHelper::getValue($this->_variables, $key);
		else if(is_array($key)) {
			$this->_variables = array_merge($this->_variables, $key);
			$ret_val = true;
		}
		else
			$ret_val = $this->_variables;
		return $ret_val;

	}

	public function resetVariables()
	{
		$this->_variables = [];
	}

	public function criteria($_key=null, $_value='__undefined__')
	{
		if($_value==self::UNDEFINED && $_key==null)
			return $this->_criteria;

		$ret_val = [];
		$key = is_array($_key) ? array_keys($_key) : [$_key];

		if(is_array($_value))
			$value = $_value;
		else if ($_value==self::UNDEFINED) {
			if(is_array($_key))
				$value = array_values($_key);
			else
				$value = [$_value];
		}
		else
			$value = [$_value];

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

	public static function getAddresses($method=null, $users=[], $global=false)
	{
		$method = (string)$method;
		$ret_val = [];

		if($global)
			$users = static::getUsers();
		else
			$users = is_array(current($users)) ? $users : [$users];

		$methods = ($method == 'any' || is_null($method)) ? array_keys(static::supportedMethods()) : explode(',', $method);
		if(in_array('any', $methods))
			unset($methods[array_search('any', $methods)]);

		foreach($users as $user)
		{
			foreach($methods as $method)
			{
				if($user['id'] == \Yii::$app->user->getIdentity()->getId())
					continue;
				switch($method)
				{
					case 'email':
					$uri = ArrayHelper::getValue($user, 'profile.public_email', ArrayHelper::getValue($user, 'email'));
					break;

					default:
					$uri = ArrayHelper::getValue($user, 'profile.'.$method.'_email', null);
					break;
				}
				if(is_string($uri)) {
					$name = $user['profile']['name'];
					$id = !$user['id'] ? 'global' : $user['id'];
					$ret_val[$method][$id] = [$uri => (!$name ? $uri : $name), 'user' => $user];
				}
			}
		}
		return $ret_val;
	}

    /**
     * @return array
     */
    public static function getUsers($options=[])
    {
		$userClass = \Yii::$app->user->getIdentity()->className();
		$key = 'alerts.users';
        switch(Cache::exists($key))
		{
			case true:
			$ret_val = Cache::cache()->get($key, $options);
			break;

			default:
			$ret_val = $userClass::find()
				->select(['id', 'username', 'email'])
				->with('profile')
				->where(static::$usersWhere)
				->andWhere(['disabled' => false])
				->asArray()
				->all();
			Cache::cache()->set($key, $ret_val, $userClass);
			break;
		}
		return $ret_val;
    }

	public function usersWhere($where=[])
	{
		//$userClass = \Yii::$app->user->getIdentity()->className();
		//$userClass::$usersWhere = $where;
	}

	public function extractParam($path, $from, $type='subject')
	{
		$ret_val = '';
		switch($type)
		{
			case 'subject':
			case 'message':
			$default = ["%subjectDt:ucfirst%"];
			if($this->criteria('status'))
				$default[] = "%status%";
			$default[] = "'%remoteType%'";
			if($this->criteria('remote_id'))
				$default[] = 'with id %id%';
			$default[] = "was %action%";
			break;

			default:
			$default = [];
			break;
		}
		if(is_array($from))
		{
			$view = ArrayHelper::getValue($from, $path, null);
			if(!is_null($view) && file_exists(\Yii::getAlias($view).'.php'))
				$ret_val = [\Yii::$app->mailer->render($view)];
			else
				$ret_val = $default;
		}
		else
			$ret_val = [$from];

		return implode(' ', $ret_val);
	}

	public static function getKey($model)
	{
		return implode('-', [
			$model->getScenario(),
			$model->isWhat(),
			$model->getId()
		]);
	}

	public function getReportedAction($event=null)
	{
		if($event instanceof \yii\base\Event)
		{
			switch($event->sender->getScenario())
			{
				case 'resolve':
				$ret_val = $event->sender->resolved == true ? 'resolved' : 'un-resolved';
				break;

				case 'complete':
				$ret_val = $event->sender->completed == true ? 'completed' : 'in-completed';
				break;

				case 'verify':
				$ret_val = $event->sender->completed == true ? 'verified' : 'un-verified';
				break;

				case 'close':
				$ret_val = $event->sender->closed == true ? 'closed' : 're-opened';
				break;

				case 'disable':
				$ret_val = $event->sender->disabled == true ? 'disabled' : 'enabled';
				break;

				default:
				$ret_val = !is_string($this->reportedAction) || empty($this->reportedAction) ? $this->_data->criteria('action') : $this->reportedAction;
				break;
			}
		} else
			$ret_val = !is_string($this->reportedAction) || empty($this->reportedAction) ? $this->_data->criteria('action') : $this->reportedAction;

		return $ret_val;
	}
}

?>
