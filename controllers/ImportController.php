<?php

namespace nitm\controllers;

use nitm\models\imported\Source;
use nitm\models\imported\search\Source as SourceSearch;
use nitm\models\imported\Element;
use nitm\models\imported\search\Element as ElementSearch;
use nitm\helpers\Response;
use nitm\helpers\Helper;
use yii\web\UploadedFile;
use yii\helpers\ArrayHelper;

class ImportController extends \nitm\controllers\DefaultController
{
	protected $sourceSelectFields = [
		'id', 'name', 'author_id', 'created_at', 
		'type', 'data_type', 'count', 
		'total', 'source', 'signature', 
		'completed', 'completed_by', 'completed_at'
	];
	
	protected $_importer;
	
	public function behaviors()
	{
		$behaviors = [
			'access' => [
				'rules' => [
					[
						'actions' => [
							'preview', 'element', 'elements',
							'batch', 'import-all', 'import-batch'
						],
						'allow' => true,
						'roles' => ['@'],
					],
				],
			],
			'verbs' => [
				'actions' => [
					'preview' => ['post'],
					'element' => ['post'],
					'elements' => ['post'],
					'batch' => ['post'],
					'import-all' => ['post', 'get'],
					'import-batch' => ['post', 'get']
				],
			],
		];
		return array_merge_recursive(parent::behaviors(), $behaviors);
	}
	
	public function beforeAction($action)
	{
		switch($action->id)
		{
			case 'preview':
			case 'element':
			$this->enableCsrfValidation = false;
			break;
		}
		return parent::beforeAction($action);
	}
	
	
	public function init()
	{
		parent::init();
		$this->model = new Source();
	}
	
	protected function getImporter($type=null)
	{
		$type = is_null($type) ? $this->model->type : $type;
		return \Yii::$app->getModule('nitm')->importer->getParser($type);
	}
	
	public function getProcessor() 
	{
		throw new \yii\base\ErrorException("You need to define procesors for this data");
	}
	
	public static function assets()
	{
		return [
			'import'
		];
	}

    public function actionIndex()
    {
		return parent::actionIndex(SourceSearch::className(), [
			'with' => [
				'author',
			],
		]);
    }
	
	public function actionPreview($id)
	{
		$ret_val = [
			'success' => true
		];
		$post = \Yii::$app->request->post();
		$this->model = $this->findModel(Source::className(), $id, [], ['select' => $this->sourceSelectFields]);
		if(!$this->model)
			return [
				'success' => false,
				'message' => "The import with the id: $id doesn't exist"
			];
			
        $this->model->setScenario('preview');
		$this->model->load($post);
		switch($this->model->source)
		{
			case 'file':
			$file = UploadedFile::getInstance($this->model, 'raw_data[file]');
			$ret_val['data'] = $file->tempName;
			$ret_val['files'] = [
				array_filter(array_intersect_key(\yii\helpers\ArrayHelper::toArray($file), array_flip(['name', 'size', 'error'])))
			];
			break;
			
			case 'url':
			$ret_val['data'] = $this->model->raw_data['url'];
			break;
			
			case 'api':
			$ret_val['data'] = $this->model->raw_data['api'];
			break;
			
			default:
			$ret_val['data'] = $this->model->raw_data['text'];
			break;
		}
		
		if(!\Yii::$app->getModule('nitm')->importer->isSupported($this->model->type))
			throw new\yii\base\ErrorException("Unsupported type: ".$this->model->type);
			
		return $ret_val;
	}
	
	public function actionImportBatch($id)
	{
		$this->model = $this->findModel(Source::className(), $id, [], [
			'select' => $this->sourceSelectFields
		]);
		
		$this->getProcessor()->limit = \yii::$app->getModule('nitm')->importer->limit;
		$this->getProcessor()->batchSize = \yii::$app->getModule('nitm')->importer->batchSize;
			
		return $this->actionImportAll($id, true);
	}
	
