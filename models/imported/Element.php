<?php

namespace nitm\models\imported;

use Yii;

/**
 * This is the model class for table "{{%imported_data_element}}".
 *
 * @property integer $id
 * @property integer $imported_data_id
 * @property string $raw_data
 * @property string $created_at
 * @property integer $author_id
 * @property string $signature
 * @property boolean $is_imported
 *
 * @property ImportedData $importedData
 */
class Element extends \nitm\models\Entity
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'imported_data_element';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['imported_data_id', 'raw_data', 'author_id', 'signature'], 'required'],
            [['imported_data_id', 'author_id'], 'integer'],
            [['raw_data', 'signature'], 'string'],
            [['created_at'], 'safe'],
            [['is_imported'], 'boolean']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'imported_data_id' => Yii::t('app', 'Imported Data ID'),
            'raw_data' => Yii::t('app', 'Raw Data'),
            'created_at' => Yii::t('app', 'Created At'),
            'author_id' => Yii::t('app', 'Author ID'),
            'signature' => Yii::t('app', 'Signature'),
            'is_imported' => Yii::t('app', 'Is Imported'),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getImportedData()
    {
        return $this->hasOne(ImportedData::className(), ['id' => 'imported_data_id']);
    }
}
