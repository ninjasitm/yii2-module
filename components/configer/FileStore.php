<?php

namespace nitm\components\configer;

use Yii;
use yii\base\Object;
use nitm\helpers\Session;
use nitm\helpers\Cache;
use nitm\helpers\ArrayHelper;
use nitm\helpers\Json;
use nitm\models\configer\Container;
use nitm\models\configer\Section;
use nitm\models\configer\Value;
use nitm\models\configer\File;

/**
 * Filestore handler for Configer
 * @package nitm\components\configer
 * See \nitm\components\configer\BaseStore for details
 */

class FileStore extends BaseStore 
{
	//public data
	public $is = 'file';
	public $type = 'json';
	public $backups = true;
	public $backupExtention = '.cfg.bak';
	public $commentChar = '#';
	public $dir = "config";
	
	protected $defaultDir = 'config/ini/';
	
	private $_types = ['json' => 'json', 'xml' => 'xml', 'file' => 'cfg'];
	
	public function init()
	{
		parent::init();
		$this->resource = new File();
		$this->resource->init();
	}
	
	public function prepare($container='config', $getValues=false)
	{
		return $this->resource->prepare();
	}
	
	public function getContainerBase($container)
	{
		$container = explode('.', $container);
		$container = array_shift($container);
		$container = empty($container) ? $this->container : $container;
		return ($container[0] == '@') ? substr($container, 1, strlen($container)) : $container;
	}
	
	/*
	 * Set the directory for the configuration. Backups will also be stroed here
	 * @param string $dir
	 */
	public function setDir($dir=null)
	{
		$this->dir = (is_dir($dir)) ? $dir : $this->defaultDir;
	}
	
	/*
	 * Write/save the configuration
	 * @param string $container
	 * @param mixed $data
	 * @param string $engine
	 * @return boolean success flag
	 */
	public function write($container, $data)
	{
		$ret_val = false;
		if($this->resource->prepare($data)) {
			$container = ($container[0] == '@') ? $this->dir.substr($container, 1, strlen($container)) : $container;
			$ret_val = $this->resource->write($container, $this->backups);
		}
		return $ret_val;
	}
	
	public function load($container, $fromSection=false)
	{
		$container = $this->resolveDir($this->dir);
		$container = $container.'.'.$this->_types[$this->type];
		$ret_val = $this->resource->load($container, $force);
	}
	
	public function read($contents) 
	{
		$ret_val = $this->resource->read($contents, $this->commentChar);
	}
	
	public function create($name, $value, $container)
	{
		$args = [];
		$ret_val = [
			'success' => false,
		];
		
		list($name, $section, $hierarchy, $isSection, $isValue) = $this->resolveNameAndSection($key, true);
		
		$container = $this->resolveDir($this->dir);
		$proceed = true;
		switch(sizeof($hierarchy))
		{
			///we might be creating a container
			case 1:
			if(!empty($container))
				$this->createContainer($container);
			break;
		
			default:
			switch(1)
			{
				case !$container:
				$ret_val['debug'] = "Sorry I cannot create a value to a container that doesn't exist\nPlease try again again by passing the correct parameters to me.\ncreate($key, ".var_export($value).", ".basename($container).", $sess_member) (0);";
				$proceed = false;
				break;
			
				case !$key:
				$ret_val['debug'] = "Sorry I cannot create an empty key\nPlease try again again by passing the correct parameters to me.\ncreate($key, ".var_export($value, true)."), ".basename($container).", $sess_member) (1);";
				$proceed = false;
				break;
			}
			break;
		}
		if($proceed)
		{
			switch(sizeof($hierarchy))
			{
				//we're creating a section
				case 5:
				case 2:
				$success = $this->resource->createSection($name);
				$message = "Added new section [".$section."] to ".$container;
				break;
				
	
				//we're creating a value
				case 6:
				case 3:
				$sucess = $this->resource->createValue($section, $name, $value);
				$message = "Added new config option [".$name."] to ".$section;
				break;
			}
			//sed should return an empty value for success when updating files
			switch($success)
			{
				case 0:
				$ret_val['unique'] = $section.'.'.$name;
				$ret_val['name'] = $name;
				$ret_val['container_name'] = $ret_val['container'];
				$ret_val['section_name'] = $section;
				$ret_val['unique_id'] = $key;
				$ret_val['message'] = $message;
				$ret_val['success'] = true;
				break;
				
			}
		}
		return $ret_val;
	}
	
