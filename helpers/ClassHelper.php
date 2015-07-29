<?php

namespace nitm\helpers;

use yii\db\ActiveRecord;
use yii\base\Model;


class ClassHelper extends Model
{
	
	/*
	 * Return a string imploded with ucfirst characters
	 * @param string $name
	 * @return string
	 */
	public static function properName($value)
	{
		$ret_val = preg_replace('/[\-\_]/', " ", $value);
		return implode(' ', array_map('ucfirst', explode(' ', $ret_val)));
	}
	
	/*
	 * Return a string imploded with ucfirst characters with no spaces
	 * @param string $name
	 * @return string
	 */
	public static function properFormName($value)
	{
		$ret_val = preg_replace('/[\-\_]/', " ", $value);
		return implode('', array_map('ucfirst', explode(' ', $ret_val)));
	}
	
	/*
	 * Return a string imploded with ucfirst characters
	 * @param string $name
	 * @return string
	 */
	public static function properClassName($value)
	{
		$ret_val = is_null($value) ?  static::className() : preg_replace('/[\-\_]/', " ", $value);
		return implode('', array_map('ucfirst', explode(' ', static::properName($ret_val))));
	}
	
	public static function getNamespace($className)
	{
		return (new \ReflectionClass($className))->getNamespaceName();
	}
}
?>
