<?php

namespace nitm\components;

use Yii;
use yii\base\Event;
use yii\base\Model;
use nitm\helpers\Session;
use nitm\helpers\Cache;
use nitm\helpers\ArrayHelper;
use nitm\helpers\Json;
use nitm\models\configer\Container;
use nitm\models\configer\Section;
use nitm\models\configer\Value;

/**
 * Class Configer
 * @package nitm\components
 *
 * @property integer $id
 * @property string $name
 * @property string $value
 * @property string $section
 * @property string $container
 * @property string $what
 * @property string $engine
 * @property string $comment
 * @property string $getValues
 */

class Configer extends Model
{
	//public data
	public $storeIn = 'session';
	public static $config = [];
	
	/**
	 * @array The global settings used by all models and the app
	 */
	public $settings = [];
	
	public $container = 'settings';
	
	//Form variables
	public $id;				//The id of the value
	public $name;			//The name of a key/value pair
	public $value;			//The value
	public $section;		//Current value section
	public $what;			//What is being done
	public $comment;		//The comment
	public $convert;		//Convert
	public $convertTo;		//Convert to what engine?
	public $getValues;		//Should we try to get values as well?
	
	//protected data
	protected $engine;			//Current engine. Database or file?
	protected $_config;
	protected $classes = [
		"success" => "success",
		"failure" => "warning",
		"info" => "info"
	];
	
	//constant data
	const dm = 'configer';
	const NO_DEC = 'nodec:';
	
	//private data
	private $_supported = ["file" => "File", "db" => "Database"];
	private $_event;
	private $_engineIsSet;
	private $_store;
	
	public function __destruct()
	{
		$this->setExternal('nitm-settings', $this->settings, 120);
	}
	
	public function init()
	{
		parent::init();	
		$this->setEngine();
		$this->initEvents();
		$this->settings = $this->getExternal('nitm-settings');
		$this->config('supported', $this->_supported);
	}
	
	public function behaviors()
	{ 
		$behaviors = [
		];
		return array_merge(parent::behaviors(), $behaviors);
	}
	
	public function rules()
	{
		return [
			[['what', 'value', 'section', 'name', 'container'], 'required', 'on' => ['createValue']],
			[['what', 'value', 'container'], 'required', 'on' => ['createSection']],
			[['what', 'value'], 'required', 'on' => ['createContainer']],
			[['what', 'value', 'name', 'container', 'id'], 'required', 'on' => ['updateValue']],
			[['what', 'value', 'section', 'container'], 'required', 'on' => ['updateSection']],
			[['what', 'value', 'container'], 'required', 'on' => ['updateContainer']],
			[['what', 'name', 'container', 'id'], 'required', 'on' => ['deleteValue']],
			[['what', 'value', 'container'], 'required', 'on' => ['deleteSection']],
			[['what', 'value'], 'required', 'on' => ['removeContainer']],
			[['what', 'container', 'section'], 'required', 'on' => ['getSection']],
			[['convert'], 'required', 'on' => ['convert']],
			[['engine'], 'safe'],
		];
	}
	
	public function scenarios()
	{
		return [
			'default' => ['value', 'container', 'section', 'what', 'engine', 'getValues',],
			'createValue' => ['value', 'name', 'container', 'section', 'what'],
			'createSection' => ['value', 'container', 'what'],
			'createContainer' => ['value', 'what'],
			'updateValue' => ['value', 'name', 'section', 'container', 'what', 'id'],
			'updateSection' => ['value', 'container', 'what', 'id'],
			'deleteValue' => ['section', 'name', 'what', 'id'],
			'deleteSection' => ['name', 'container', 'what', 'id'],
			'removeContainer' => ['value', 'what', 'id'],
			'updateComment' => ['comment'],
			'getSection' => ['what', 'container', 'section', 'getValues'],
			'convert' => ['convert', 'engine']
		 ];
	}
	
	public function attributeLabels()
	{
		return [
		    'value' => 'Value',
		    'name' => 'Name',
		    'container' => 'Container',
		    'engine' => 'Engine',
		    'section' => 'Section',
		    'what' => 'Action',
		];
	}
	