	public function update(int $id, $key, $value, $container)
	{
		$args = [];
		$ret_val = ['success' => false];
		$container = $this->resolveDir($this->dir);
		
		list($name, $section, $hierarchy, $isSection, $isValue) = $this->resolveNameAndSection($key, true);
		
		$proceed = true;
		
		switch(1)
		{
			case !$container:
			$ret_val['debug'] ="Sorry I cannot update a value in a file that doesn't exist\nPlease try again again by passing the correct parameters to me.\nupdate($key, ".var_export($value).", ".basename($container).", $sess_member) (0);";
			$proceed = false;
			break;
			
			case !$key:
			$ret_val['debug'] ="Sorry I cannot update an empty key\nPlease try again again by passing the correct parameters to me.\nupdate($key, ".var_export($value, true).", ".basename($container).", $sess_member) (1);";
			$proceed = false;
			break;
		}
		if($proceed)
		{
			switch(sizeof($hierarchy))
			{
				//we're updating a section
				case 4:
				case 2:
				$success = $this->resource->updateSection($section, $value);
				$message = "Updated the section name from ".$name." to $value";
				break;
			
				//no support for updating section names as of yet
				case 5: 
				case 3:    
				$success = $this->resource->updateValue($section, $name, $name, $value);
				$message = "Updated the value name from ".$name." to $value";
				break;
			}
			//sed should return an empty value for success when updating files
			if($success == 0) {
				$ret_val['message'] = $message;
				$ret_val['success'] = true;
				
			}
		}
		return $ret_val;
	}
	
	public function delete(int $id, $key, $container)
	{	
		$args = [];
		
		list($name, $section, $hierarchy, $isSection, $isValue) = $this->resolveNameAndSection($key, true);
		
		$container = $this->resolveDir($this->dir);
		$proceed = true;
		switch(1)
		{
			case !$container:
			$ret_val['debug'] = "Sorry I cannot delete a value from a file that doesn't exist\nPlease try again again by passing the correct parameters to me.\delete($key, ".var_export($value, true)."), ".basename($container).", $sess_member) (0);";
			$proceed = false;
			break;
			
			case !$id:
			$ret_val['debug'] = "Sorry I cannot delete an empty key\nPlease try again again by passing the correct parameters to me.\ndelete($key, ".var_export($value).", ".basename($container).", $sess_member) (1);";
			$proceed = false;
			break;
		}
		if($proceed)
		{
			switch(sizeof($hierarchy))
			{
				//are we deleting a value/line?
				case 6:
				case 3:
				$success = $this->resource->deleteValue($name, $sectionName);
				$message = "Deleted value ".$hierarchy." in ".$sectionName;
				break;
				
				//we're deleting a section
				case 5:
				case 2:
				$success = $this->resource->deleteSection($sectionName);
				$args['command'] = "sed -i '/^\[%s\]/,/^$/d' ";
				$args['args'] = [$name];
				$message = "Deleted the section ".$name;
				break;
				
				//we're deleting a container
				case 1:
				$success = $this->resource->deletefile($container);
				$message = "Deleted the file ".$container;
				break;
			}
			//sed should return an empty value for success when updating files
			if($success) {
				$ret_val['message'] = $message;
				$ret_val['success'] = true;
			}
		}
		return $ret_val;

	}
	
