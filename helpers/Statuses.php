<?php

namespace nitm\helpers;

class Statuses 
{
	/**
	 * Indicator types supports
	 */
	protected static $indicators = [
		'critical' => 'bg-danger',
		'danger' => 'bg-danger',
		'default' => '',
		'disabled' => 'bg-disabled',
		'duplicate' => 'bg-duplicate',
		'error' => 'bg-danger',
		'info' => 'bg-info',
		'important' => 'bg-info',
		'normal' => '',
		'resolved' => 'bg-resolved',
		'success' => 'bg-success',
		'warning' => 'bg-warning'
	];
	
	/**
	 * Indicator types supports
	 */
	protected static $listIndicators = [
		'critical' => 'list-group-item list-group-item-danger',
		'danger' => 'list-group-item list-group-item-danger',
		'default' => 'list-group-item',
		'disabled' => 'list-group-item list-group-item-disabled',
		'duplicate' => 'list-group-item list-group-item-duplicate',
		'error' => 'list-group-item list-group-item-danger',
		'info' => 'list-group-item list-group-item-info',
		'important' => 'list-group-item list-group-item-info',
		'normal' => 'list-group-item',
		'resolved' => 'list-group-item  list-group-item-resolved',
		'success' => 'list-group-item  list-group-item-success',
		'warning' => 'list-group-item list-group-item-warning'
	];
	
	/**
	 * Get the class indicator value for a generic item
	 * @param string $indicator
	 * @return string $css class
	 */
	public static function getIndicator($indicator=null)
	{
		$indicator = is_null($indicator) ? 'default' : $indicator;
		return self::$indicators[$indicator];
	}
	
	/**
	 * Get the class indicator value for a list item
	 * @param string $indicator
	 * @return string $css class
	 */
	public static function getListIndicator($indicator=null)
	{
		$indicator = is_null($indicator) ? 'default' : $indicator;
		return self::$listIndicators[$indicator];
	}
}
?>