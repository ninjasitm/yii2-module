<?php

namespace nitm\models\configer\formats;

use Yii;
use nitm\helpers\Json;

/**
 * Json text file parser for configer
 */
class JsonFile extends BaseFileFormat
{
	public function prepare(array $data)
	{
		return Json::encode($data);
	}
	 
	public function read($data)
	{
		return Json::isJson($data) ? Json::decode($data) : $data;
	}
	
	public function createSection($name)
	{
		$this->contents[$name] = [];
	}
	
	public function createValue($section, $name, $value)
	{
		$this->contents[$section][$name] = $value;
		return true;
	}
	
	public function updateSection($section, $name)
	{
		if(isset($this->contents[$section]) && !isset($this->contents[$name]))
		{
			$this->contents[$name] = $this->contents[$section];
			unset($this->contents[$section]);
			return true;
		}
		return false;
	}
	
	public function updateValue($section, $name, $value)
	{
		$this->contents[$section][$name] = $value;
		return true;	
	}
	
	public function deleteSection($section, $name)
	{
		unset($this->contents[$section]);
		return true;	
	}
	
	public function deleteValue($section, $name, $value)
	{
		unset($this->contents[$section][$name]);
		return true;	
	}
}
