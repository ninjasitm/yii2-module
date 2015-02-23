<?php

namespace nitm\models\imported;

use Yii;
use yii\helpers\ArrayHelper;

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
class Source extends BaseImported
{	
	public $previewImport;
	
	protected static $_is = 'import';
	
	public function init()
	{
		parent::init();
		if($this->isNewRecord)
			$this->source = 'file';
	}
	
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
        return array_merge(parent::rules(), [
            [['created_at'], 'safe'],
            [['name', 'type', 'data_type'], 'required', 'on' => ['create', 'update']],
            [['name', 'type', 'data_type'], 'string'],
			[['name'], 'unique', 'targetAttribute' => ['name', 'type', 'data_type']],
        ]);
    }
	
	public static function has()
	{
		return array_merge(parent::has(), [
			'editor'
		]);
	}
	
	public function scenarios()
	{
		return [
			'create' => ['name', 'raw_data', 'type', 'data_type', 'source'],
			'update' => ['name', 'type', 'data_type', 'raw_data'],
			'delete' => ['id'], 
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
    public function getElements()
    {
        return $this->hasMany(Element::className(), ['imported_data_id' => 'id']);
    }
	
	public function getRawData()
	{
		if(!isset($this->raw_data))
			$this->raw_data = $this->find()
				->where(['id' => $this->getId()])
				->select('raw_data')
				->one()->raw_data;
		return $this->raw_data;
	}
	
	public function getRawDataArray($offset=0, $limit=200)
	{
		$rows = array_pop(array_map(function ($row) {
			$decoded = json_decode($row['raw_data'], true);
				return $decoded;
			}, $this->find()
				->where(['id' => $this->getId()])
				->select([
					new \yii\db\Expression("raw_data"),
				])
				->limit($limit)
				->offset($offset)
				->asArray()
				->all()
		));
		$isImported = Element::find()
			->select(['id', 'signature', 'is_imported'])
			->where([
				'signature' => array_map(function ($row) {
					return Element::getSignature(Element::encode($row));
				}, $rows),
				'imported_data_id' => $this->getId()
			])
			->asArray()
			->all();
		$ret_val = array_map(function ($row) use(&$isImported){
			if(is_array($isImported))
				foreach($isImported as $idx=>$same)
				{
					if(($signature = Element::getSIgnature(Element::encode($row))) == $same['signature'])
					{
						$row = array_merge($row, $same);
						unset($isImported[$idx]);
					}
				}
			$row['is_imported'] = ArrayHelper::getValue($row, 'is_imported', false);
			return $row;
		}, $rows);
		return $ret_val;
	}
	
	public function selectFields()
	{
		return [
			'name', 'type', 'data_type', 
			'count', 'total', 'source', 
			'signature', 'completed', 'completed_by', 
			'completed_at', 'created_at', 'id'
		];
	}
	
	public function encode($data=null)
	{
		$data = is_null($data) ? $this->raw_data : $data;
		$this->total = count($data);
		return parent::encode($data);
	}
	
	public function saveElement($attributes, $asArray=false)
	{
		$element = new \nitm\models\imported\Element($attributes);
		$element->setScenario('create');
		$existing = Element::find()->where([
			'imported_data_id' => $this->id,
			'signature' => $element->getSignature($element->encode($element->raw_data))
		])->one();
		if($existing instanceof Element)
			$element = $existing;
		$element->imported_data_id = $this->id;
		$element->is_imported = true;
		$element->save();
		return $asArray ? ArrayHelper::toArray($element) : $element;
	}
	
	public function saveElements($attributes, $asArray=false, $fields=null)
	{
		$ret_val = [];
		$fields = is_null($fields) ? [
			'raw_data',
			'imported_data_id',
			'signature',
			'author_id'
		] : $fields;
		
		sort($fields);
		$attributes = array_map(function ($element) use($fields){
			ksort($element);
			return array_intersect_key(array_merge($element, [
				'raw_data' => Element::encode($element['raw_data']),
				'imported_data_id' => $this->id,
				'signature' => Element::getSignature(Element::encode($element['raw_data'])),
				'author_id' => \Yii::$app->user->getId()
			]), array_flip($fields));
		}, $attributes);
		
		$command = \Yii::$app->getDb()->createCommand();
		$sql = $command->batchInsert(Element::tableName(), $fields, $attributes)->getSql();
		$sql .= ' ON DUPLCATE KEY imported_data_id='.$this->id;
		
		echo $sql;
		exit;
		$command->setSql($sql);
		//If the batch insert was successful then get the inserted IDs based on the signature and return the job
		if($command->execute())
		{
			$ret_val = array_map(function ($result) {
				$result = [
					'success' => true,
					'id' => $result['id'],
					'link' => \Yii::$app->urlManager->createUrl(['/import/element/'.$result['id']])
				];
			}, Element::find()->select(['id'])->where(['signature' => array_map(function ($element) {
				return $element['signature']; 
			}, $attributes)])->asArray()->all());
		}
		return $ret_val;
	}
}
