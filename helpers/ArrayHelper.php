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
	//data and logic flags
	const FLAG_NULL = 'null:';
	const FLAG_ASIS = 'asis:';
	const FLAG_IGNORE = 'ignore:';

	/**
	 * Function to split data based on conditionals
	 * @param mixed $a1 first array to split, usually the keys
	 * @param mixed $a2 second array to be split, usually data
	 * @param mixed $c comparison operator to use
	 * @param mixed $xor glue to connect pieces of array conditionals
	 * @param boolean $esc escape data
	 * @param boolean $quote_fields should fields be quoted?
	 * @param boolean $quote_data should data be quoted?
	 * @return mixed
	*/
	public static function splitc($a1, $a2=null, $c='=', $xor='AND', $esc=false, $quote_fields=false, $quote_data=false)
	{
		$keys = (is_array($a1)) ? (is_null($a2) ? array_keys($a1) : array_values($a1)) : [$a1];
		$data = (is_array($a2)) ? array_values($a2) : (is_null($a2) ? array_values($a1) : [$a2]);
// 		$c = (is_array($c) && (sizeof($c) == 1)) ? array_shift($c) : $c;
		if(($s = sizeof($keys)) == sizeof($data))
		{
			$ret_val = "";
			$c_arr = is_array($c);
			$xor_arr = is_array($xor);
			for($ci = 0; $ci < $s; $ci++)
			{
				$field = ($quote_fields === true) ? "`".$keys[$ci]."`" : $keys[$ci];
				$value = (($data[$ci] === self::FLAG_NULL) || ((is_null($data[$ci]) && !is_numeric($data[$ci])) === true)) ? null : (($esc === true) ? $data[$ci] : $data[$ci]);
				switch(substr($keys[$ci], 0, strlen(self::FLAG_IGNORE)) === self::FLAG_IGNORE)
				{
					case true:
					switch(isset($xor[$ci]))
					{
						case true:
						$field = '';
						$value = '';
						break;

						default:
						continue 2;
						break;
					}
					break;
				}
				switch(1)
				{
					case substr($field, 0, strlen(self::FLAG_ASIS)) === self::FLAG_ASIS:
					$value = null;
					$field = substr($field, strlen(self::FLAG_ASIS), strlen($field));
					$match = '';
					break;

					default:
					switch(1)
					{
						case $field == self::FLAG_NULL:
						case $field == null:
						$match = null;
						switch(is_array($data[$ci]))
						{
							case true:
							$value = "(".static::splitc($value['keys'], $value['data'], $value['operand'], $value['xor'], $esc, $quote_fields, $quote_data).")";
							break;
						}
						break;

						case is_numeric($c) && !empty($c):
						case is_string($c) && !empty($c):
						$match = $c;
						break;

						default:
						switch($c_arr == true)
						{
							case true:
							$match = isset($c[$ci]) ? (($c[$ci] == self::FLAG_NULL) ? null : $c[$ci]) : '=';
							break;

							default:
							$match = ($c == self::FLAG_NULL) ? null : (($c == null) ? '=' : $c);
							break;
						}
					break;
					}
				}
				$multi_cond = ($xor_arr === true && isset($xor[$ci])) ? (($xor[$ci] == null) ? " AND " : " $xor[$ci] ") : (($xor == null) ? " AND " : (is_array($xor) && !isset($xor[$ci])) ?  " AND " : " $xor ");
				switch(1)
				{
					case is_null($value):
					$ret_val .= $field.$match;
					break;

					case is_numeric($value):
					$ret_val .= $field.$match.$value;
					break;

					case is_string($value) && $quote_data:
					$quoter = ($quote_data === false) ? '' : ($quote_data === true) ? '"' : '';
					$ret_val .= $field.$match."$quoter".$value."$quoter";
					break;

					case is_array($value):
					$ret_val .= static::splitc(array_keys($value), array_values($value), $c, $xor, $esc, $quote_fields, $quote_data);
					break;

					default:
					$ret_val .= $field.$match.$value;
					break;
				}
				switch($ci == ($s-1))
				{
					case false:
					$ret_val .= $multi_cond;
					break;
				}
			}
		}
		else
		{
			static::generateError(-1, "You specified incorrect lengths for the keys and data to check Helper::splitc('".print_r($a1)."', '".print_r($a2)."');");
			return null;
		}
		return $ret_val;
	}

	/**
	 * function to split fields by a delimiter
	 * @param mixed $array array to split
	 * @param mixed $splitter comparison operator to use
	 * @param boolean $esc escape data
	 * @param string $sur Surround the data with?
	 * @param integer $max_len The maximum length
	 * @param boolean $quote_data should data be quoted?
	 * @return mixed
	 */
	public static function splitf($array, $splitter=',', $esc = true, $sur='', $max_len=null, $num=true, $print=false)
	{
		$ret_val = array();
		switch(empty($array))
		{
			case false:
			$array = is_array($array) ? $array : array($array);
			$data = array_values($array);
			foreach($data as $d)
			{
				switch(1)
				{
					case substr($d, 0, strlen(self::FLAG_ASIS)) === self::FLAG_ASIS:
					$ret_val[] = substr($d, strlen(self::FLAG_ASIS), strlen($d));
					break;

					default:
					$ret_val[] = $sur.$d.$sur;
					break;
				}
			}
			break;
		}
		switch($print == true)
		{
			case true:
			self::pr($ret_val);
			break;
		}
		return implode($splitter, $ret_val);
	}

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
    public static function exists($array, $key, $emptyCheck=false)
    {
		//By default the path exists
		$ret_val = true;
		$hierarchy = explode('.', $key);

		foreach($hierarchy as $key)
		{
			if(is_array($array) && isset($array[$key])) {
				$array = $array[$key];
			} else if(is_object($array) && property_exists($object, $key)) {
				$array = $object->$key;
			} else {
				$ret_val = false;
				break;
			}
			if (($emptyCheck === true) && $array == []) {
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
		$keyCount = count($keys);
		for($i = 0; $i < $keyCount; $i++)
		{
			$key = array_shift($keys);
			if(is_array($array) && isset($array[$key]) && ($i == $keyCount-1)) {
				$ret_val = $array[$key];
				unset($array[$key]);
				return $ret_val;
			}
			if(is_array($array) && isset($array[$key]) && is_array($array[$key])) {
				$ret_val = static::remove($array[$key], $keys);
			} else
				break;
		}
		return !$ret_val ? $default : $ret_val;
	}


	/**
	 * Return data from source specified by $getter
	 * @param array $source. Indexed by the ids of the items
	 * @param boolean $dsOnly Only return the ids
	 * @param array|callablke $getter
	 * @return array
	 */
	public static function filter(array $source, $idsOnly=false, $getter=null, $default=null, $ignoreEmpty=null, $idAttr='id')
	{
		$ret_val = [];
		$ignoreEmpty = $ignoreEmpty ?: [$idAttr];
		if($idsOnly) {
			$ret_val = parent::getColumn($source, $idAttr);
			if(empty($ret_val))
				$ret_val = $default ?: null;
		} else {
			$source = (array)$source;
			$source = array_filter($source);
			$shouldSkip = function ($value) use($ignoreEmpty){
				foreach($ignoreEmpty as $key) {
					if(empty(self::getValue($value, $key, false)))
						return true;
				}
				return false;
			};
			if(count($source)) {
				foreach($source as $id=>$d)
				{
					if($shouldSkip($d))
						continue;
					if(is_callable($getter))
						$ret_val[$id] = call_user_func($getter, $d);
					else if(is_array($getter)) {
						$d = $d instanceof \yii\data\Arrayable ? $d->toArray() : (array)$d;
						$ret_val[$id] = array_intersect_key($d, $getter);
					} else if(!is_null($d) || !empty($d))
						$ret_val[$id] = $d;
				}
			}
		}
		return $ret_val;
	}
}