	/*
	 * Initiate the event handlers for this class
	 */
	public function initEvents()
	{
		$this->on("afterCreate", function($e) {
			$this->config('current.section', $this->event('section'));
			$this->set($this->event('key'), $this->event('value'));
			$this->config($this->uriOf(self::dm.'.'.$this->event('key'), true), $this->event('value'));
			$this->trigger('logData');
		});
		
		$this->on("afterUpdate", function($e) {
			$this->set($this->event('key'), $this->event('value'));
			$this->config($this->uriOf(self::dm.'.'.$this->event('key'), true), $this->event('value'));
			$this->trigger('logData');
		});
		
		$this->on("afterDelete", function($e) {
			switch($this->event('action'))
			{
				case 'delete':
				$value = $section;
				break;
			}
			$this->config('current.section', $this->event('section'));
			$this->remove($this->uriOf(self::dm.'.'.$this->event('key'), true), true);
			
			if($this->container == \Yii::$app->getModule('nitm')->config->container)
				$this->remove(Session::settings.'.'.$this->event('key'));
			else
				$this->remove($this->container.'.'.$this->event('key'), $value);
				
			$this->trigger('logData');
		});
		
		$this->on('logData', function ($e) {
			$data = array_merge($this->getEventData(), $this->_event->data);
			\Yii::$app->getModule('nitm')->logger->log($data);
			$this->_event->handled = true;
		});
	}
	
	protected function setEventData($data) {
		$this->_event = new Event([
			'data' => $data
		]);
	}
	
	protected function getEventData()
	{
		return [
			'db_name' => $this->event('db'),
			'table_name' => $this->event('table'),
			'action' => $this->event('action'),
			'message' => implode(' ', [
				"On",
				date("F j, Y @ g:i a"),
				"user",
				$this->get('securer.username'),
				$this->event('message')
			])
		];
	}
	
	/*
     * Prepare the config info for updating
	 * @param string $container
	 * @param boolean $getValues
     * @return mixed $config
     */
	public function prepareConfig($container=null, $getValues=false)
	{
		$container = is_null($container) ? $this->container : array_pop(explode('.', $container));
		switch($this->_store->is)
		{
			case 'alt':
			switch($container)
			{
				case 'pma':
				$template = $this->get("settings.templates.iframe");
				$this->render($template, ['src' => '/phpmyadmin/main.php']);
				return;
				break;
			}
			break;
			
			default:
			$this->setType($container);
			//if the selected config is not loaded then load it
			$key = $this->_store->is.'.'.$container;
			if($this->get(self::dm.'.current.location') != $key || $this->get(self::dm.'.current.container') != $container || !$this->existsExternal(self::dm.'.'.$this->_store->is.'.config')) {
				$this->config('current.config', $this->getConfig($container, $getValues, true));
				$this->setExternal(self::dm.'.'.$this->_store->is.'.config', $this->config('current.config'));
				$this->config('current.sections', array_merge(["" => "Select section..."], $this->getSections()));
			}
			//otherwise just get the current loaded config
			else {
				$this->config('current.config', $this->getExternal(self::dm.'.'.$this->_store->is.'.config'));
				$this->config('current.sections', array_merge(["" => "Select section..."], $this->getSections()));
			}
			
			if(!$getValues)
				$this->config('current.config', null);
				
			$this->config('load.current', $this->section && (bool)count($this->config('current.config.'.$this->section))>=1);
			$this->config('load.sections', (bool)count($this->config('current.sections'))>=1);
			$this->set(self::dm.'.current.location', $key);
			$this->set(self::dm.'.current.container', $container);
			break;
		}
	}
	
