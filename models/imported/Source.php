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
	public $limit = 100;
	
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
			['raw_data', 'validateJson', 'on' => ['create']]
        ]);
    }
	
	public function validateJson($attribute, $params)
	{
		if($this->type == 'json')
			if(json_decode($this->$attribute, true) == null)
				$this->addError($attribute, "You chose a json source but the data isn't valid json");
				
		if($this->source == 'api') {
			if(json_decode($this->$attribute, true) == null)
				$this->addError($attribute, "You chose an API but the config provided is not valid json");
		}
	}
	
	public function encode($data=null)
	{
		if(!is_null($data))
			if(!($decoded = json_decode(ArrayHelper::getValue($data, $this->source, '{{}'))) == null)
				return json_encode($decoded);
			else
				return is_string($data) ? $data : $data[$this->source];
		else
			return parent::encode($data);
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
			'import' => ['count'],
			'create' => ['name', 'raw_data', 'type', 'data_type', 'source', 'total'],
			'update' => ['name', 'type', 'data_type', 'raw_data' , 'total'],
			'delete' => ['id'], 
			'preview' => ['raw_data', 'total'], 
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
        return $this->hasMany(Element::className(), ['imported_data_id' => 'id'])
			->groupBy([
				'is_imported',
				'id'
			])->orderBy([
				'id' => SORT_ASC,
			]);
    }
	
	public function elements()
	{
		return $this->elements;
	}

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getElementsArray()
    {
        $query = $this->hasMany(Element::className(), ['imported_data_id' => 'id'])
			->groupBy([
				'is_imported',
				'id'
			])->orderBy([
				'id' => SORT_ASC,
			])
			->asArray();
		if(($where = $this->getFlag('source-where')) != null)
			$query->where($where);
		return $query;
    }
	
	public function elementsArray()
	{
		return $this->elementsArray;
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
	
	public function updateElements($attributes)
	{		foreach($attributes as $element)
		{
			$model = @Element::find()->select('id')->where(['id' => $element['id']])->one();
			if($model instanceof Element)
			{
				$model->setScenario('update');
				$model->load($attributes);
				$model->save();
			}
		}
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
			$element = array_intersect_key(array_merge($element, [
				'raw_data' => Element::encode($element['raw_data']),
				'imported_data_id' => $this->id,
				'signature' => Element::getSignature(Element::encode($element['raw_data'])),
				'author_id' => \Yii::$app->user->getId()
			]), array_flip($fields));
			ksort($element);
			return $element;
		}, $attributes);
		
		//Postgres doesn't support on duplicate key so getting existing elements with this signature
		$existing = Element::find()->select(['signature'])->where(["not IN", 'signature', array_map(function ($element) {
			return $element['signature']; 
		}, $attributes)])->asArray()->indexBy('signature')->all();
		
		$attributes = array_filter($attributes, function ($element) use ($existing){
			return !isset($existing[$element['signature']]);
		});
		
		$command = \Yii::$app->getDb()->createCommand();
		
		$sql = $command->batchInsert(Element::tableName(), $fields, array_map('array_values', $attributes))->getSql();
		//Postgres doesn't support on duplicate key
		//$sql .= ' ON DUPLICATE KEY UPDATE imported_data_id='.$this->id;
		
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
	
	public function percentComplete()
	{
		if($this->count <= 0)
			return 0;
		else
			return round(($this->count/$this->total)*100, 2);
	}
}
