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
class Element extends BaseImported
{
	protected $is = 'import-item';
	
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
        return array_merge(parent::rules(), [
            [['imported_data_id'], 'required'],
            [['imported_data_id'], 'integer'],
            [['created_at'], 'safe'],
            [['is_imported'], 'boolean'],
        ]);
    }
	
	public function scenarios()
	{
		return [
			'import' => ['is_imported'],
			'create' => ['imported_data_id', 'raw_data', 'signature'],
			'default' => []
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
    public function getSource()
    {
        return $this->hasOne(Source::className(), ['id' => 'imported_data_id'])->select(Source::selectFields());
    }
	
	public function findFromRaw($importId, $id)
	{
		return array_map(function ($row) use ($importId) {
			return new Element([
				'raw_data' => $row['raw_data'], 
				'imported_data_id' => $importId,
				'is_imported' => $row['is_imported']
			]);
		}, Source::find()
			->where(['id' => $importId])
			->select([
				new \yii\db\Expression("json_extract_path(raw_data, '$id') AS raw_data"),
				new \yii\db\Expression("(SELECT 1 FROM ".Element::tableName()." WHERE signature=MD5(json_extract_path_text(raw_data, '$id')) AND imported_data_id=".$importId." LIMIT 1) AS is_imported"),
			])
			->asArray()
			->all());
	}
}
