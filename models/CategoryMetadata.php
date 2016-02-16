<?php

namespace nitm\models;

use Yii;

/**
 * This is the model class for table "categories_metadata".
 *
 * @property integer $category_id
 * @property string $key
 * @property string $value
 * @property string $created
 * @property string $updated
 *
 * @property Category $category
 */
class CategoryMetadata extends EntityMetadata
{
    public static $tableName = 'categories_metadata';

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['category_id', 'key', 'value'], 'required'],
            [['category_id'], 'integer'],
            [['created', 'updated'], 'safe'],
            [['category_id', 'key'], 'unique', 'targetAttribute' => ['category_id', 'key'], 'message' => 'The combination of Category ID and Key has already been taken.']
        ];
    }

	public function scenarios()
	{
		return [
            'default' => [],
			'create' => ['key', 'value', 'category_id'],
			'update' => ['key', 'value'],
		];
	}

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'category_id' => Yii::t('app', 'Category ID'),
            'key' => Yii::t('app', 'Key'),
            'value' => Yii::t('app', 'Value'),
            'created' => Yii::t('app', 'Created'),
            'updated' => Yii::t('app', 'Updated'),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCategory()
    {
        return $this->hasOne(Category::className(), ['id' => 'category_id']);
    }


    /**
	 * The link that signifies the metadata connection
     * @return array
     */
    public function metadataLink()
    {
        return ['category_id' => 'id'];
    }

    public static function primaryKey()
    {
        return ['category_id', 'key'];
    }
}
