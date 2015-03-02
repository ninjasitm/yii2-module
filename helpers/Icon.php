<?php

namespace nitm\helpers;

class Icon extends \kartik\icons\Icon
{
	/**
	 * Get certain types of icons
	 * @param string $action
	 * @param string $attribute
	 * @param Object $model
	 * @param mixed $options
	 */
	public static function forAction($action, $attribute=null, $model=null, $options=[])
	{
		$icon = $action;
		switch(!is_null($model) || !is_null($attribute))
		{
			case true;
			switch(1)
			{
				case is_object($model) && $model->hasAttribute($attribute):
				$value = $model->getAttribute($attribute);
				break;
				
				default;
				$value = $attribute;
				break;
			}
			switch($action)
			{
				case 'close':
				$icon = ((bool)$value == true) ? 'lock' : 'unlock-alt';
				break;
				
				case 'resolve':
				case 'complete':
				$icon = ((bool)$value == true) ? 'check-circle' : 'circle';
				break;
				
				case 'approve':
				$icon = ((bool)$value == true) ? 'thumbs-down' : 'thumbs-up';
				break;
				
				case 'duplicate':
				$icon = ((bool)$value == true) ?  'flag' : 'flag-o';
				break;
				
				case 'disable':
				$icon = ((bool)$value == true) ?  'check-circle' : 'ban';
				break;
				
				case 'delete':
				case 'remove':
				$icon = ((bool)$value == true) ?  'refresh' : 'trash-o';
				break;
			}
			break;
			
			case false:
			switch($action)
			{
				case 'update':
				$icon = 'pencil';
				break;
				
				case 'delete':
				$icon = 'trash-o';
				break;
				
				case 'comment':
				$icon = 'comment';
				break;
				
				case 'view':
				$icon = 'eye';
				break;
			}
			break;
		}
		if(isset($options['size']))
		{
			$options['class'] = isset($options['class']) ? $options['class'] : '';
			$options['class'] .= \Yii::$app->params['icon-framework']."-".$options['size'];
			unset($options['size']);
		}
		return Icon::show($icon, $options);
	}
}
?>