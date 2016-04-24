<?php

namespace nitm\helpers;

use yii\db\ActiveRecord;
use yii\base\Model;


class Helper
{
	public static $debugFormat = 'html';

	const DEBUG_HTML = '@html';

	/**
	 * Print a pre formatted value
	 */
	public static function pr()
	{
		$args = func_get_args();
		$html = false;
		if($args[0] == static::DEBUG_HTML) {
			ArrayHelper::remove($args, 0);
			$html = true;
		}
		foreach($args as $data)
		{
			if(!empty($data)) {
				if($html || static::$debugFormat == 'html')
					echo static::prHtml(uniqid(), print_r($data, true));
				else
					echo "<pre>".print_r($data, true)."</pre>";
			}
		}
	}

	public static function prHtml($name, $data)
	{
	    $captured = preg_split("/\r?\n/", $data);
	    print "<script>function toggleDiv(num){
	      var span = document.getElementById('d'+num);
	      var a = document.getElementById('a'+num);
	      var cur = span.style.display;
	      if(cur == 'none') {
	        a.innerHTML = '-';
	        span.style.display = 'inline';
	      }else{
	        a.innerHTML = '+';
	        span.style.display = 'none';
	      }
	    }</script>";
	    print "<b>$name</b>\n";
	    print "<pre>\n";
	    foreach($captured as $line)
	    {
	        print static::prColor($line)."\n";
	    }
	    print "</pre>\n";
	}

	function nextDiv($matches)
	{
		static $num = 0;
		++$num;
		return "$matches[1]<a id=a$num href=\"javascript: toggleDiv($num)\">+</a><span id=d$num style=\"display:none\">(";
	}

	/**
	* colorize a string for pretty display
	* @source http://php.net/manual/en/function.print-r.php
	* @access private
	* @param $string string info to colorize
	* @return string HTML colorized
	* @global
	*/
	public static function prColor($string)
	{
		$string = preg_replace("/\[(\w*)\]/i", '[<font color="red">$1</font>]', $string);
		$string = preg_replace_callback("/(\s+)\($/", ['\nitm\helpers\Helper', 'nextDiv'], $string);
		$string = preg_replace("/(\s+)\)$/", '$1)</span>', $string);
		/* turn array indexes to red */
		/* turn the word Array blue */
		$string = str_replace('Array','<font color="blue">Array</font>',$string);
		/* turn arrows graygreen */
		$string = str_replace('=>','<font color="#556F55">=></font>',$string);
		return $string;
	}

	public static function printBacktrace($lines=10, $nl2br=false, $htmlArray=false)
	{
		$debug = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $lines);
		if($htmlArray)
			self::pr($debug);
		else
			self::prHtml(uniqid(), print_r($debug, true));
		/*foreach($debug as $idx=>$line) {
 			$trace = var_export($line, true);
			echo ($nl2br === true) ? nl2br($trace)."<br>" : $trace."\n";
		}
		echo ($nl2br === true) ? '<br><br>' : "\n\n";*/
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
						return call_user_func($model, $attribute);
					}
					else if(is_object($model) && $model->hasMethod($attribute)) {
						$arguments = explode(':', $attribute);
						$attribute = $arguments[0];
						switch(strtolower($attribute))
						{
							case 'getid':
							case 'iswhat':
							return call_user_func_array([$model, $attribute], (array)$arguments);
							break;

							default:
							return call_user_func_array([$model, $attribute], array_merge([$model], (array)$arguments));
							break;
						}
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
