<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace nitm\helpers;

/**
 * ArrayHelper provides additional array functionality that you can use in your
 * application.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class ArrayHelper extends \yii\helpers\ArrayHelper
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
}
