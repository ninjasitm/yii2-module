<?php

namespace nitm\models;

use Yii;

/**
 * This is the model class for table "images_metadata".
 *
 * @property integer $image_id
 * @property string $key
 * @property string $value
 * @property string $created
 * @property string $updated
 *
 * @property Images $image
 */
class EntityMetadata extends \yii\db\ActiveRecord
{
	public static $tableName = 'content_metadata';

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
		switch(!static::$tableName)
		{
			case true:
			throw new \yii\base\Exception("You need to specify the tableName static for ".static::className());
			break;
		}
        return static::$tableName;
    }

	public function behaviors()
	{
		$behaviors = [
			'timestamp' => [
				'class' => \yii\behaviors\TimestampBehavior::className(),
					'attributes' => [
						\yii\db\ActiveRecord::EVENT_BEFORE_INSERT => 'created_at',
						\yii\db\ActiveRecord::EVENT_BEFORE_UPDATE => 'updated_at',
					],
					'value' => new \yii\db\Expression('NOW()')
				]
		];
		return array_merge(parent::behaviors(), $behaviors);
	}

	public function scenarios()
	{
		return [
			'default' => [],
			'create' => ['key', 'value', 'content_id'],
			'update' => ['key', 'value'],
		];
	}

    public static function primaryKey(){
        return ['content_id', 'key'];
    }

    /**
	 * The link that signifies the metadata connection
     * @return array
     */
    public function metadataLink()
    {
        return ['content_id' => 'id'];
    }
}