	/*
     * Set the configuration type
	 * @param string $container
	 * @param string $from
     */	
	public function setType($container=null, $from='default')
	{
		$this->config('surround', []);
		$this->config('current.type', $this->_store->is);
		$this->config('current.type_text', 'a section');
		$this->config('current.container', $container);
		$this->config('current.sections', []);
		$this->config('current.selected_text', "selected='selected'");
		$this->config('load.types', !is_array($this->_supported) ? false : true);
		switch(isset($this->config('from')[$from]))
		{
			case true:
			switch(1)
			{
				case in_array('xml', $this->config('from')[$from]['types']) !== false:
				//$fb::$compatible = ['text' => '.xml');
				//$freswitch_base = '/usr/local/freswitch/conf/';
				$this->config('current.container', $this->_store->is);
				$this->config('current.from.'.$this->_store->is.'.selected',  "selected='selected'");
				$this->config('current.path', $this->config('from')[$from]['dir']);
				$this->config('current.type', 'xml');
				$this->config('current.surround', ['open' => "<code>", "close" => "</code>"]);
				$this->config('current.type_text', 'an xml file');
				break;
			
				default:
				switch(in_array($container, $this->config('containers')))
				{
					case true:
					$this->config('current.container', $container);
					$this->config('current.path', "@$container");
					break;
				
					default:
					$this->config('current.container', "globals");
					$this->config('current.path', '@globals');
					break;
				}
				break;
			}
			$this->container = $this->config('current.container');
			break;
		}
	}
	
	public function getEngine()
	{
		return $this->engine;
	}
	
	/*
	 * Set the storage engine
	 * @param string $loc Either file or database
	 */
	public function setEngine($loc=null)
	{
		$loc = is_null($loc) ? $this->engine : $loc;
		
		if(isset($this->_store) && $this->_store->is == $loc && $this->_engineIsSet)
			return;
			
		switch($this->isSupported($loc))
		{
			case true:
			switch($loc)
			{
				case 'db':
				$this->_store = new configer\DBStore;
				break;
				
				case 'file':
				$this->_store = new configer\FileStore;
				break;
			}
			break;
		}
		if($this->_store->is)
		{
			$this->getContainers(null);
			//clear any other unused engine data
			foreach(array_diff_key($this->_supported, [$this->_store->is => ucfirst($this->_store->is)]) as $clear=>$key)
				$this->remove(''.$clear);
			$this->set(self::dm.'.current.engine', $this->_store->is);
			$this->engine = $loc;
			$this->_engineIsSet = true;
		} else 
			throw new \yii\base\ErrorException("Specified an unsupported engine: $loc");
	}
	
	public function setBase($container)
	{
		if(!empty($container))
			$this->container = $this->_store->getContainerBase($container);
	}
	
	public function uriOf($key, $internal=false)
	{
		$key = $originalKey = explode('.', $key);		
		switch(1)
		{
			case $key[0] == self::dm:
			array_shift($key);
			if($key == [$this->_store->is, 'config'])
					array_unshift($key, self::dm);
			else if($key[0] != $this->container)
				array_unshift($key, self::dm, $this->_store->is, 'config');
			break;
			
			default:
			if($internal === true)
				if($key[0] == $this->container)
					array_unshift($key, self::dm, $this->_store->is, 'config');
				else
					array_unshift($key, self::dm, $this->_store->is, 'config', $this->container);
			else
				if($this->container == 'globals' && $key[0] == 'globals' || $key[0] == Session::settings)
					$key[0] = Session::settings;
				else if($key[0] != $this->container && !in_array($key[0], $this->config('containers')))
					array_unshift($key, $this->container);
			break;
		}
		return implode('.', $key);
	}
	
	public function getDm()
	{
		return self::dm;
	}
	
	/**
	 * Set or get a current setting
	 * @param string|array $name the name of the setting to get
	 * @param mixed $value the value to set
	 * @param boolean $append
	 */
	public function config($name=null, $value=null, $append=false)
	{
		$name = is_array($name) ? implode('.', $name) : $name;
		return (new \nitm\helpers\ArrayHelper)->getOrSetValue($this->_config, $name, $value, $append);
	}
	
	protected function event($name=null, $value=null, $append = false)
	{
		$name = is_array($name) ? implode('.', $name) : $name;
		return (new \nitm\helpers\ArrayHelper)->getOrSetValue($this->_event->data, $name, $value, $append);
	}
	
	/**
	 * Find out where configuration information is being stored in
	 * @return classname of stroage adapter
	 */	
	protected function resolveStoredIn() 
	{
		switch($this->storeIn)
		{
			case 'cache':
			$class = Cache::className();
			$prefix = function ($name) {
				return Session::getPath($name);
			};
			break;
			
			default:
			$class = Session::className();
			$prefix = function ($name) {
				if($name == 'nitm-settings')
					return Session::settings;
				else
					return $name;
			};
			break;
		}
		return [$class, $prefix];
	}
	
