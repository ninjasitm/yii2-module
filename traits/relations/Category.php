<?php

namespace nitm\traits\relations;

use nitm\traits\Relations;
//use nitm\widgets\models\category\CategoriesMetadata;

trait Category
{

    /**
     * @return \yii\db\ActiveQuery
     */
    /*public function getCategoriesMetadata()
    {
        return $this->hasMany(Relations::getRelationClass(CategoriesMetadata::className(), get_called_class()), ['category_id' => 'id']);
    }

	public function categoriesMetadata()
	{
		return \nitm\helpers\Relations::getRelatedRecord('categoriesMetadata', $this, []);
	}*/

	/**
	 * Get the type id of this entity
	 */
	public function getTypeId()
	{
		switch(!isset($this->typeId))
		{
			case true:
			$type = \nitm\models\Category::find()->select('id')->where(['slug' => static::isWhat()])->asArray()->one();
			$this->typeId = isset($type['id']) ? $type['id'] : 0;
			break;
		}
		return $this->typeId;
	}

	public function fields() {
		return [
			'id',
			'name',
			'title' => 'name',
			'slug',
			'htmlIcon' => 'html_icon'
		];
	}

}
