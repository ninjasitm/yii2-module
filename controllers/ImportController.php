<?php

namespace nitm\controllers;

use nitm\models\imported\Source;
use nitm\models\imported\search\Source as SourceSearch;
use nitm\models\imported\Element;
use nitm\models\imported\search\Element as ElementSearch;
use nitm\helpers\Response;

class ImportController extends \nitm\controllers\DefaultController
{
	
	public function behaviors()
	{
		$behaviors = [
			'access' => [
				'class' => \yii\filters\AccessControl::className(),
				'rules' => [
					[
						'actions' => [
							'index', 'delete', 'view'
						],
						'allow' => true,
						'roles' => ['@'],
					],
				],
			],
			'verbs' => [
				'class' => \yii\filters\VerbFilter::className(),
				'actions' => [
					'index' => ['get', 'post'],
					'view' => ['post'],
					'delete' => ['post'],
				],
			],
		];
		return array_merge(parent::behaviors(), $behaviors);
	}
	public function init()
	{
		parent::init();
		$this->model = new Source(['scenario' => 'default']);
	}

    public function actionCreate()
    {
        return $this->render('create');
    }

    public function actionIndex()
    {
		return parent::actionIndex(SourceSearch::className(), [
			'with' => [
				'author',
			],
		]);
    }
	
	public function actionView()
	{
		print_r($_FILES);
		exit;
		$this->model->load(\Yii::$app->request->post);
		$this->model->previewImport = true;
		$options = [
			'args' => [
				'content' => $this->render("preview", ["model" => $this->model])
			]
		];
	}

}
