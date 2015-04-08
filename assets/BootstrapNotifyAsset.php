<?php
/**
 * @link http://www.nitm.com/
 * @copyright Copyright (c) 2014 NITM Inc
 */

namespace nitm\assets;

use yii\web\AssetBundle;

/**
 * @author Malcolm Paul admin@nitm.com
 */
class BootstrapNotifyAsset extends AssetBundle
{
	public $sourcePath = '@vendor/mouse0270/bootstrap-notify';
	public $css = [
		'http://cdnjs.cloudflare.com/ajax/libs/animate.css/3.2.0/animate.min.css'
	];
	public $js = [
		'bootstrap-notify.min.js'
	];
	//public $jsOptions = ['position' => \yii\web\View::POS_END];
	public $depends = [
		'nitm\assets\AppAsset',
	];
}
