<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace nitm\helpers;

use yii\helpers\ArrayHelper as BaseArrayHelper;

/**
 * ArrayHelper provides additional array functionality that you can use in your
 * application.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class ArrayHelper extends BaseArrayHelper
{
	public static function mapRecursive($array, $callback) {
		if(is_array($array) || $array instanceof ArrayAccess)
		{
			foreach ($array as $key => $value) {
				if (is_array($value)) {
					$array[$key] = static::mapRecursive($array[$key], $callback);
				}
				else {
					$array[$key] = call_user_func($callback, (array)$array[$key]);
				}
			}
		}
        return $array;
    }
	
	public static function filterRecursive(&$array, $callback)
	{
		$ret_val = true;
		if(is_array($array) || $array instanceof ArrayAccess)
		{
			foreach ($array as $key => $value) {
				if(is_array($value)) {
					if(!static::filterRecursive($array[$key], $callback))
						unset($array[$key]);
				} else if(!call_user_func($callback, $array[$key])) {
					unset($array[$key]);
					$ret_val = false;
				}
			}
		}
		else if(!call_user_func($callback, $array)){
			return false;
		}
		return $ret_val;
	}
	
	public function setValue(&$array, $key, $value, $append=false)
	{
        if ($key instanceof \Closure) {
            return $key($array, $default);
        }
		
        if (($pos = strrpos($key, '.')) !== false) {
			$keys = explode('.', $key);
			$name = array_shift($keys);
			if(!isset($array[$name]))
				$array[$name] = [];
				
			self::setValue($array[$name], implode('.', $keys), $value, $append);
        } else {
			if (is_array($array) && array_key_exists($key, $array)) {
				if(is_array($array[$key]) && is_array($value))
					$array[$key] = ($append === true) ? array_merge($array[$key], $value) : $value;
				else
					$array[$key] = ($append === true) ? $array[$key].$value : $value;
			}
			else if (is_object($array)) {
				$array->$key = ($append === true) ? $array->$key.$value : $value;
				return true;
			} else {
				if($append === true)
					$array[$key][] = $value;
				else
					$array[$key] = $value;
				return true;
			}
			return false;
		}
    }
	
	public function getOrSetValue(&$array, $key=null, $value=null, $append=false)
	{
		//Merge the view options with this array
		if(is_null($key) && is_array($value))
			$array = array_merge($array, $value);
		//If we have a value and a name then set it
		else if(!is_null($value) && is_string($key))
			self::setValue($array, $key, $value, $append);
		//Otherwise we may be looking for a specific value
		else if (is_string($key))
			return self::getValue($array, $key);
		//Or we may Want all of the options
		else if (is_null($key) && is_null($value))
			return $array;
		else
			return null;
	}
}
