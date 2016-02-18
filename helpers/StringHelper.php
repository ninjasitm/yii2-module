<?php

namespace nitm\helpers;

use yii\db\ActiveRecord;
use yii\base\Model;


class StringHelper
{
	/**
	 * function to remove carriage returns and newlines from strings
	 * @param string | mixed $s
	 * @return string | mixed
	 */
	public static function stripNl($s)
	{
		switch(gettype($s))
		{
			case 'array':
			foreach($s as $k=>$v)
			{
				$s[$k] = self::stripNl($v);
			}
			return $s;
			break;

			default:
			return str_replace(array("\r", "\n", "\r\n"), '', $s);
			break;
		}
	}

	/*
		Function to return an array for the given parameters:
		@param string $string = The string code with all values
		@param string $sep = The separator used in $string
		@param string $sur = What to surround the values with
		@param integer $num = Should numeric values be left alone and not surround with $sur?
		@return Array | boolean
	*/
	public static function getArray($string, $sep=',', $sur="'", $num=true)
	{
		$ret_val = false;
		$sep = ($sep == null) ? ',' : $sep;
		$sur = ($sur == null) ? "'" : $sur;
		switch(is_string($string) || ($string == 0))
		{
			case true:
			$string = explode($sep, $string);
			$string = is_array($string) ? $string : array($string);
			$ret_val = self::splitf($string, $sep, true, $sur, null, $num);
			eval("\$ret_val = array($ret_val);");
			break;

			default:
			$ret_val = $string;
			break;
		}
		return $ret_val;
	}

	/**
		Get safe a string. Essentially replace all non string characters with underscores,
		or unless specified by $s and $r
		@param string $subject = string to be checked
		@param mixed $s = what to search for
		@param mixed $r = what to replace with
		@return string
	*/
	public static function getSafeString($subject, $s=null, $r=null)
	{
		$s = (!empty($s)) ? $s : array("/([^a-zA-Z0-9\\+])/", "/([^a-zA-Z0-9]){1,}$/", "/([\s]){1,}/");
		$r = (!empty($r)) ? $r : array(" ", "", "_");
		return preg_replace($s, $r, $subject);
	}

	/*---------------------
		Protected Functions
	---------------------*/


	/*---------------------
		Private Functions
	---------------------*/
}
?>