	public function getPath($for) 
	{
		$for = explode('.', $for);
		if($for[0] == Session::settings)
			$for[0] = 'globals';
		return implode('.', $for);
	}
	
	/**
	 * Set a local config value based on the storage location
	 * @param string $name
	 * @param mixed $value
	 * @return boolean
	 */
	public function set($name, $value)
	{
		$value = Json::isJson($value) ? Json::decode($value) : $value;
		ArrayHelper::setValue($this->settings, $this->getPath($this->uriOf($name)), $value);
	}
	
	/**
	 * Get a local config value based on the storage location
	 * @param string $name
	 * @return mixed
	 */
	public function get($name, $asArray = false)
	{
		return @ArrayHelper::getValue($this->settings, $this->getPath($this->uriOf($name)), null);
	}
	
	/**
	 * Remove a local config value based on the storage location
	 * @param string $name
	 * @return boolean
	 */
	public function remove($name)
	{
		return ArrayHelper::remove($this->settings, $this->getPath($this->uriOf($name)));
	}
	
	/**
	 * Does a local config value exist?
	 * @param string $name
	 * @return boolean
	 */	
	public function exists($name)
	{
		return ArrayHelper::exists($this->settings, $this->getPath($this->uriOf($name)));
	}
	
	public function containerExists($container)
	{
		$ret_val = true;
		switch(1)
		{
			case !isset($this->settings[$container]):
			case isset($this->settings[$container]) && (count(isset($this->settings[$container]) == 0)):
			$ret_val = false;
			break;
		}
		return $ret_val;
	}
	
	/**
	 * Set a config value based on the storage location
	 * @param string $name
	 * @param mixed $value
	 * @return boolean
	 */
	private function setExternal($name, $value, $duration=120)
	{
		list($class, $prefix) = $this->resolveStoredIn();
		return call_user_func_array([$class, 'set'], [$prefix($name), $value, $duration]);
	}
	
	/**
	 * Get a config value based on the storage location
	 * @param string $name
	 * @return mixed
	 */
	private function getExternal($name, $asArray = false)
	{
		list($class, $prefix) = $this->resolveStoredIn();
		return call_user_func_array([$class, 'get'], [$prefix($name), $asArray]);
	}
	
	/**
	 * Remove a config value based on the storage location
	 * @param string $name
	 * @return boolean
	 */
	private function removeExternal($name)
	{
		list($class, $prefix) = $this->resolveStoredIn();
		return call_user_func_array([$class, 'delete'], [$prefix($name)]);
	}
	
	/**
	 * Doesa config value exist?
	 * @param string $name
	 * @return boolean
	 */	
	private function existsExternal($name)
	{
		list($class, $prefix) = $this->resolveStoredIn();
		return call_user_func_array([$class, 'exists'], [$prefix($name)]);
	}
	
	/*
	 * Write/save the configuration
	 * @param string $container
	 * @param mixed $data
	 * @return boolean success flag
	 */
	public function writeTo($container, $data)
	{
		$sections = '';
		$content = '';
		$ret_val = false;
		$this->container($container);
		switch(!$this->container($container))
		{
			case true:
			$this->createContainer($container, null, $this->_store->is);
			$container = $this->container()->name;
			break;
		}
		$this->_store->write($container, $data);
		return $ret_val;
	}
	
	/*
     * Get the configuration information depending on container and location and store it in $this->config
	 * @param string $container
	 * @param boolean $getValues
     * @return mixed $config
     */
	public function getConfig($container=null, $getValues=false, $updating=false)
	{
		$container = is_null($container) ? $this->container : $container;
		switch($this->_store->is)
		{
			case 'file':
			$container = $this->config('current.path');
			break;
		}
		$this->config('containers', [$container => $container], true);
		return $this->readFrom($this->loadFrom($container, false, true), 'json', $updating);
	}	
	
