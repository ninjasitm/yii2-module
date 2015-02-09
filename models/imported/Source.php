<?php

namespace nitm\models\imported;

use Yii;

/**
 * This is the model class for table "{{%imported_data}}".
 *
 * @property integer $id
 * @property string $created_at
 * @property integer $author_id
 * @property string $name
 * @property string $raw_data
 * @property string $type
 * @property string $data_type
 *
 * @property User $author
 * @property ImportedDataElement[] $importedDataElements
 */
class Source extends \nitm\models\Entity
{
	public $previewImport = false;
	public $location;
	
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'imported_data';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['created_at'], 'safe'],
            [['author_id', 'name', 'raw_data', 'type', 'data_type'], 'required'],
            [['author_id'], 'integer'],
            [['name', 'raw_data', 'type', 'data_type'], 'string']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'created_at' => Yii::t('app', 'Created At'),
            'author_id' => Yii::t('app', 'Author ID'),
            'name' => Yii::t('app', 'Name'),
            'raw_data' => Yii::t('app', 'Raw Data'),
            'type' => Yii::t('app', 'Type'),
            'data_type' => Yii::t('app', 'Data Type'),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getImportedDataElements()
    {
        return $this->hasMany(ImportedDataElement::className(), ['imported_data_id' => 'id']);
    }
}
