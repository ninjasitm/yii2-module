<?php

namespace nitm\controllers;

use nitm\models\imported\Source;
use nitm\models\imported\Element;
use nitm\helpers\Response;

class ImportController extends \nitm\controllers\DefaultController
{
	public function init()
	{
		parent::init();
		$this->model = new Source(['scenario' => 'default']);
	}
	
    public function actionHistory()
    {
        return $this->render('history');
    }

    public function actionCreate()
    {
        return $this->render('create');
    }

    public function actionIndex()
    {
		if(\Yii::$app->request->post('preview-import'))
		{
			$this->model->load(\Yii::$app->request->post);
			$this->model->previewImport = true;
			$options = [
				'args' => [
					'content' => $this->render("index", ["model" => $this->model])
				]
			];
		}
		else
			$options = [
				'args' => [
					"content" => $this->render("forms/_form", ["model" => $this->model])
				],
			];
		
		Response::viewOptions(null, array_merge($options, [
			'modalOptions' => [
				'contentOnly' => true
			]
		]), true);
		return $this->renderResponse(null, null, \Yii::$app->request->isAjax);
    }

}
