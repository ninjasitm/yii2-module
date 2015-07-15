<?php

namespace nitm\models\configer\formats;

use Yii;
use nitm\helpers\Directory;

/**
 * Json text file parser for configer
 */
abstract class BaseFileFormat extends \yii\base\Object
{
	protected $contents;
	protected $commentChar = '#';
	
	public function setContents($contents)
	{
		$this->contents = $contents;
	}
	
	public function getContents()
	{
		return $this->contents;
	}
	
	abstract public function read($contents);
	
	abstract public function prepare(array $data);
	
	abstract public function createSection($name);
	
	abstract public function updateSection($section, $name);
	
	abstract public function createValue($section, $name, $value);
	
	abstract public function updateValue($section, $name, $value);
	
	abstract public function deleteSection($section, $name);
	
	abstract public function deleteValue($section, $name, $value);
	
	protected function command($comand, $args=null)
	{
		$args['command'] = vsprintf($args['command'], array_map(function ($v) {return preg_quote($v, DIRECTORY_SEPARATOR);}, $args['args'])).' "'.$container.'.'.$this->types[$this->location].'"';
		exec($args['command'], $output, $cmd_ret_val);
		return $cmd_ret_val;
	}
}
