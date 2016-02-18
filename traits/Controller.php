<?php
namespace nitm\traits;

use nitm\helpers\Response;
use nitm\helpers\Icon;
use nitm\helpers\Helper;
use nitm\helpers\ArrayHelper;
use yii\helpers\Html;

/**
 * Traits defined for expanding active relation scopes until yii2 resolves traits issue
 */
 trait Controller {

	protected $_logLevel = 3;
	protected $logCollection;
	protected $shouldLog = true;

    /*
	 * Check to see if somethign is supported
	 * @param mixed $what
	 */
	public function isSupported($what)
	{
		return (@$this->settings['supported'][$what] == true);
	}

	public static function assets()
	{
		return [];
	}

	/**
	 * Initialze the assets supported by this controller. Taken from static::has();
	 * @param mixed $assts Array of assets
	 * @param boolean $force. Force registration of assets
	 */
	public function initAssets($assets=[], $force=false)
	{
		//don't init on ajax requests so that we don't send duplicate files
		if(\Yii::$app->request->isAjax && !$force)
			return;
		$assets = array_merge($assets, (array)static::assets());
		foreach($assets as $asset)
		{
			//This may be an absolute namespace to an asset
			switch(class_exists($asset))
			{
				case true:
				$asset::register($this->getView());
				break;

				default:
				//It isn't then it may be an asset we have in nitm/assets or nitm/widgets
				$class = $asset.'\assets\Asset';
				switch(class_exists($class))
				{
					case true:
					$class::register($this->getView());
					break;

					default:
					//This is probably not a widget asset but a module asset
					$class = '\nitm\assets\\'.static::properName($asset).'Asset';
					switch(class_exists($class))
					{
						case true:
						$class::register($this->getView());
						break;
					}
					break;
				}
				break;
			}
		}
	}
	public function getFormVariables($options, $modalOptions=[], $model)
	{
		return \nitm\helpers\Form::getVariables($options, $modalOptions, $model);
	}

	/**
	 * Use nitm logger to log something
	 * @param string|array $message
	 * @param int $level. Only log if logging level is above this
	 * @param string $action
	 * @param string $category The category to insert this with
	 * @param string $internalCategory
	 * @param object $model The model object
	 * @return boolean
	 */
	protected function log($message, $level=0, $action=null, $options=[], $model=null)
	{
		if(\Yii::$app->getModule('nitm')->enableLogger)
		{
			if(is_null($message))
				return false;

			if(!$model)
				$model = ($model instanceof \nitm\models\Data) ? $model : $this->model;

			/**
			 * Only log this information if the logging $level is less than or equal to the gloabl accepted level
			 */
			$options = array_merge([
				'category' => 'user-activity',
				'internal_category' => 'User Activity',
				'table_name' => $model->tableName(),
				'message' => $message,
				'action' => (is_null($action) ? $this->action->id : $action),
			], $options);
			return \Yii::$app->getModule('nitm')->log($options, $level, null, $model);
		}
		return false;
	}

	protected function commitLog()
	{
		return \Yii::$app->getModule('nitm')->commitLog();
	}

	protected function getWith()
	{
		return [];
	}

	/**
	 * Get te log parameters
	 * @param boolean $saved
	 * @param array $result
	 * @param \nitm\models\Data based $model
	 * @return array;
	 */
	protected function getLogParams($saved, $result, $model=null)
	{
		$action = strtolower(ArrayHelper::remove($result, 'actionName', $this->action->id));
		$level = ArrayHelper::remove($result, 'logLevel', $this->_logLevel);
		$category = ArrayHelper::remove($result, 'logCategory', 'User Action');
		$internalCategory = ArrayHelper::remove($result, 'internalLogCategory', 'user-activity');

		$baseArgs = [
			'category' => $category,
			'internal_category' => $internalCategory
		];

		if(!isset($result['collection_name']) && (isset($this->logCollection) && !is_null($this->logCollection)))
			$baseArgs['collection_name'] = $this->logCollection;

		if(!$model)
			$model = $this->hasProperty('model') && ($this->model instanceof \nitm\models\Data) ? $this->model : new \nitm\models\Data(['noDbInit' => true]);

		$id = ArrayHelper::remove($result, 'id', $model->getId());
		$message = [\Yii::$app->user->identity->username];

		array_push($message, ($saved ? $action.(in_array(substr($action, strlen($action)-1, 1), ['e']) ? 'd' : 'ed') : "failed to $action"), $this->model->isWhat());
		if($id)
			array_push($message, "with id $id");
		if(!$saved)
			array_push($message, "\n\nError was: \n\n".var_export($message));

		return [
			implode(' ', $message), $level, $action, $baseArgs, $model
		];
	}

	/**
	 * Prepare some standard Javascript functions
	 * @param boolean $force Force preparing the operation
	 * @param array $options
	 */
	protected function prepareJsFor($force=false, $options=[])
	{
		$options = $options == [] ? ['forms', 'actions'] : $options;
		if(!Response::viewOptions('js') || $force)	{
			$js = '';
			foreach((array)$options as $type)
			{
				switch($type)
				{
					case 'forms':
					$js .= 'module.initForms(null, "'.$this->model->isWhat().'");';
					break;

					case 'actions':
					$js .= 'module.initMetaActions(null, "'.$this->model->isWhat().'");';
					break;
				}
			}
			Response::viewOptions('js', new \yii\web\JsExpression('$nitm.onModuleLoad("entity", function (module) {'.$js.'})'));
			return true;
		}
		return false;
	}

	/**
	 * Determine how to return the data
	 * @param mixed $result Data to be displayed
	 */
	protected function renderResponse($result=null, $params=null, $partial=true)
	{
		Response::initContext(\Yii::$app->controller,  \Yii::$app->controller->getView());
		$params = is_null($params) ? Response::viewOptions() : $params;
		return Response::render($result, $params, $partial);
	}

	/**
	 * Get the desired display format supported
	 * @return string format
	 */
	protected function setResponseFormat($format=null)
	{
		return Response::setFormat($format);
	}

	/**
	 * Get the response format
	 * @method getResponseFormat
	 * @return string            The response format
	 */
	public function getResponseFormat()
	{
		return Response::getFormat();
	}

	/**
	 * Is the response format specified?
	 * @method isResponseFormatSpecified
	 * @return boolean
	 */
	public function getIsResponseFormatSpecified()
	{
		return Response::formatSpecified();
	}

	/**
	 * Determine what format to return for the response
	 * @method determineResponseFormat
	 * @param string			$format THe response format
	 * @return string 			The response format
	 */
	protected function determineResponseFormat($format=null)
	{
		if(Response::formatSpecified())
			return;

		if(\Yii::$app->request->isAjax)
			$this->setResponseFormat(\Yii::$app->request->get('_pjax') ? 'html' : 'json');
		else
			$this->setResponseFormat($format ?: 'html');

		return $this->responseFormat;
	}

	/*
	 * Return a string imploded with ucfirst characters
	 * @param string $name
	 * @return string
	 */
	protected static function properName($value)
	{
		return \nitm\helpers\ClassHelper::properName($value);
	}

	/**
     * Finds the Category model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param string $className
     * @param integer $id
     * @param array $with Load with what
     * @return the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($className=null, $id=null, $with=null, $queryOptions=[])
    {
		$ret_val = null;
		if($className == null)
			$className = $this->model->className();
		if($id != null || $queryOptions != [])
		{
			$query = $className::find();

			if($id && is_numeric($id)) {
                $pk = $className::primaryKey();
				$query->where([array_shift($pk) => $id]);
            } else if(is_array($id))
                $query->where($id);

			$with = is_array($with) ? $with : (is_null($with) ? null : [$with]);
			if(is_array($with))
				$query->with($with);

			if($queryOptions != [])
				foreach($queryOptions as $type=>$options)
					$query->$type($options);

			if(($ret_val = $query->one()) != null)
            	return $ret_val;
			else {
                $id = implode('-', (array)$id);
				if(defined('YII_DEBUG') && (defined('YII_ENV') && YII_ENV == 'dev'))
            		throw new \yii\web\NotFoundHttpException((new $className)->properName()." : $id doesn't exist!");
				return $ret_val;
            }
        } else
			if(defined('YII_DEBUG') && (defined('YII_ENV') && YII_ENV == 'dev'))
           		throw new \yii\web\NotFoundHttpException((new $className)->properName()." doesn't exist!");
		return $ret_val;
    }

	/**
	 * Extract the relation parameters
	 * @method extractRelationParameters
	 * @param  array                    $options  Array of specified options
	 * @return array                             The with parameters
	 */
	protected function extractRelationParameters($options)
	{
		return array_unique(array_merge($this->getWith(), ArrayHelper::getValue($options, 'with', ArrayHelper::getValue($options, 'queryOptions.with', []))));
	}

	/**
	 * Go through the relations and determine whether they shoudl be kept or excluded
	 * @method filterRelationParameters
	 * @param \yii\db\Query $query             The query object
	 * @param array with			    The relations
	 * @return boolean                  Relations were dropped
	 */
	protected function filterRelationParameters($query, $with=[])
	{
		if(in_array($query->className(), [
			'\yii\elasticsearch\ActiveQuery',
			'\yii\mongodb\ActiveQuery'
		]))
			$query->with = null;

		$query->with = array_unique((array)$query->with);
		return true;
	}

	/**
	 * Get some related videw options
	 * @method getViewOptions
	 * @param  array         $options User specified options
	 * @return array                  View options
	 */
	protected function getViewOptions($options=[])
	{
		$createOptions = isset($options['createOptions']) ? $options['createOptions'] : [];

		$filterOptions = isset($options['filterOptions']) ? $options['filterOptions'] : [];

		return [
			'createButton' => $this->getCreateButton($createOptions),
			'createMobileButton' => $this->getCreateButton(array_replace_recursive([
				'containerOptions' => [
					'class' => 'btn btn-default navbar-toggle aligned'
				]
			], $createOptions), 'Create'),
			'filterButton' => $this->getFilterButton($filterOptions),
			'filterCloseButton' => $this->getFilterButton($filterOptions, 'Close'),
			'isWhat' => $this->model->isWhat()
		];
	}

	/**
	 * Is this a form request for validation?
	 * @method isValidationRequest
	 * @return boolean             Whether this is a validation request
	 */
	protected function isValidationRequest()
	{
		return \Yii::$app->request->isAjax && (@Helper::boolval($_REQUEST['do']) !== true) && !\Yii::$app->request->get('_pjax');
	}

	/**
	 * Perform the validation request on the model
	 * @method performValidationRequest
	 * @return array                  The errors is there were any
	 */
	protected function performValidationRequest()
	{
		$this->setResponseFormat('json');
		return \yii\widgets\ActiveForm::validate($this->model);
	}

	/**
	 * Either create a new model or update an existing model
	 * @method getModel
	 * @param  string   	$scenario   The scenario we're working on
	 * @param  int|null   	$id 		The id of an existing model
	 * @param  string|null  $modelClass The model classs
	 * @return [type]               [description]
	 */
	protected function getModel($scenario, $id=null, $modelClass=null, $with=null)
	{
		$modelClass = !$modelClass ? $this->model->className() : $modelClass;
		$data = \Yii::$app->request->post();

		if(!is_null($id)) {
        	$this->model =  $this->findModel($modelClass, $id, is_null($with) ? $this->getWith() : $with);
		}
		else
        	$this->model =  new $modelClass;

		$this->model->setScenario($scenario);
		if(!empty($data))
			$this->model->load($data);
	}

	/**
	 * If there were errors after the save then handle them
	 * @method handleSaveErrors
	 * @param  array           $data The data to be saved
	 * @param  string			$action The action being performed
	 * @return string                 The message after success or failure
	 */
	protected function saveInternal($data, $action)
	{
		$ret_val = false;
		$result = [
			'message' => "Unable to {$action} ".$this->model->isWhat()
		];
		if (!empty($data) && $this->model->save()) {
			$ret_val = true;
			$result['message'] = implode(' ', [
				"Succesfully {$action}d ",
				$this->model->isWhat(),
				': '.$this->model->title()
			]);

			Response::viewOptions("view", '/'.$this->id.'/view');
		} else {
			if(!empty($data)) {
				$result['message'] = implode('<br>', array_map(function ($value) {
					return array_shift($value);
				}, $this->model->getErrors()));

				\Yii::$app->getSession()->setFlash('error', $ret_val);
			}
			else
				$this->shouldLog = false;

			/**
			 * If the save failed, we're most likely going back to the form so get the form variables
			 */
			Response::viewOptions(null, array_merge($this->getVariables($this->model->isWhat()), [
				"view" => '/'.$this->id."/$action"
			]), true);
		}
		return [$ret_val, $result];
	}

	/**
	 * Get the create button
	 * @method getCreateButton
	 * @param  array          	$options The options for the create button
	 * @param  string          	$text    The text for the button
	 * @return string                    The HTML button
	 */
	public function getCreateButton($options=[], $text=null, $isWhat=null)
	{
        $isWhat = $isWhat ?: $this->model->isWhat();
        $id = $isWhat ?: $this->id;
        $properName = $isWhat ? \nitm\helpers\ClassHelper::properName($isWhat) : $this->model->properName();
		$text = ucwords(\Yii::t('yii', " new ".($text ?: $properName)));
		$options = array_replace_recursive([
			'toggleButton' => [
				'tag' => 'a',
				'label' => Icon::forAction('plus')." ".$text,
				'href' => \Yii::$app->urlManager->createUrl(['/'.$id.'/form/create', '__format' => 'modal']),
				'title' => \Yii::t('yii', "Add a new ".$properName),
				'role' => 'dynamicAction createAction disabledOnClose',
				'class' => 'btn btn-success'
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

	/**
	 * Get the filter button
	 * @method getFilterButton
	 * @param  array          	$options The options for the filter button
	 * @param  string          	$text    The text for the button
	 * @return string                    The HTML button
	 */
	public function getFilterButton($options=[], $text='filter', $isWhat=null)
	{
        $isWhat = $isWhat ?: $this->model->isWhat();
		$containerOptions = isset($options['containerOptions']) ? $options['containerOptions'] : ['class' => 'navbar-toggle aligned'];
		unset($options['containerOptions']);
		return Html::tag('div', Html::button(Icon::forAction('filter')." ".ucwords($text), array_replace([
			'class' => 'btn btn-default',
			'data-toggle' => 'collapse',
			'data-target' => '#'.$isWhat.'-filter'
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
?>
