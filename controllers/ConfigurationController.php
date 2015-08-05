<?php

namespace nitm\controllers;

use Yii;
use yii\helpers\ArrayHelper;
use nitm\helpers\Helper;
use nitm\helpers\Session;
use nitm\helpers\Configer;
use nitm\helpers\Response;
use nitm\interfaces\DefaultControllerInterface;

class ConfigurationController extends DefaultController
{	
	public function init()
	{
		parent::init();
		$this->model = \Yii::$app->getModule('nitm')->config;
	}
	
	public function behaviors()
	{
		$behaviors = [
			'access' => [
				'class' => \yii\filters\AccessControl::className(),
				//'only' => ['index', 'update', 'create', 'index', 'get', 'delete', 'convert', 'undelete'],
				'rules' => [
					[
						'actions' => ['index',  'create',  'update',  'delete', 'get','convert', 'undelete'],
						'allow' => true,
						'roles' => ['@'],
					],
				],
			],
			'verbs' => [
				'class' => \yii\filters\VerbFilter::className(),
				'actions' => [
					'index' => ['get'],
					'delete' => ['post'],
					'undelete' => ['post'],
					'create' => ['post'],
					'update' => ['post'],
					'convert' => ['post'],
				],
			],
		];
		
		return array_replace_recursive(parent::behaviors(), $behaviors);
	}
	
	public static function assets()
	{
		return [
			'configuration'
		];
	}
	
	function beforeAction($action)
	{
		$beforeAction = parent::beforeAction($action);
		
		if(isset($_GET['engine']))
			$this->model->setEngine($_GET['engine']);
		if(isset($_GET['container']))
			$this->model->container = $_GET['container'];	
			
		$this->model->load($_REQUEST);
		
		switch(1)
		{
			case $_SERVER['REQUEST_METHOD'] == 'POST':
			case $_SERVER['REQUEST_METHOD'] == 'PUT':
			$params = $_POST;
			break;
			
			case $_SERVER['REQUEST_METHOD'] == 'GET':
			$params = $_GET;
			break;
		}
		
		$dm = $this->model->getDm();
		$container = Session::getVal($dm.'.current.container');
		
		//determine the correct container
		$this->model->container = $container ? $container : $this->model->container;
		
		//if we're not requesting a specific section then only load the sections and no values
		$this->model->prepareConfig($this->model->container, $this->model->getValues);
		return $beforeAction;
	}
	
	public function actionIndex()
	{
		return $this->render('index', ["model" => $this->model]);
	}
	
	/*
	 * Convert configuration from one format to antoher
	 */
	public function actionConvert()
	{
		$this->model->setScenario($this->action->id);
		$this->model->load($_POST);
		switch($this->model->convert['do'])
		{
			case true:
			$this->model->convert($this->model->convert['container'], $this->model->convert['from'], $this->model->convert['to']);
			break;
		}
		return $this->finalAction();
	}
	
	public function actionUndelete()
	{
		$section = explode('.', $_POST[$this->model->formName()]['name']);
		$name = explode('.', $_POST[$this->model->formName()]['name']);
		$_POST[$this->model->formName()]['section'] = array_shift($section);
		$_POST[$this->model->formName()]['name'] = array_pop($name);
		$this->action->id = 'create';
		return $this->actionCreate();
	}
	
	public function actionCreate()
	{
		if(isset($_POST[$this->model->formName()]))
		{
			$this->model->setScenario($this->action->id.ucfirst($_POST[$this->model->formName()]['what']));
			$this->model->load($_POST);
			
			if(\Yii::$app->request->isAjax && (@Helper::boolval($_REQUEST['do']) !== true)) {
				$this->setResponseFormat('json');
				return \yii\widgets\ActiveForm::validate($this->model);
			}
				
			if($this->model->validate())
			{
				switch($this->model->getScenario())
				{
					case 'createContainer':
					$this->model->createContainer($this->model->value, null, $this->model->engine);
					break;
					
					case 'createValue':
					$view['data']['data'] = $this->model->create($this->model->section.'.'.$this->model->name);
					$view = [
						'view' => 'values/value',
						'data' => [
							"model" => $this->model,
							"data" => $this->model->config('current.action'),
							"parent" => $this->model->section
						]
					];
					break;
					
					case 'createSection':
					$this->model->create();
					$view = [
						'view' => 'values/index',
						'data' => [
							"model" => $this->model,
							"data" => []
						]
					];
					break;
				}
			}
		}
		switch($this->model->config('current.action.success') && \Yii::$app->request->isAjax && (Helper::boolval(@$_REQUEST['getHtml']) === true))
		{
			case true:
			$this->model->config('current.action.data', $this->renderAjax($view['view'], $view['data']));
			break;
		}
		return $this->finalAction();
	}
	
