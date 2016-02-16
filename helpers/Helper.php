<?php

namespace nitm\helpers;

use yii\db\ActiveRecord;
use yii\base\Model;


class Helper
{
	/**
	 * Print a pre formatted value
	 */
	public static function pr()
	{
		foreach(func_get_args() as $data)
		{
			if(!empty($data))
			{
				echo "<pre>".print_r($data)."</pre>";
			}
		}
	}

	/**
	 * Function to return boolean value of a variable
	 * @param string | int $var = value
	 */
	public static function boolVal($val)
	{
		$ret_val = 'nobool';
		switch(true)
		{
			case (true === $val):
			case (1 === $val) || ('1' === $val):
			case (is_string($val) && (strtolower($val) === 'true')):
			case (is_string($val) && (strtolower($val) === 'on')):
			case (is_string($val) && (strtolower($val) === 'yes')):
			case (is_string($val) && (strtolower($val) === 'y')):
			$ret_val = true;
			break;

			case (false === $val):
			case (0 === $val) || ('0' === $val):
			case (is_string($val) && (strtolower($val) === 'false')):
			case (is_string($val) && (strtolower($val) === 'off')):
			case (is_string($val) && (strtolower($val) === 'no')):
			case (is_string($val) && (strtolower($val) === 'n')):
			$ret_val = false;
			break;
		}
		return $ret_val;
	}

	public static function printBacktrace($lines=10, $nl2br=false)
	{
		$debug = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $lines);
		foreach($debug as $idx=>$line)
		{
 			$trace = var_export($line, true);
			echo ($nl2br === true) ? nl2br($trace)."<br>" : $trace."\n";
		}
		echo ($nl2br === true) ? '<br><br>' : "\n\n";
	}

	public static function getCallerName()
	{
		$callers = debug_backtrace(null, 3);
		return $callers[2]['function'];
	}
	public static function concatAttributes($models, $attributes, $glue='-', $discardEmpty=false)
	{
		$ret_val = [];
		if(count($models))
		{
			$models = is_array($models) ? $models : [$models];
			foreach($models as $model)
			{
				$ret_val[] = implode($glue, array_map(function ($attribute) use($model, $discardEmpty) {
					if(is_callable($attribute)) {
						return call_user_func($attribute, $model);
					}
					else if(is_object($model) && $model->hasMethod($attribute)) {
						return call_user_func([$model, $attribute], $model);
					} else if ($attribute && $model->hasAttribute($attribute)) {
						return \yii\helpers\ArrayHelper::getValue($model, $attribute, ($discardEmpty ? null : $attribute));
					}
				}, (array)$attributes));
			}
		}
		return implode($glue, array_filter($ret_val));
	}
}
?>
