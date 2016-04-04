<?php

namespace nitm\controllers;
use yii\helpers\Html;
use nitm\helpers\ArrayHelper;
use nitm\models\Category;
use nitm\helpers\Response;
use nitm\helpers\Helper;
use nitm\helpers\Icon;

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
		$this->determineResponseFormat();
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
    public function actionIndex($className=null, $options=[])
    {
		$className = $className ?: $this->model->clasName();
		$options = array_replace_recursive([
			'params' => \Yii::$app->request->get(),
			'with' => [],
			'viewOptions' => [],
			'construct' => [
				'inclusiveSearch' => false,
				'exclusiveSearch' => true,
				'forceExclusiveBooleanSearch' => false,
				'booleanSearch' => true,
				'queryOptions' => []
			],
		], $options);

        $searchModel = new $className($options['construct']);

		$options['with'] = $this->extractWith($options);
		$searchModel->queryOptions['with'] = $options['with'];

        $dataProvider = $searchModel->search($options['params']);

		$this->filterWith($dataProvider->query, $options['with']);

		$dataProvider->pagination->route = isset($options['pagination']['route']) ? $options['pagination']['route'] : '/'.$this->id;

		$options['viewOptions'] = array_merge($this->getViewOptions($options), (array)@$options['viewOptions']);

		unset($options['createOptions'], $options['filterOptions']);

		Response::viewOptions(null, [
			'view' => ArrayHelper::getValue($options, 'view', 'index'),
			'args' => array_merge([
				'dataProvider' => $dataProvider,
				'searchModel' => $searchModel,
				'model' => $this->model
			], $options['viewOptions'])
		]);

		if(!$this->isResponseFormatSpecified)
			$this->setResponseFormat('html');

        return $this->renderResponse($this->responseFormat == 'json' ? $dataProvider->getModels() : null, Response::viewOptions(), false);
    }

	/**
	 * Performs filtering on models
	 * @method actionFilter
	 * @param  array       $options       Options for the view parameters
	 * @param  array       $modelOptions Options for the search model
	 * @return string                     Rendered data
	 */
	public function actionFilter($options=[], $modelOptions=[])
	{
		\nitm\search\widgets\SearchAsset::register($this->getView());
		$options = array_replace_recursive([
			'params' => \Yii::$app->request->get(),
			'with' => [],
			'viewOptions' => [],
		], $options);

		$ret_val = [
			"success" => false,
			'action' => 'filter',
			"format" => $this->responseFormat,
			'message' => "No data found for this filter"
		];

		$searchModelOptions = array_merge([
			'inclusiveSearch' => true,
			'booleanSearch' => true,
			'forceType' => true
		], $modelOptions);

		$className = ArrayHelper::getValue($options, 'className');
		if(!$className) {
			$namespace = ArrayHelper::getValue($options, 'namespace');
			if($namespace) {
				$className = rtrim($namespace,'\\')."\\".$this->model->formName();
			} else {
				throw new \Exception("No namespace or class was specified for filtering");
			}
		}

		$this->model = new $className($searchModelOptions);
		$type = $this->model->isWhat();
		$this->model->setIndexType($type);

		$this->model->type = $type;
		$this->model->setIs($type);

		$options['with'] = $this->extractWith(array_merge($options, $modelOptions));
		$this->model->queryOptions['with'] = $options['with'];

		$dataProvider = $this->model->search($options['params']);

		$this->filterWith($dataProvider->query, $options['with']);

		$dataProvider->pagination->route = "/$type";

		$view = ArrayHelper::getValue($options, 'view', 'index');

		//Change the context ID here to match the filtered content
		$this->id = $type;

		//Add support for Pjax requests here. If somethign was sent based on Pjax always return HTML
		if(\Yii::$app->getRequest()->get('_pjax') != null) {
			$this->setResponseFormat('html');
		}

		if($this->responseFormat === 'json') {
			$getParams = array_merge([$type], \Yii::$app->request->get());
			foreach(['__format', '_type', 'getHtml', 'ajax', 'do'] as $prop)
				unset($getParams[$prop]);

			$ret_val['data'] = $this->renderResponse(null, Response::viewOptions(), $this->response->isAjax);
			$ret_val['url'] = \Yii::$app->urlManager->createUrl($getParams);
			$ret_val['message'] = !$dataProvider->getCount() ? $ret_val['message'] : "Found ".$dataProvider->getTotalCount()." results matching your search";
		} else {
			Response::viewOptions(null, [
				'view' => ArrayHelper::getValue($options, 'view', 'index'),
				'args' => array_merge($this->getViewOptions(), [
					"dataProvider" => $dataProvider,
					'searchModel' => $this->model,
					'model' => $this->model,
					'primaryModel' => $this->model->primaryModel,
					'isWhat' => $type,
				], ArrayHelper::getValue($options, 'viewOptions', []))
			]);
			$ret_val = null;
		}

		return $this->renderResponse($ret_val, Response::viewOptions(), \Yii::$app->request->isAjax);
	}

	/*
	 * Get the forms associated with this controller
	 * @param string $param What are we getting this form for?
	 * @param int $unique The id to load data for
	 * @param array $options
	 * @return string | json
	 */
	public function actionForm($type, $id=null, $options=[], $returnData=false)
	{
		$parsedOptions = $this->getVariables($type, $id, $options);

		$this->determineResponseFormat('html');

		if(\Yii::$app->request->isAjax) {
			$containerId = Response::viewOptions('args.formOptions.container.id');
			$js = ArrayHelper::getValue($options, 'js', '');
			$js = is_array($js) ? $js : [$js];
			Response::viewOptions('js', implode(';', $js), true);
		}

		if($returnData)
			return Response::viewOptions();

		return $this->renderResponse($this->responseFormat == 'json' ? $parsedOptions : null, Response::viewOptions(), \Yii::$app->request->isAjax);
	}

    /**
     * Displays a single model.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id, $modelClass=null, $options=[])
    {
		$modelClass = !$modelClass ? $this->model->className() : $modelClass;
        $this->model =  isset($options['model']) ? $options['model'] : $this->findModel($modelClass, $id, array_merge($this->getWith(), ArrayHelper::getValue($options, 'with', [])));
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

		$this->prepareJsFor(true);

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
		$result = [
			'level' => 3,
			'/form/create'
		];
		$level = 1;
		$this->getModel('create', null, $modelClass);

		if($this->isValidationRequest())
			return $this->performValidationRequest();

		$this->determineResponseFormat('html');

		list($ret_val, $result) = $this->saveInternal(\Yii::$app->getRequest()->post(), 'create');

		Response::viewOptions("args", array_merge((array)$viewOptions, ["model" => $this->model]), true);
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
		$this->getModel('update', $id, $modelClass, $with);

		if($this->isValidationRequest())
			return $this->performValidationRequest();

		$this->determineResponseFormat('html');

		list($ret_val, $result) = $this->saveInternal(\Yii::$app->getRequest()->post(), 'update');

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
		$this->getModel('delete', $id);
		$modelClass = !class_exists($modelClass) ? $this->model->className() : $modelClass;
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
						$this->model->setAttribute($value, ($this->boolResult ? new \yii\db\Expression('NOW()') : NULL));
						break;
					}
					break;
				}
			}
			$this->model->setAttribute($attributes['attribute'], $this->boolResult);
			if(!$this->isResponseFormatSpecified)
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
			'message' => implode(' ', ["Successfully", $actionTitle, $this->model->isWhat(false).':', $this->model->title()])
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
			 * NOTICE: This is now handled at the Model level
			 */
			if(\Yii::$app->getModule('nitm')->enableLogger && $this->shouldLog) {
				//call_user_func_array([$this, 'log'], $this->getLogParams($saved, $args, $this->model));
				foreach(['logLevel', 'collection_name'] as $remove)
					unset($ret_val[$remove]);
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
					$iconName = @ArrayHelper::getValue((array)$icon, (int)$booleanValue, $this->action->id);
					$ret_val['actionHtml'] = Icon::forAction($iconName, $booleanValue);
					$ret_val['action'] = isset($action) ? $action : $this->action->id;
					$ret_val['data'] = $this->boolResult;
					$ret_val['class'] = [];
					$ret_val['indicate'] = $this->model->getStatus();
					switch(\Yii::$app->request->get(self::ELEM_TYPE_PARAM))
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
					$format = $this->isResponseFormatSpecified ? $this->responseFormat : 'json';
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

						case 'create':
						$ret_val['message'] .= ' '.Html::tag('a', 'Click Here to Edit', [
							'href' => $this->id.'/form/update/'.$this->model->id,
							'class' => 'btn btn-default'
						]);
						break;
					}
					$viewFile = $this->model->isWhat().'/view';
					$ret_val['success'] = true;
					$ret_val['action'] = $this->action->id;
					switch($this->responseFormat)
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
}