	public function actionGet()
	{
		$ret_val = [
			'success' => false,
			'action' => 'get',
			'message' => 'Get configuration',
			'class' => ''
		];
		switch($this->model->validate())
		{
			case true:
			switch($this->model->what)
			{
				case 'section':
				if($this->model->section)
				{			
					$ret_val["success"] = true;
					$ret_val["section"] = $this->model->section;
					
					if($this->model->section)
						$values = $this->model->config('current.config.'.$this->model->section);
					else
						$values = $this->model->config('current.config');
						
					switch(Response::getFormat())
					{
						case 'html':
						case 'modal';
						$ret_val['data'] = $this->renderAjax('values/index', [
							"model" => $this->model,
							"values" => $values,
							"parent" => $this->model->section
						]);
						break;
						
						case 'json':
						if(\Yii::$app->request->get("getHtml"))
							$ret_val['data'] = $this->renderAjax('values/index', [
								"model" => $this->model,
								"values" => $values,
								"parent" => $this->model->section
						]);
						else
							$ret_val['data'] = $values;
						break;
					
						default:
						$ret_val['data'] = $values;
						break;
					}
				}
				break;
			}
			break;
		}
		Response::viewOptions('args', [
			'content' => ArrayHelper::getValue($ret_val, 'data', '')
		]);
		$this->model->config('current.action', $ret_val);
		return $this->finalAction();
	}
	
	public function actionDelete()
	{
		switch(isset($_REQUEST[$this->model->formName()]))
		{
			case true:
			$this->model->setScenario($this->action->id.ucfirst($_POST[$this->model->formName()]['what']));
			$this->model->load($_POST);
			
			if(\Yii::$app->request->isAjax && (@Helper::boolval($_REQUEST['do']) !== true)) {
				$this->setResponseFormat('json');
				return \yii\widgets\ActiveForm::validate($this->model);
			}
				
			if($this->model->validate())
			{
				switch($this->model->getScenario())
				{
					case 'deleteContainer':
					$this->model->deleteContainer($this->model->value, null, $this->model->engine);
					break;
					
					case 'deleteValue':
					case 'deleteSection':
					$this->model->delete();
					break;
				}
			}
			break;
		}
		return $this->finalAction();
	}
	
	public function actionUpdate()
	{
		switch(isset($_POST[$this->model->formName()]))
		{
			case true:
			$this->model->setScenario($this->action->id.ucfirst($_POST[$this->model->formName()]['what']));
			$this->model->load($_POST);
			
			if(\Yii::$app->request->isAjax && (@Helper::boolval($_REQUEST['do']) !== true)) {
				$this->setResponseFormat('json');
				return \yii\widgets\ActiveForm::validate($this->model);
			}
				
			if($this->model->validate())
			{
				switch($this->model->getScenario())
				{					
					case 'updateContainer':
					case 'updateValue':
					case 'updateSection':
					$this->model->update();
					break;
					
					case 'updateComment':
					$this->model->comment();
					break;
				}
			}
			break;
		}
		return $this->finalAction();
	}
	/*
	 * Where do we go after an action?
	 * @params mixed $params
	 */
	protected function finalAction($params=null)
	{
		\Yii::$app->getSession()->setFlash(
			@$this->model->config('current.action.class'),
			$this->model->config('current.action.message')
		);
		switch(\Yii::$app->request->isAjax)
		{
			//if this is an ajax call then print the result
			case true:
			$this->model->config('current.action.flash', \Yii::$app->getSession()->getFlash(
			$this->model->config('current.action.class'), null, true));
			Response::viewOptions('args.content', $this->model->config('current.action'));
			$format = Response::formatSpecified() ? $this->getResponseFormat() : 'json';
			$this->setResponseFormat($format);
			return $this->renderResponse($this->model->config('current.action'), null, true);
			break;
			
			//otherwise we're going back to the index
			default;
			$this->redirect(\Yii::$app->request->getReferrer());
			break;
		}
	}
	
	/*---------------------
	  Private functions
	 --------------------*/
	
};

?>