	/*
	 * Convert configuration betwen formats
	 * @param string $container
	 * @param string $from
	 * @param string $to
	 */
	public function convert($container, $from, $to)
	{
		$ret_val = [
			"success" => false, 
			"message" => "Unable to convert $container from $from to $to"
		];
		switch($this->isSupported($from) && $this->isSupported($to))
		{
			case true:
			$old_engine = $this->_store->is;
			$this->setEngine($from);
			$config = $this->getConfig($from, $container, true, true);
			$this->setEngine($to);
			$this->writeTo($container, $config, $to);
			$ret_val['message'] = "Converted $container from $from to $to";
			$ret_val['success'] = true;
			$ret_val['action'] = 'convert';
			$this->config('current.action', $ret_val);
			$this->setEngine($old_engine);
			break;
		}
	}
	
	/*
	 * Load the configuration
	 * @param string $container
	 * @param boolean $fromLocal From the session?
	 * @param boolean $force Force a load?
	 * @return mixed configuration
	 */
	public function loadFrom($container=null, $fromLocal=false, $force=false)
	{
		$ret_val = null;
		$container = !empty($container) ? $container : $this->container;
		switch($fromLocal === true)
		{
			case true:
			$ret_val = $this->get(self::dm.'.'.$container);
			break;
			
			default:
			$this->setBase($container);
			$ret_val = $this->_store->load($container, $this->section, $force);
			break;
		}
		return $ret_val;
	}
	
	/*
	 * Read the configuration from a database or file
	 * @param mixed $contents
	 * @param string $commentchar
	 * @param string $decode
	 * @param boolean $updating
	 * @return mixed $ret_val
	 */
	public function readFrom($contents=null, $decode='json', $updating=false) 
	{
		$ret_val = [];
		$decode = is_array($decode) ? $decode : [$decode];
		$ret_val = $this->_store->read($contents);
		switch($this->_store->is)
		{
			case 'db':
			case 'file':
			switch(is_array($ret_val) && is_array($decode))
			{
				case true:
				foreach($decode as $dec)
				{
					switch($dec)
					{
						case 'json':
						foreach($ret_val as $sectionName=>$section)
						{
							foreach($section as $name=>$v)
							{
								switch(1)
								{
									case is_array($v) && isset($v['value']) && is_array($v['value']):
									continue;
									break;
									
									case is_array($v) && substr($v['value'], 0, strlen(self::NO_DEC)) == self::NO_DEC:
									$v['value'] = substr($v['value'], strlen(self::NO_DEC), strlen($v['value']));
									break;
									
									case((@$v['value'][0] == "{") && ($v['value'][strlen($v['value'])-1] == "}")) && ($updating === false):
									$v['value'] = ((!is_null($data = json_decode(trim($v['value']), true))) ? $data : $v['value']);
									break;
								}
								switch($updating)
								{
									case false:
									$v = $v['value'];
									break;
									
									default:
									$v = array_merge($v, array_intersect_key($v, array_flip([
											'section_name',
											'container_name',
											'unique_id',
											'unique_name'
										])
									));
									break;
								}
								$section[$name] = $v;
							}
							ksort($section);
							$ret_val[$sectionName] = $section;
						}
						break;
						
						case 'csv':
						array_walk_recursive($ret_val, function (&$v) {
							switch((@$v['value'][0] == "{") && ($v['value'][strlen($v['value'])-1] == "}") && ($updating === false))
							{
								case true:
								$v['value'] = explode(',', $v['value']);
								break;
							}
						});
						break;
					}
				}
				break;
			}
			break;
		}
		return $ret_val;
	}
	
	/*
	 * Comment on a value
	 * @param string $key
	 * @param string $comment
	 */
	public function comment($key=null, $comment=null)
	{
		extract($this->getParams([
			'key' => $key,
			'comment' => $comment
		]));
		$uriOf = $this->uriOf($key);
		$ret_val = [
			"success" => true, 
			"message" => "Updated comment successfully"
		];
		
		$result = $this->_store->comment(($id ? $id : $key), $uriOf, $value, $container);
		if($result['success'])
		{
			$ret_val = array_merge($ret_val, $result, [
				'data' => [$key, $value],
				'class' => $this->classes['success']
			]);
			$this->setEventData(array_merge($ret_val, [
				'key' => $key,
				'value' => $value,
				'action' => "Update Config Comment",
				'message' => "updated comment ($key to '".var_export($value, true)."') in ".$container
			]));
			$this->trigger('afterUpdate');
		}		
		$ret_val['action'] = 'update';
		$ret_val['value'] = rawurlencode($value);
		$this->config('current.action', $ret_val);
		$this->set(self::dm.'.action', $ret_val);
	} 
	
