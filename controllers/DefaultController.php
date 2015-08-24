<?php

namespace nitm\controllers;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use nitm\models\Category;
use nitm\helpers\Icon;
use nitm\helpers\Response;
use nitm\helpers\Helper;

class DefaultController extends BaseController
{
	public $boolResult;
	/**
	 * Redirect requests to the index page to the search function by default
	 */
	public $indexToSearch = true;
	public static $currentUser;
	
	public function init()
	{
		parent::init();
		static::$currentUser =  \Yii::$app->user->identity;
	}
	
	public function behaviors()
	{
		$behaviors = [
			'access' => [
				'rules' => [
					[
						'actions' => ['login', 'error'],
						'allow' => true,
						'roles' => ['?']
					],
					[
						'actions' => [
							'index', 'add', 'list', 'view', 'create', 
							'update', 'delete', 'form', 'filter', 'disable',
							'close', 'resolve', 'complete', 'error',
						],
						'allow' => true,
						'roles' => ['@'],
					],
				],
			],
			'verbs' => [
				'actions' => [
					'index' => ['get', 'post'],
					'list' => ['get', 'post'],
					'add' => ['get'],
					'view' => ['get'],
					'delete' => ['post'],
					'create' => ['post', 'get'],
					'update' => ['post', 'get'],
					'filter' => ['get', 'post']
				],
			],
		];
		return array_merge_recursive(parent::behaviors(), $behaviors);
	}
	
