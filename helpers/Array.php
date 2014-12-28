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
        foreach ($array as $key => $value) {
            if (is_array($array[$key])) {
                $array[$key] = static::arrayMapRecursive($callback, $array[$key]);
            }
            else {
                $array[$key] = call_user_func($callback, $array[$key]);
            }
        }
        return $array;
    }
}
