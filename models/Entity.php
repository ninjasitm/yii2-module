<?php

namespace nitm\models;

use Yii;
/**
 */
class Entity extends Data
{	
	use \nitm\traits\Nitm, \nitm\traits\Relations;
	
	public $hasNewActivity;	
	
	public function init()
	{
		parent::init();
	}
}