    /**
	* @inheritdoc
	*/
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
        ];
    }
	
	public function beforeAction($action)
	{
		switch($action->id)
		{
			case 'delete':
			case 'disable':
			case 'resolve':
			case 'complete':
			case 'close':
			case 'view':
			$this->enableCsrfValidation = false;
			break;
		}
		return parent::beforeAction($action);
	}

    /**
     * Default index controller.
     * @return string HTML index
     */
    public function actionIndex($className, $options=[])
    {
		$options = array_replace_recursive([
			'params' => \Yii::$app->request->get(),
			'with' => [], 
			'viewOptions' => [], 
			'construct' => [
				'inclusiveSearch' => true,
				'exclusiveSearch' => false,
				'forceExclusiveBooleanSearch' => false,
				'booleanSearch' => true,
				'queryOptions' => []
			],
		], $options);
		
        $searchModel = new $className($options['construct']);
		
        $dataProvider = $searchModel->search($options['params']);
		
		switch(1)
		{
			case $dataProvider->query instanceof \yii\elasticsearch\ActiveQuery:
			case $dataProvider->query instanceof \yii\mongodb\ActiveQuery:
			$dataProvider->query->with = null;
			break;
			
			default:
			$dataProvider->query->with($options['with']);
			break;
		}
		
		$dataProvider->pagination->route = isset($options['pagination']['route']) ? $options['pagination']['route'] : '/'.$this->id;

		$createOptions = isset($options['createOptions']) ? $options['createOptions'] : [];
		
		$filterOptions = isset($options['filterOptions']) ? $options['filterOptions'] : [];
		unset($options['createOptions'], $options['filterOptions']);
		
		$options['viewOptions'] = array_merge([
			'createButton' => $this->getCreateButton($createOptions),
			'createMobileButton' => $this->getCreateButton(array_replace_recursive([
				'containerOptions' => [
					'class' => 'btn btn-default btn-lg navbar-toggle aligned'
				]
			], $createOptions), 'Create'),
			'filterButton' => $this->getFilterButton($filterOptions),
			'filterCloseButton' => $this->getFilterButton($filterOptions, 'Close'),
			'isWhat' => $this->model->isWhat()
		], (array)@$options['viewOptions']);
		
		Response::viewOptions(null, [
			'view' => ArrayHelper::getValue($options, 'view', 'index'),
			'args' => array_merge([
				'dataProvider' => $dataProvider,
				'searchModel' => $searchModel,
				'model' => $this->model
			], $options['viewOptions'])
		]);
        return $this->renderResponse(null, Response::viewOptions(), false);
    }
	
	/*
	 * Get the forms associated with this controller
	 * @param string $param What are we getting this form for?
	 * @param int $unique The id to load data for
	 * @param array $options
	 * @return string | json
	 */
	public function actionForm($type=null, $id=null, $options=[], $returnData=false)
	{
		$options = $this->getVariables($type, $id, $options);
		$format = Response::formatSpecified() ? $this->getResponseFormat() : 'html';
		$this->setResponseFormat($format);
		
		if(\Yii::$app->request->isAjax)
			Response::viewOptions('js', "\$nitm.module('tools').init('".Response::viewOptions('args.formOptions.container.id')."');", true);
		
		return $returnData ? Response::viewOptions() : $this->renderResponse($options, Response::viewOptions(), \Yii::$app->request->isAjax);
	}

    /**
     * Displays a single model.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id, $modelClass=null, $options=[])
    {
		$modelClass = !$modelClass ? $this->model->className() : $modelClass;
        $this->model =  isset($options['model']) ? $options['model'] : $this->findModel($modelClass, $id, @$options['with']);
		$view = isset($options['view']) ? $options['view'] : '/'.$this->id.'/view';
		$args = isset($options['args']) ? $options['args'] : [];
		
		Response::viewOptions(null, $options);
		/**
		 * Some default values we would like
		 */
		Response::viewOptions("view", '@nitm/views/view/index');
		Response::viewOptions('args', array_merge([
			'content' => $this->renderAjax($view, array_merge(["model" => $this->model], $args)),
		], ArrayHelper::getValue($options, 'args', [])));
			
		if(Response::viewOptions('assets')) {
			$this->initAssets(Response::viewOptions('assets'), true);
		}
			
		if(!Response::viewOptions('scripts'))	
			Response::viewOptions('scripts', new \yii\web\JsExpression("\$nitm.onModuleLoad('entity', function (){\$nitm.module('entity').initForms(null, '".$this->model->isWhat(true)."').initMetaActions(null, '".$this->model->isWhat(true)."');})"));
				
		Response::viewOptions('title', Response::viewOptions('title') ? 
\nitm\helpers\Form::getTitle($this->model, ArrayHelper::getValue(Response::viewOptions(), 'title', [])) : '');
		
		Response::$forceAjax = false;
		
		$this->log($this->model->properName()."[$id] was viewed from ".\Yii::$app->request->userIp, 3);
		
		return $this->renderResponse(null, Response::viewOptions(), (\Yii::$app->request->get('__contentOnly') ? true : \Yii::$app->request->isAjax));
    }
	
    /**
     * Creates a new Category model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate($modelClass=null, $viewOptions=[])
    {
		$this->action->id = 'create';
		$ret_val = false;
		$result = ['level' => 3];
		$level = 1;
		$modelClass = !$modelClass ? $this->model->className() : $modelClass;
		$post = \Yii::$app->request->post();
        $this->model =  new $modelClass(['scenario' => 'create']);
		$this->model->load($post);
		switch(\Yii::$app->request->isAjax && (@Helper::boolval($_REQUEST['do']) !== true) && !\Yii::$app->request->get('_pjax'))
		{
			case true:
			$this->setResponseFormat('json');
			return \yii\widgets\ActiveForm::validate($this->model);
			break;
		}
		
		switch(\Yii::$app->request->isAjax)
		{
			case true:
			$this->setResponseFormat(\Yii::$app->request->get('_pjax') ? 'html' : 'json');
			break;
			
			default:
			$this->setResponseFormat('html');
			break;
		}
        if (!empty($post) && $this->model->save()) {
			$metadata = isset($post[$this->model->formName()]['contentMetadata']) ? $post[$this->model->formName()]['contentMetadata'] : null;
			$ret_val = true;
			$result['message'] = implode(' ', [
				"Succesfully created new ",
				$this->model->isWhat(),
				': '.$this->model->title()
			]);
			switch($metadata && $this->model->addMetadata($metadata))
			{
				case true:
				\Yii::$app->getSession()->setFlash('success', "Added metadata");
				break;
			}
			Response::viewOptions("view", '/'.$this->id.'/view');
        } else {
			if(!empty($post)) {
				$result['message'] = implode('<br>', array_map(function ($value) {
					return array_shift($value);
				}, $this->model->getErrors()));
				
				\Yii::$app->getSession()->setFlash('error', $result['message']);
			}
			else
				$this->shouldLog = false;
				
			/**
			 * If the save failed, we're most likely going back to the form so get the form variables
			 */
			Response::viewOptions(null, array_merge($this->getVariables($this->model->isWhat()), [
				"view" => '/'.$this->id.'/create'
			]), true);
        }
		
		Response::viewOptions("args", array_merge($viewOptions, ["model" => $this->model]), true);
		return $this->finalAction($ret_val, $result);
    }
	
	/**
     * Updates an existing Category model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id, $modelClass=null, $with=[], $viewOptions=[])
    {
		$this->action->id = 'update';
		$ret_val = false;
		$result = ['level' => 3];
		$modelClass = !$modelClass ? $this->model->className() : $modelClass;
		$post = \Yii::$app->request->post();
        $this->model =  $this->findModel($modelClass, $id, $with);
		$this->model->setScenario('update');
		$this->model->load($post);
		
		if(\Yii::$app->request->isAjax && (@Helper::boolval($_REQUEST['do']) !== true) && !\Yii::$app->request->get('_pjax')) {
			$this->setResponseFormat('json');
			return \yii\widgets\ActiveForm::validate($this->model);
		}
		 
		if(\Yii::$app->request->isAjax && !Response::formatSpecified())
			$this->setResponseFormat(\Yii::$app->request->get('_pjax') ? 'html' : 'json');
		else
			$this->setResponseFormat('html');
		
        if (!empty($post) && $this->model->save()) {
			$metadata = isset($post[$this->model->formName()]['contentMetadata']) ? $post[$this->model->formName()]['contentMetadata'] : null;
			$ret_val = true;
			switch($metadata && $this->model->addMetadata($metadata))
			{
				case true:
				\Yii::$app->getSession()->setFlash(
					'success',
					"Updated metadata"
				);
				break;
			}
			$result['message'] = implode(' ', [
				"Succesfully updated ",
				$this->model->isWhat(),
				': '.$this->model->title()
			]);
			Response::viewOptions("view",  '/'.$this->model->isWhat().'/view');
			
        } else {
			if(!empty($post)) {
				$result['message'] = implode('<br>', array_map(function ($value) {
					return array_shift($value);
				}, $this->model->geterrors()));
				\Yii::$app->getSession()->setFlash('error', $result['message']);
			}
			else
				$this->shouldLog = false;
			
			/**
			 * If the save failed, we're most likely going back to the form so get the form variables
			 */
			Response::viewOptions(null, array_merge($this->getVariables($this->model->isWhat(), $this->model->getId()), [
				"view" => '/'.$this->model->isWhat().'/update'
			]), true);
			
        }
				
		Response::viewOptions("args", array_merge($viewOptions, ["model" => $this->model]), true);
		
		return $this->finalAction($ret_val, $result);
    }

    /**
     * Deletes an existing Category model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id, $modelClass=null)
    {
		$deleted = false;
		$modelClass = !$modelClass ? $this->model->className() : $modelClass;
        $this->model =  $this->findModel($modelClass, $id);
		if(is_object($this->model))
		{
			switch(1)
			{
				case \Yii::$app->user->identity->isAdmin():
				case $this->model->hasAttribute('author_id') && ($this->model->author_id == \Yii::$app->user->getId()):
				case $this->model->hasAttribute('user_id') && ($this->model->user_id == \Yii::$app->user->getId()):
				$attributes = $this->model->getAttributes();
				if($this->model->delete())
				{
					$deleted = true;
					$this->model = new $modelClass($attributes);
				}
				$deleted = true;
				$level = 1;
				break;
				
				default:
				$level = 6;
				break;
			}
		}
		
		$this->setResponseFormat('json');
		return $this->finalAction($deleted, ['redirect' => \Yii::$app->request->getReferrer(), 'logLevel' => $level]);
    }
	
	public function actionClose($id)
	{
		return $this->booleanAction($this->action->id, $id);
	}
	
	public function actionComplete($id)
	{
		return $this->booleanAction($this->action->id, $id);
	}
	
	public function actionResolve($id)
	{
		return $this->booleanAction($this->action->id, $id);
	}
	
	public function actionDisable($id)
	{
		return $this->booleanAction($this->action->id, $id);
	}

    public static function booleanActions()
	{
		return [
			'close' => [
				'scenario' => 'close',
				'attributes' => [
					'attribute' => 'closed',
					'blamable' => 'closed_by',
					'date' => 'closed_at'
				],
				'title' => [
					'Re-Open',
					'Close'
				]
			],
			'complete' => [
				'scenario' => 'complete',
				'attributes' => [
					'attribute' => 'completed',
					'blamable' => 'completed_by',
					'date' => 'completed_at'
				],
				'title' => [
					'In-Complete',
					'Complete'
				]
			],
			'resolve' => [
				'scenario' => 'resolve',
				'attributes' => [
					'attribute' => 'resolved',
					'blamable' => 'resolved_by',
					'date' => 'resolved_at'
				],
				'title' => [
					'Un-Resolve',
					'Resolve'
				]
			],
			'disable' => [
				'scenario' => 'disable',
				'attributes' => [
					'attribute' => 'disabled',
					'blamable' => 'disabled_by',
					'date' => 'disabled_at'
				],
				'title' => [
					'Enable',
					'Disable'
				]
			],
			'delete'=> [
				'scenario' => 'delete',
				'attributes' => [
					'attribute' => 'deleted',
					'blamable' => 'deleted_by',
					'date' => 'deleted_at'
				],
				'title' => [
					'Restore',
					'Delete'
				]
			]
		];
	}
	
	protected function booleanAction($action, $id)
	{
		$saved = false;
        $this->model = $this->findModel($this->model->className(), $id);
		if(array_key_exists($action, static::booleanActions()))
		{
			extract(static::booleanActions()[$action]);
			$this->model->setScenario($scenario);
			$this->boolResult = !$this->model->getAttribute($attributes['attribute']) ? 1 : 0;
			foreach($attributes as $key=>$value)
			{
				switch($this->model->hasAttribute($value))
				{
					case true:
					switch($key)
					{
						case 'blamable':
						$this->model->setAttribute($value, (!$this->boolResult ? null : \Yii::$app->user->getId()));
						break;
						
						case 'date':
						$this->model->setAttribute($value, (!$this->boolResult ? null : new \yii\db\Expression('NOW()')));
						break;
					}
					break;
				}
			}
			$this->model->setAttribute($attributes['attribute'], $this->boolResult);
			if(!Response::formatSpecified())
				$this->setResponseFormat('json');
			
			if(isset($afterAction) && is_callable($afterAction))
				$afterAction($this->model);
				
			$saved = $this->model->save();
		}
		
		$this->shouldLog = true;
		$actionTitle = strtolower($title[(int)$this->boolResult]);
		$actionTitle .= (in_array(substr($actionTitle, strlen($actionTitle)-1, 1), ['e']) ? 'd' : 'ed');
		return $this->finalAction($saved, [
			'logLevel' => 1,
			'actionName' => $actionTitle,
			'message' => implode(' ', ["Successfully", $actionTitle, $this->model->isWhat().':', $this->model->title()])
		]);
	}
	
	/**
	 * Put here primarily to handle action after create/update
	 */
	protected function finalAction($saved=false, $args=[])
	{
		$ret_val = is_array($args) ? $args : [
			'success' => false,
		];
        if ($saved) {
		
			/**
			 * Perform logging if logging is enabled in the module and the controller enables it
			 */
			if(\Yii::$app->getModule('nitm')->enableLogger && $this->shouldLog) {
				call_user_func_array([$this, 'log'], $this->getLogParams($saved, $args));
				foreach(['logLevel', 'collection_name'] as $remove)
					unset($ret_val[$remove]);
				$this->commitLog();
			}
			
			switch(\Yii::$app->request->isAjax)
			{
				case true:
				switch(array_key_exists($this->action->id, static::booleanActions()))
				{
					case true:
					extract(static::booleanActions()[$this->action->id]);
					$ret_val['success'] = true;
					$booleanValue = (bool)$this->model->getAttribute($attributes['attribute']);
					$ret_val['title'] = @ArrayHelper::getValue((array)$title, $booleanValue, '');
					$iconName = @ArrayHelper::getValue((array)$icon, $booleanValue, $this->action->id);
					$ret_val['actionHtml'] = Icon::forAction($iconName, $booleanValue);
					$ret_val['action'] = isset($action) ? $action : $this->action->id;
					$ret_val['data'] = $this->boolResult;
					$ret_val['class'] = [];
					$ret_val['indicate'] = $this->model->getStatus();
					switch(\Yii::$app->request->get(static::ELEM_TYPE_PARAM))
					{
						case 'li':
						if(method_exists($this->model, 'getStatus'))
							$ret_val['class'][] = \nitm\helpers\Statuses::getListIndicator($this->model->getStatus());
						break;
						
						default:
						if(method_exists($this->model, 'getStatus'))
							$ret_val['class'][] = \nitm\helpers\Statuses::getIndicator($this->model->getStatus());
						break;
					}
					$ret_val['class'] = implode(' ', $ret_val['class']);
					break;
					
					default:
					$format = Response::formatSpecified() ? $this->getResponseFormat() : 'json';
					$this->setResponseFormat($format);
					if($this->model->hasAttribute('created_at'))
						$this->model->created_at = \nitm\helpers\DateFormatter::formatDate($this->model->created_at);
					switch($this->action->id)
					{
						case 'update':
						if($this->model->hasAttribute('updated_at')) {
							$this->model->updated_at = \nitm\helpers\DateFormatter::formatDate($this->model->updated_at);
						}
						break;
					}
					$viewFile = $this->model->isWhat().'/view';
					$ret_val['success'] = true;
					$ret_val['action'] = $this->action->id;
					switch($this->getResponseFormat())
					{
						case 'json':
						if(file_exists($this->getViewPath() . DIRECTORY_SEPARATOR . ltrim($viewFile, '/').'.php'))
							$ret_val['data'] = $this->renderAjax($viewFile, ["model" => $this->model]);
						break;
						
						default:
						if(file_exists($this->getViewPath() . DIRECTORY_SEPARATOR . ltrim($viewFile, '/')))
							Response::viewOptions('content', $this->renderAjax($viewFile, ["model" => $this->model]));
						else
							Response::viewOptions('content', true);
						break;
					}
					break;
				}
				break;
					
				default:
				\Yii::$app->getSession()->setFlash(@$ret_val['class'], @$ret_val['message']);
				return $this->redirect(isset($args['redirect']) ? $args['redirect'] : ['index']);
				break;
			}
        }
		if(!$saved)
			if($this->model->getErrors())
				$ret_val['message'] = array_map('implode', $this->model->getErrors(), ['. ']);
			else
				$ret_val['message'] = ArrayHelper::getValue($ret_val, 'message', 'There was an error creating a new '.$this->model->isWhat());
		$ret_val['id'] = $this->model->getId();
			
		return $this->renderResponse($ret_val, Response::viewOptions(), \Yii::$app->request->isAjax);
	}
	
	protected function getCreateButton($options=[], $text=null)
	{
		$text = is_null($text) ? strtoupper(\Yii::t('yii', " new ".$this->model->properName($this->model->isWhat()))) : $text;
		$options = array_replace_recursive([
			'toggleButton' => [
				'tag' => 'a',
				'label' => Icon::forAction('plus')." ".$text, 
				'href' => \Yii::$app->urlManager->createUrl(['/'.$this->id.'/form/create', '__format' => 'modal']),
				'title' => \Yii::t('yii', "Add a new ".$this->model->properName($this->model->isWhat())),
				'role' => 'dynamicAction createAction disabledOnClose',
				'class' => 'btn btn-success btn-lg'
			],
			//'dialogOptions' => [
			//	"class" => "modal-full"
			//],
			'containerOptions' => [
				'class' => 'navbar-collapse navbar-collapse-content'
			]
		], (array)$options);
		
		$containerOptions = $options['containerOptions'];
		unset($options['containerOptions']);
		
		return Html::tag('div', \nitm\widgets\modal\Modal::widget($options), $containerOptions);
	}
	
	protected function getFilterButton($options=[], $text='filter')
	{
		$containerOptions = isset($options['containerOptions']) ? $options['containerOptions'] : [
			'class' => 'navbar-toggle aligned'
		];
		unset($options['containerOptions']);
		return Html::tag('div', Html::button(Icon::forAction('filter')." ".ucfirst($text), array_replace([
			'class' => 'btn btn-default btn-lg',
			'data-toggle' => 'collapse',
			'data-target' => '#'.$this->model->isWhat().'-filter'
		], (array)$options)), $containerOptions);
	}
	
	/*
	 * Get the variables for a model
	 * @param string $param What are we getting this form for?
	 * @param int $unique The id to load data for
	 * @param array $options
	 * @return string | json
	 */
	protected function getVariables($type=null, $id=null, $options=[])
	{
		$force = false;
		$options['id'] = $id;
		$options['param'] = $type;
		
		if(isset($options['modelClass']))
		{
			$this->model = ($this->model->className() == $options['modelClass']) ? $this->model : new $options['modelClass'](@$options['construct']);
		}
		switch($type)
		{	
			//This is for generating the form for updating and creating a form for $this->model->className()
			default:
			$options = array_merge([
				'title' => ['title', 'Create '.static::properName($this->model->isWhat())],
				'scenario' => !$id ? 'create' : 'update',
				'provider' => null,
				'dataProvider' => null,
				'view' => isset($options['view']) ? $options['view'] : $type,
				'args' => [],
				'modelClass' => $this->model->className(),
				'force' => false	
			], $options);
			break;
		}
		$options['modalOptions'] = isset($options['modalOptions']) ? (array)$options['modalOptions'] : [];
		$modalOptions = array_merge([
			'body' => [
				'class' => 'modal-full'
			],
			'dialog' => [
				'class' => 'modal-full'
			],
			'content' => [
				'class' => 'modal-full'
			],
			'contentOnly' => true
		], $options['modalOptions']);
				
		unset($options['modalOptions']);
		return $this->getFormVariables($this->model, $options, $modalOptions);
	}
}