	/*
	 * Create a value to the configuration
	 * @param string $key
	 * @param string $container
	 * @param string sess_member
	 * @return mixed created value and success flag
	 */
	public function create($key=null, $value=null, $container=null)
	{
		extract($this->getParams([
			'key' => $key,
			'value' => $value,
			'container' => $container
		]));
		$uriOf = $this->uriOf($key);
		$ret_val = [
			"success" => false, 
			"message" => "Couldn't create: $key [$value] in $container", 
			"action" => 'create', 
			"class" => $this->classes["failure"]
		];
		
		$this->setBase($container);	
		$this->container($container);
		
		$ret_val = array_merge($ret_val, array_filter($this->_store->create($uriOf, $value, $container, $this->isSection())));
		if($ret_val['success']) {
			$ret_val += [
				'data' => [$key, $value],
				'class' => $this->classes['success']
			];
			$this->setEventData(array_merge($ret_val, [
				'action' => 'Create Config',
				'message' => "created new key->value ($key -> ".var_export($value, true).") to in ".$container
			]));
			$this->trigger('afterCreate');
		}
		
		$this->config('current.action', $ret_val);
		$this->set(self::dm.'.action', $ret_val);
	}
	
	/*
	 * Update a value in the configuration
	 * @param string $key
	 * @param string $container
	 * @param string sess_member
	 * @return mixed updated value and success flag
	 */
	public function update($key=null, $value=null, $container=null)
	{
		extract($this->getParams([
			'key' => $key,
			'value' => $value,
			'container' => $container
		]));
		$uriOf = $this->uriOf($key);
		$value = is_array($value) ? json_encode($value) : $value;
		
		if(is_array($container)) {
			debug_print_backtrace();
			exit;
		}
		$oldValue = $this->config('current.config.'.$key.'.value');
		$ret_val = [
			'success' => false,
			'oldValue' => rawurlencode(is_array($oldValue) ? json_encode($oldValue) : $oldValue),
			'value' => rawurlencode($value),
			'key' => $uriOf,
			'message' => "Unable to update value '".$key."' to '".$value."'"
		];
		
		$ret_val = array_merge($ret_val, array_filter($this->_store->update(($id ? $id : $key), $uriOf, $value, $container, $this->isSection())));
		if($ret_val['success']) {
			$ret_val += [
				'data' => [$key, $value],
				'class' => $this->classes['success']
			];
			$this->setEventData(array_merge($ret_val, [
				'key' => $key,
				'value' => $value,
				'action' => "Update Config",
				'message' => "updated value ($key from '".var_export($ret_val['oldValue'], true)."' to '".var_export($value, true)."') in ".$container
			]));
			$this->trigger('afterUpdate');
		}		
		$ret_val['action'] = 'update';
		$ret_val['value'] = rawurlencode($value);
		$this->config('current.action', $ret_val);
		$this->set(self::dm.'.action', $ret_val);
	}
	
	/*
	 * Delete a value in the configuration
	 * @param string $key
	 * @param string $container
	 * @return mixed deleted value and success flag
	 */
	public function delete($key=null, $container=null)
	{
		extract($this->getParams([
			'key' => $key,
			'container' => $container
		]));
		
		$uriOf = $this->uriOf($key);
		$this->setBase($container);
		
		$ret_val = [
			'success' => false,
			'container' => $container,
			'key' => $uriOf,
			'message' => "Unable to delete ".$uriOf,
			"action" => 'delete', 
			"class" => $this->classes["failure"]
		];
		
		$ret_val = array_merge($ret_val, array_filter($this->_store->delete((!$id ? $key : $id), $uriOf, $container, $this->isSection())));
		if($ret_val['success'])
		{
			$ret_val += [
				'data' => [$key, $result['value']],
				'class' => $this->classes['success']
			];
			$this->setEventData(array_replace($ret_val, [
				'key' => $key,
				'value' => $result['value'],
				'action' => "Delete Config",
				'message' => "deleted value ($key -> '".var_export($result['value'], true)."') from config ".$this->_store->is.": ".$container
			]));
			$this->trigger('afterDelete');
		}
		$this->config('current.action', $ret_val);
		$this->set(self::dm.'.action', $ret_val);
	}
	
