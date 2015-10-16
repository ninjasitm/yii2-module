<?php

namespace nitm\traits\relations;

use nitm\helpers\Cache;
use yii\helpers\ArrayHelper;

/**
 * Traits defined for expanding active relation scopes until yii2 resolves traits issue
 */

trait Profile {
	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getUser()
	{
		return $this->hasOne(\Yii::$app->getModule('user')->modelMap['User'], ['id' => 'user_id']);
	}
}
?>
