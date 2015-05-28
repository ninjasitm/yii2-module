<?php

namespace nitm\helpers;

use yii\helpers\Json as JsonHelper;

class Json
{
	public static function isJson($value)
	{
		return is_string($value) && ($value[0] == '{' && $value[strlen($value)] == '}');
	}
	
	/**
	 * Return a json decoded value or the original value
	 * @param mixed $value, should be a tring
	 * @return array
	 */
	public static function decode($value, $asArray=true)
	{
		if(is_string($value))
			return (is_null($decoded = JsonHelper::decode(trim($value), $asArray)) ? $value : $decoded);
		return $value;
	}
	
	/**
	 * Return a json encoded value or the original value
	 * @param mixed $value, should be a tring
	 * @return array
	 */
	public static function encode($value, $options=320)
	{
		if(is_array($value))
			return JsonHelper::encode($value, $options);
		return $value;
	}
}
?>