	public function actionImportAll($id, $modelFound=false)
	{
		$ret_val = [
			'count' => 0,
			'processed' => 0,
			'exists' => 0,
			'percent' => 0,
			'message' => "Didn't improt anything :-("
		];
		if(!$modelFound) {
			$this->model = $this->findModel(Source::className(), $id, [], [
				'select' => $this->sourceSelectFields
			]);
			$this->getProcessor()->limit = 1000;
			$this->getProcessor()->batchSize = \yii::$app->getModule('nitm')->importer->batchSize;
		}
			
		$this->getProcessor()->setJob($this->model);
		$this->model = null;
		if($this->getProcessor()->getJOb() instanceof Source)
		{
			$result = $this->getProcessor()->batchImport('data');
			$imported = [];
			foreach($result as $idx=>$jobElement)
			{
				if(ArrayHelper::getValue($jobElement, 'success', false))
				{
					$imported[] = ArrayHelper::getValue($jobElement, 'id', $idx);
					$ret_val['processed']++;
				}
				else if(ArrayHelper::getValue($jobElement, 'exists', false)) {
					$imported[] = ArrayHelper::getValue($jobElement, 'id', $idx);
					$ret_val['exists']++;
					$ret_val['processed']++;
				}

				$ret_val['count']++;
			}
			if(count($imported))
				Element::updateAll(['is_imported' => true], ['id' => $imported]);
			
			$ret_val['message'] = "Imported <b>".$ret_val['processed']."</b> out of <b>".$ret_val['count']."</b> elements!";
			$ret_val['percent'] = $this->getProcessor()->getJob()->percentComplete();
			if($ret_val['exists'])
				$ret_val['message'] .= " <b>".$ret_val['exists']."</b> out of <b>".$ret_val['count']."</b> the entires already exist!";
			if($ret_val['exists'])
				$ret_val['class'] = 'info';
			else if($ret_val['processed'] == 0)
				$ret_val['class'] = 'error';
			else if($ret_val['processed'] < $ret_val['count'])
				$ret_val['class'] = 'warning';
			else
				$ret_val['class'] = 'success';
			
		}
		$this->setResponseFormat('json');
		return $ret_val;
	}
	
	protected function actionElements($id)
	{
		$elementIds = \Yii::$app->request($post);
		$this->model = $this->findModel(Source::className(), $id, [], [
			'select' => $this->sourceSelectFields
		]);
		$this->model->setFlag('source-where', ['ids' => $elementIds]);
		
		$this->getProcessor()->limit = \yii::$app->getModule('nitm')->importer->limit;
		$this->getProcessor()->batchSize = \yii::$app->getModule('nitm')->importer->batchSize;
		$this->getProcessor()->offset = $this->model->getElementsArray()
			->where([
				'is_imported' => true
			])->count();
			
		return $this->actionImportAll($id);
	}
	
	public function actionCreate()
	{
		/*$this->setResponseFormat('json');
		$ret_val = [
			'action' => 'create',
			'id' => 13,
			'success' => true,
			'url' => \Yii::$app->urlManager->createUrl(['/import/preview/13'])
		];
		return $ret_val;*/
		$ret_val = parent::actionCreate();
		if(isset($ret_val['success']) && $ret_val['success'])
		{
			$ret_val['url'] = \Yii::$app->urlManager->createUrl(['/import/preview/'.$this->model->getId()]);
			$this->getProcessor();
		}
		return $ret_val;
	}
	
	public function actionElement($type, $id=null)
	{
		$ret_val = ['id' => $id, 'success' => false];
		switch($type)
		{
			case 'element':
			$model = $this->findModel(Element::className(), $id, ['source']);
			break;
			
			default:
			$model = array_shift(Element::findFromRaw($type, $id));
			break;
		}
		if($model instanceof Element)
		{
			$this->model = $model->source;
			$this->getProcessor()->setJob($model->source);
			$this->getProcessor()->prepare([$model]);
			$ret_val = array_shift($this->getProcessor()->import('data'));
			if($ret_val['success'] || @$ret_val['exists'])
			{
				$model->setScenario('import');
				$model->is_imported = true;
				$model->save();
				Source::updateAllCounters(['count' => 1], ['id' => $this->model->getId()]);
				$ret_val['class'] = 'success';
				$ret_val['icon'] = \nitm\helpers\Icon::show('thumbs-up', ['class' => 'text-success']);
			}
		}
		$this->setResponseFormat('json');
		return $this->renderResponse($ret_val, Response::viewOptions(), \Yii::$app->request->isAjax);
	}
	
	public function actionForm($type, $id=null)
	{
		switch($type)
		{
			case 'update':
			$this->model = $this->findModel($this->model->className(), $id, ['author'], ['select' => Source::selectFields()]);
			/*$options = [
				//'provider' => 'elementsArray'
				'provider' => ['elementsArray', function ($objects) {
					return array_map(function ($object) {
						return $this->getProcessor()->transformFormAttributes($object);
					}, $objects);
				}]
			];*/
			$options = [];
			$id = null;
			break;
			
			default:
			$options = [];
			break;
		}
		$options['formOptions'] = [
			'options' => [
				'id' => 'source-import-form'
			]
		];
		$data = parent::actionForm($type, $id, $options, true);
		$data['args']['dataProvider'] = new \yii\data\ActiveDataProvider([
			'query' => $data['args']['model']->getElementsArray(),
			'pagination' => [
				'defaultPageSize' => 50,
				'pageSize' => 50
			]
		]);
		$data['args']['processor'] = $this->getProcessor();
		Response::viewOptions(null, $data);
		return $this->renderResponse([], Response::viewOptions(), \Yii::$app->request->isAjax);
	}
}
