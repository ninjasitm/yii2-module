<?php
namespace nitm\traits;

use nitm\helpers\Response;
use yii\helpers\ArrayHelper;

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
	
	public function getResponseFormat()
	{
		return Response::getFormat();
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
				'internal_category' => 'user-activity',
				'category' => 'User Activity',
				'table_name' => $model->tableName(),
				'message' => $message,
				'action' => (is_null($action) ? $this->action->id : $action), 
			], $options);
			return \Yii::$app->getModule('nitm')->log($level, $options, $model->className());
		}
		return false;
	}
	
	protected function commitLog()
	{
		return \Yii::$app->getModule('nitm')->commitLog();
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
	
	/*
	 * Determine how to return the data
	 * @param mixed $result Data to be displayed
	 */
	protected function renderResponse($result=null, $params=null, $partial=true)
	{
		Response::initContext(\Yii::$app->controller,  \Yii::$app->controller->getView());
		$params = is_null($params) ? Response::viewOptions() : $params;
		return Response::render($result, $params, $partial);
	}
	
	/*
	 * Get the desired display format supported
	 * @return string format
	 */
	protected function setResponseFormat($format=null)
	{
		return Response::setFormat($format);
	}
	
	/*
	 * Return a string imploded with ucfirst characters
	 * @param string $name
	 * @return string
	 */
	protected static function properName($value)
	{
		$ret_val = empty($value) ?  [] : array_map('ucfirst', preg_split("/[_-]/", $value));
		return implode($ret_val);
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
			
			if($id && is_numeric($id))
				$query->where([array_shift($className::primaryKey()) => $id]);

			$with = is_array($with) ? $with : (is_null($with) ? null : [$with]);
			if(is_array($with))
				$query->with($with);
			
			if($queryOptions != [])
				foreach($queryOptions as $type=>$options)
					$query->$type($options);
						
			if(($ret_val = $query->one()) instanceof $className)
            	return $ret_val;
			else
				if(defined('YII_DEBUG') && (defined('YII_ENV') && YII_ENV == 'dev'))	
            		throw new \yii\web\NotFoundHttpException((new $className)->properName()." : $id doesn't exist!");
				return $ret_val;
        } else
			if(defined('YII_DEBUG') && (defined('YII_ENV') && YII_ENV == 'dev'))	
           		throw new \yii\web\NotFoundHttpException((new $className)->properName()." doesn't exist!");
		return $ret_val;
    }
 }
?>
