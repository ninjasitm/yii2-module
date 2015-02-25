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
class ImportAsset extends AssetBundle
{
	public $sourcePath = __DIR__;
	public $css = [
	];
	public $js = [
		'js/import.js',
	];
	//public $jsOptions = ['position' => \yii\web\View::POS_READY];
	public $depends = [
		'yii\web\YiiAsset',
		'yii\bootstrap\BootstrapAsset',
		'yii\bootstrap\BootstrapPluginAsset',
		'nitm\assets\AppAsset',
	];
}
