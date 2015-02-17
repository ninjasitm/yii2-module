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
	
	public function actionPreview()
	{
		$ret_val = [];
		$post = \Yii::$app->request->post();
        $this->model->setScenario('create');
		$this->model->load($post);
		switch($this->model->source)
		{
			case 'file':
			$file = UploadedFile::getInstance($this->model, 'raw_data[file]');
			$data = $file->tempName;
			$ret_val['files'] = [
				array_filter(array_intersect_key(\yii\helpers\ArrayHelper::toArray($file), array_flip(['name', 'size', 'error'])))
			];
			break;
			
			default:
			$data = $this->model->raw_data['text'];
			break;
		}
		switch($this->model->type)
		{
			case 'csv':
			case 'json':
			case 'xml':
			$this->getImporter()->parse($data)->close();
			$this->model->raw_data = $this->getImporter()->parsedData;
			$ret_val['data'] = $this->model->raw_data;
			break;
		}
		return $ret_val;
	}
	

}
