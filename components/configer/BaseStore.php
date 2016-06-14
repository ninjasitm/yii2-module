<?php

namespace nitm\components\configer;

use nitm\helpers\ArrayHelper;

/**
 * The base stroe interface for configer storage
 */

abstract class BaseStore extends \yii\base\Object
{
	public $is;
	//The link to the storage location
	protected $resource;
	protected $containerModel;
	protected $sectionModel;
	protected static $_containers;
	protected static $hasNew;

	/*
     * Prepare the config info for updating
	 * @param string $container
	 * @param boolean $getValues
     * @return mixed $config
     */
	public function prepare($container, $getValues)
	{
		return true;
	}

	/**
	 * Get Container base
	 */
	public function getContainerBase($container)
	{
		return $container;
	}

	/**
	 * Get a key for use in a cache
	 * @param string $container
	 * @return string key
	 */
	protected function containerKey($container)
	{
		return 'config-container-'.$container;
	}

	/*
     * Get the configuration information depending on container and location and store it in $this->config
	 * @param string $engine
	 * @param string $container
	 * @param boolean $getValues
     * @return mixed $config
     */
	public function getConfig($container, $force)
	{
		return $this->read($this->load($container, $force));
	}

	/*
	 * Write/save the configuration
	 * @param string $container
	 * @param mixed $data
	 * @return boolean success flag
	 */
	abstract public function write($contianer, $data);

	/*
	 * Load the configuration
	 * @param string $container
	 * @param boolean $force Force a load?
	 * @return mixed configuration
	 */
	abstract public function load($container, $fromSection=false);

	/*
	 * Read the configuration from a database or file
	 * @param mixed $contents
	 * @return mixed $ret_val
	 */
	abstract public function read($contents);

	/*
	 * Handle creating config
	 * @param string|int $key
	 * @param mixed $value
	 * @param string|int $container
	 * @param boolean Is this a section?
	 * @return mixed
	 */
	abstract public function create($key, $value, $container, $isSection=false);

	/*
	 * Handle updating
	 * @param int $id
	 * @param string|int $key
	 * @param mixed $value
	 * @param string|int $container
	 * @param boolean Is this a section?
	 * @return mixed
	 */
	abstract public function update($id, $key, $value, $container, $isSection=false);

	/*
	 * Handle deleting in DB or in file to simplify delete function
	 * @param int $id
	 * @param string|int $key
	 * @param string|int $container
	 * @param boolean Is this a section?
	 * @return mixed
	 */
	abstract public function delete($id, $key, $container, $isSection=false);

	/**
	 * Create a container: file, db entry...etc
	 * @param string $name
	 * @param string $in
	 * @return mixed result
	 */
	abstract public function createContainer($name, $in=null);

	/**
	 * Remove a container: file, db entry...etc
	 * @param string $name
	 * @param string $in
	 * @return mixed result
	 */
	abstract public function removeContainer($name, $in=null);

	/*
	 * Get the container id for a given value
	 * @param string|int $container
	 * @return \nitm\models\configer\Container
	 */
	abstract public function container($container);

	/*
	 * Get the section id for a given value
	 * @param string|int $section
	 * @return \nitm\models\confier\Section
	 */
	abstract public function section($section, $container=null, $asArray=true);

	/*
	 * Get a value
	 * @param string|int $section
	 * @param string|int $id
	 * @return \nitm\models\confier\Value
	 */
	abstract public function value($section, $id, $key=null, $asArray=true);

	/*
	 * Get the configuration containers: file or database
	 * @param string $in
	 * @return mixed
	 */
	abstract public function getSections();

	abstract public function getContainers($in, $objectsOnly=false);

	protected static function resolveNameAndSection($key, $updating=false)
	{
		$hierarchy = is_array($key) ? $key : explode('.', $key);
		$size = count($hierarchy);

		$name = end($hierarchy);
		$sectionName = $size == 2 ? $hierarchy[0] : $hierarchy[$size - 2];

		return [
			'name' => $name,
			'sectionName' => $sectionName,
			'hierarchy' => $hierarchy,
		];
	}

	protected static function getLastChecked()
	{
		return ArrayHelper::getValue($_SESSION, '__configLastChecked', date('Y-m-d H:i:s', 0));
	}

	protected static function setLastChecked()
	{
		$_SESSION['__configLastChecked'] = date('Y-m-d H:i:s', strtotime('now'));
	}
}
?>
