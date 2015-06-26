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
	public static function diff($array1, $array2, $mode='default') {
		switch($mode)
		{
			case 'assoc':
			$difference = static::diffAssoc($array1, $array2);
			break;
			
			case 'key':
			$difference = static::diffKey($array1, $array2);
			break;
			
			default:
			$difference = static::diffSimple($array1, $array2);
			break;
		}
		return $difference;
	}
	
	/**
	 * Lazy copy pasta
	 * http://php.net/manual/en/function.array-diff-assoc.php
	 */
	public static function diffAssoc($array1, $array2)
	{
		$difference = [];
		foreach($array1 as $key => $value) {
			if( is_array($value) ) {
				if( !isset($array2[$key]) || !is_array($array2[$key]) ) {
					$difference[$key] = $value;
				} else {
					$new_diff = static::diffAssoc($value, $array2[$key]);
					if( !empty($new_diff) )
						$difference[$key] = $new_diff;
				}
			} else if( !array_key_exists($key,$array2) || $array2[$key] !== $value ) {
				$difference[$key] = $value;
			}
		}
		return $difference;
	}
	
	/**
	 * Lazy copy pasta
	 * http://php.net/manual/en/function.array-diff.php
	 */
	function diffSimple($array1, $array2) {
		$aReturn = array();
	  
		foreach ($array1 as $mKey => $mValue) {
			if (array_key_exists($mKey, $array2)) {
				if (is_array($mValue)) {
					$aRecursiveDiff = static::diffSimple($mValue, $array2[$mKey]);
					if (count($aRecursiveDiff)) { $aReturn[$mKey] = $aRecursiveDiff; }
				} else {
					if ($mValue != $array2[$mKey]) {
						$aReturn[$mKey] = $mValue;
					}
				}
			} else {
				$aReturn[$mKey] = $mValue;
			}
		}
	  
		return $aReturn;
	} 
	
	/**
	* @author Gajus Kuizinas <gk@anuary.com>
	* @version 1.0.0 (2013 03 19)
	*/
	function diffKey(array $array1, array $array2) {
		$diff = array_diff_key($array1, $array2);
		$intersect = array_intersect_key($array1, $array2);
	   
		foreach ($intersect as $k => $v) {
			if (is_array($array1[$k]) && is_array($array2[$k])) {
				$d = static::diffKey($array1[$k], $array2[$k]);
			   
				if ($d) {
					$diff[$k] = $d;
				}
			}
		}
	   
		return $diff;
	}

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
				else if(is_array($array[$key]) && !is_array($value))
					$array[$key][] = $value;
				else
					$array[$key] = $value;
			}
			else if (is_object($array)) {
				$array->$key = ($append === true) ? $array->$key.$value : $value;
				return true;
			} else {
				if($append === true && isset($array[$key]))
					$array[$key] .= $value;
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
		else if (is_string($key) && is_array($array))
			return self::getValue($array, $key);
		//Or we may Want all of the options
		else if (is_null($key) && is_null($value))
			return $array;
		else
			return null;
	}
    
	/**
     * Check to see if a certain key exists in an array
     * @param array|object $array
	 * @param string $key
	 * @return mixed
     */
    public static function exists($array, $key)
    {
		//By default the path exists
		$ret_val = true;
		$hierarchy = explode('.', $key);
		
		foreach($hierarchy as $key)
		{
			if(is_array($array) && isset($array[$key]))
				$array = $array[$key];
			else if(is_object($array) && property_exists($object, $key))
				$array = $object->$key;
			else {
				$ret_val = false;
				break;
			}
		}
		return $ret_val;
    }
		
	
	/*
	 * Search and delete values in $array
	 * @param mixed $array
	 * @param mixed $keys
	 * @param mixed $data
	 * @return bool
	 */
	public static function remove(&$array, $keys, $default=null)
	{
		$ret_val = $removed = false;
		$notFound = true;
		$keys = is_array($keys) ? $keys : [$keys];
		$key = null;
		for($i = 0; $i < count($keys); $i++)
		{
			$key = array_shift($keys);
			if(is_array($array) && isset($array[$key]) && !is_array($array[$key])) {
				$ret_val = $array[$key];
				unset($array[$key]);
				return $ret_val;	
			}
			if(is_array($array) && isset($array[$key]) && is_array($array[$key])) {
				return static::remove($array[$key], $keys);
			}
		}
		return !$ret_val ? $default : $ret_val;
	}
}
