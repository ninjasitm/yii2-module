<?php

namespace nitm\models;

use Yii;

/**
 * This is the model class for table "{{%model_parent_map}}".
 *
 * @property integer $id
 * @property string $remote_type
 * @property integer $remote_id
 * @property string $remote_class
 * @property string $remote_table
 * @property string $parent_type
 * @property integer $parent_id
 * @property string $parent_class
 * @property string $parent_table
 */
class ParentMap extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'model_parent_map';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['remote_type', 'remote_id', 'remote_class', 'remote_table', 'parent_type', 'parent_id', 'parent_class', 'parent_table'], 'required'],
            [['remote_type', 'remote_class', 'remote_table', 'parent_type', 'parent_class', 'parent_table'], 'string'],
            [['remote_id', 'parent_id'], 'integer'],
			[['remote_type', 'remote_id', 'remote_class', 'remote_table', 'parent_type', 'parent_id', 'parent_class', 'parent_table'], 'unique']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'remote_type' => Yii::t('app', 'Remote Type'),
            'remote_id' => Yii::t('app', 'Remote ID'),
            'remote_class' => Yii::t('app', 'Remote Class'),
            'remote_table' => Yii::t('app', 'Remote Table'),
            'parent_type' => Yii::t('app', 'Parent Type'),
            'parent_id' => Yii::t('app', 'Parent ID'),
            'parent_class' => Yii::t('app', 'Parent Class'),
            'parent_table' => Yii::t('app', 'Parent Table'),
        ];
    }
}
