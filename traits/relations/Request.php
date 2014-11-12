<?php
namespace nitm\traits\relations;

use nitm\models\Category;

/**
 * Traits defined for expanding active relation scopes until yii2 resolves traits issue
 */

trait Request {
	public $requestModel;
	
	protected static $urgency = [
		'normal',
		'important',
		'critical'
	];
	
	public function getUrgency()
	{
		return ucfirst(static::$urgency[$this->status]);
	}

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getRequestFor()
    {
        return $this->hasOne(Category::className(), ['id' => 'request_for_id']);
    }
	
	public function requestFor()
	{
		return $this->requestFor instanceof Category ? $this->requestFor : new Category();
	}
}
?>
