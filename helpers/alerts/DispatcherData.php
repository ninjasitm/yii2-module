<?php

namespace nitm\helpers\alerts;

use nitm\helpers\ArrayHelper;

/**
 * Functions relating to parsing data for alerts dispatcher
 */

class DispatcherData 
{
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
	
	public function processEventData($event)
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
	
	public function variables($variables=null)
	{
		$key = is_string($variables) ? $variables : null;
		if(is_string($variables))
			$variables = null;
		else if(is_array($variables))
			$variables = is_array(current($variables)) ? array_pop($variables) : $variables;
		
		return ArrayHelper::getOrSetValue($this->_variables, $key, $variables);
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

    /**
     * @return array
     */
    public static function getUsers($options=[])
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
	
	public function usersWhere($where=[])
	{
		//$userClass = \Yii::$app->user->identity->className();
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
				$default[] = 'with id $id%';
			$default[] = "was %action%";
			break;
		}
		if(is_array($from))
		{
			$view = ArrayHelper::getValue($from, $path, null);
			if(!is_null($view) && file_exists(\Yii::getAlias($view)))
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
}

?>