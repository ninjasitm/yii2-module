<?php

namespace nitm\helpers;

class Html extends \yii\helpers\Html
{
	/**
	 * Get certain types of icons
	 * @param string $action
	 * @param string $attribute
	 * @param Object $model
	 * @param mixed $options
	 */
	public static function linkify($text)
	{
		return preg_replace("
			#((http|https|ftp)://(\S*?\.\S*?))(\s|\;|\)|\]|\[|\{|\}|,|\"|'|:|\<|$|\.\s)#ie",
			"'<a href=\"$1\" target=\"_blank\">$3</a>$4'",
			$text
		);
	}

	public static function parseLinks($str)
	{
		return preg_replace("[[:alpha:]]+://[^<>[:space:]]+[[:alnum:]/]","<a href=\"\\0\">\\0</a>", $str);
	}
}
?>