	/**
	 * Create a container: file, db entry...etc
	 * @param string $name
	 * @param string $in
	 * @return mixed result
	 */
	public function createContainer(string $name, string $in=null)
	{
		$ret_val = ["success" => false, 'class' => 'error'];
		$ret_val['message'] = "I ".$ret_val['message'];
		$result = $this->_store->createContainer($name, $in);
		if($result['success'])
		{
			$this->setEventData([
				'action' => 'Create Config File',
				'message' => "created a new config container: ".$name
			]);
			$this->trigger('afterCreate');
			$ret_val['class'] = $this->classes['success'];
		}
		$this->config('current.action', $ret_val);
		return $ret_val;
	}
	
	/*---------------------
		Protected Functions
	---------------------*/
	
	/*
	 * Log the data to the DB
	 * @param mixed $data
	 */
	protected function log($data=[])
	{
		$this->initLogging();
		$this->l->addTrans($data['table'], $data['db'], $data['action'], $data['message']);
	}
	
	/*
	 * Get the configuration containers: file or database
	 * @param string $in
	 * @param boolean $multi
	 * @param boolean $objectsOnly
	 * @return mixed
	 */
	protected function getContainers($in=null, $objectsOnly=false)
	{
		$in = is_null($in) ? $this->container : $in;
		$ret_val = $this->_store->getContainers($in, $objectsOnly);
		$this->config('containers', $ret_val);
		$this->config('load.containers', true);
		return $ret_val;
	}
	
	/*
	 * Get the configuration containers: file or database
	 * @param string $in
	 * @return mixed
	 */
	protected function getSections($in=null)
	{
		$in = is_null($in) ? $this->container : $in;
		$ret_val = $this->_store->getSections($in);
		$this->config('sections', $ret_val);
		$this->config('load.sections', true);
		return $ret_val;
	}
	
	protected function isSection()
	{
		return $this->what == 'section';
	}
	
	/*---------------------
		Private Functions
	---------------------*/
	
	private  function removeContainer($in, $name, $ext)
	{
		return $this->_store->removeContainer($in, $name, $ext);
	}
	
	/*
	 * Is this engine supported?
	 * @param string $engine
	 * @return boolean is supported?
	 */
	 private function isSupported($engine)
	 {
		return isset($this->_supported[$engine]);
	 }
	 
	 /*
	  * Get the container for a given value
	  * @param string|int $container
	 * @return \nitm\models\confier\Container
	  */
	private function container($container=null)
	{
		$container = is_null($container) ? $this->container : $container;
		return $this->_store->container($container);
	 }
	 
	/*
	 * Get the section id for a given value
	 * @param string|int $section
	 * @return \nitm\models\confier\Section
	 */
	private function section($section)
	{
		return $this->_store->section($section);
	}
	 
	/*
	 * Get the section id for a given value
	 * @param string|int $section
	 * @param string|int $id
	 * @return \nitm\models\confier\Value
	 */
	private function value($section, $id)
	{
		return $this->_store->value($section, $id);
	}
	 
	private static function hasNew()
	{
		return $this->_store->hasNew();
	}
	
	private function getParams($params=[])
	{
		$ret_val = [
			'id' => $this->id,
			'key' => $this->name,
			'value' => $this->value,
			'comment' => $this->comment,
			'container' => $this->container
		];
		switch($this->what)
		{
			case 'value':
			$ret_val['key'] = $this->name;
			break;
			
			case 'section':
			$ret_val['key'] = $this->section;
			break;
			
			case 'container':
			$ret_val['key'] = $this->container;
			break;
		}
		return array_merge($ret_val, array_filter($params));
	}
}
?>