<?php

namespace nitm\controllers;

use nitm\models\imported\Source;
use nitm\models\imported\search\Source as SourceSearch;
use nitm\models\imported\Element;
use nitm\models\imported\search\Element as ElementSearch;
use nitm\helpers\Response;
use nitm\helpers\Helper;
use yii\web\UploadedFile;

class ImportController extends \nitm\controllers\DefaultController
{
	private $_importer;
	
	public function behaviors()
	{
		$behaviors = [
			'access' => [
				'rules' => [
					[
						'actions' => [
							'preview', 'element', 'batch'
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
					'batch' => ['post'],
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
		if(isset($this->_importer))
			return $this->_importer;
		switch($this->model->data_type)
		{
			case 'drinks':
			$this->_importer = new \app\helpers\import\DrinkImporter([
				'name' => $this->model->name,
				'job' => $this->model
			]);
			break;
		}
		return $this->_importer;
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
		$this->model = $this->findModel(Source::className(), $id);
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
			
			default:
			$ret_val['data'] = $this->model->raw_data['text'];
			break;
		}
		
		if(!\Yii::$app->getModule('nitm')->importer->isSupported($this->model->type))
			throw new\yii\base\ErrorException("Unsupported type: ".$this->model->type);
			
		return $ret_val;
	}
	

}