	public function createContainer($name, $in=null)
	{
		$ret_val = ["success" => false];
		$in = (!is_dir($in)) ? $this->dir : $in;
		$file = $in.$name.'.'.$this->_types[$this->type];
		switch($this->resource->createFile($file))
		{
			case true:
			$ret_val['success'] = true;
			$ret_val['message'] = "The system was able to create the config file".basename($file);
			break;
				
			default:
			$ret_val['message'] = "The system was unable to create the config file because ".basename($file)." already exists";
			break;
		}
		return $ret_val;
	}
	
	public function removeContainer($name, $in=null)
	{
		$ret_val = ["success" => false];
		$file = $in.$name.'.'.$this->_types[$this->type];
		$ret_val['message'] = 'I was unable to delete the config file '.basename($file);
		switch(empty($name))
		{
			case false:
			if($this->resource->deleteFile($file))
			{
				$this->trigger('afterDelete', new Event($this, [
						'table' => 'NULL',
						'db' => "NULL",
						'action' => "Delete Config File",
						'message' => $action[$result], "On ".date("F j, Y @ g:i a")." user ".$this->get('securer.username')." deleted config file: ".basename($file)
					])
				);
				$ret_val['success'] = true;
				$ret_val['message'] = 'I was able to delete the config file '.basename($file);
			}
			break;
		}
	}
	
	 /*
	  * Get the container for a given value
	  * @param string|int $container
	  * @return int containerid
	  */
	public function container($container)
	{
		$containerKey = $this->containerKey($container);
		
		if(isset(static::$_containers[$containerKey]))
			$this->containerModel = $ret_val = static::$_containers[$containerKey];
		else if(Cache::cache()->exists($containerKey)) {
			$this->containerModel = $ret_val = static::$_containers[$containerKey] = Cache::getModel($this, $containerKey);
		} else {
			$construct = is_numeric($section) ? ['id' => $section] : ['name' => $section];
			$this->containerModel = $ret_val = new File($construct);
			Cache::setModel($containerKey, [
				File::className(),
				array_merge(ArrayHelper::toArray($this->containerModel), [
					'values' => ArrayHelper::toArray($this->containerModel->values),
					'sections' => ArrayHelper::toArray($this->containerModel->sections)
				])
			], false, 30);
		}
	}
	
	public function section($section, $container=null)
	{
		$ret_val = null;
		if(!$this->containerModel)
			throw new \yii\base\ErrorException("The container model is not set");
			
		switch(isset($this->container($container)->sections[$section]))
		{
			case false:
			if(!$this->sectionModel instanceof File)
			{
				$construct = is_numeric($section) ? ['id' => $section] : ['name' => $section];
				$construct->container_id = $this->containerModel->id;
				
				$this->sectionModel = new File($construct);
				$ret_val = $this->sectionModel;
				$this->containerModel->sections = array_merge($this->containerModel->sections, [$section => $ret_val]);
				Cache::setModel($this->containerKey($this->container()->name), $this->containerModel);
			}
			break;
				
			default:
			$ret_val = $this->containerModel->sections[$section];
			break;
		}
		return $ret_val;
	}
	
	public function value($section, $id)
	{
		return ArrayHelper::getValue($this->containerModel, 'sections.'.$section.'.'.$id, null);
	}
	
	public function getSections($in=null)
	{
		$in = ($in == null) ? $this->dir : $in;
		$ret_val = $this->resource->getNames($in);
		return $ret_val;
	}
	
	public function getContainers($in, $objectsOnly=false)
	{
		$in = ($in == null) ? $this->dir : $in;
		if(!isset(static::$_containers))
			static::$_containers = $this->resource->getFiles($in, $objectsOnly);
		return static::$_containers;
	}
	
	/*
	 * Get the proper path for this directory
	 * @param string $container
	 * @return string
	 */
	protected function resolveDir($path)
	{
		return ($path[0] == '@') ? $this->dir.rtrim($path, '/') : $path;
	}
}
?>