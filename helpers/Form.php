<?php
namespace nitm\helpers;

use yii\base\Behavior;
use nitm\helpers\Response;
use yii\helpers\Html;

/**
 * Form trait which supports the retrieval of form variables
 */
class Form extends Behavior
{
	public static function getVariables($model, $options=[], $modalOptions=[], $setViewOptions=true)
	{
		$ret_val = [
			"success" => false, 
			'data' => \yii\helpers\Html::tag('h3', "No form found", ['class' => 'alert alert-danger text-center'])
		];
		switch(!empty($options['scenario']) && (is_a($model, \nitm\models\Data::className())))
		{
			case true:
			$attributes = [];
			//$model->id = @$options['id'];
			switch($model->validate())
			{
				case true:
				$model->setScenario($options['scenario']);
				$options['modelOptions'] = (isset($options['modelOptions']) && is_array($options['modelOptions'])) ? $options['modelOptions'] : null;
				$model->requestModel = new $options['modelClass']($options['modelOptions']);
				$model->requestModel->id = @$options['id'];
				//this means we found our object
				switch($options['modelClass'])
				{
					case $model->className():
					switch(is_null(ArrayHelper::getValue($options, 'id', null)))
					{
						/**
						 * If there's no ID for the given model then use it as is
						 */
						case true:
						break;
						
						default:
						/**
						 * Otherwise we need to make sure this model exists
						 */
						$queryOptions = isset($options['queryOptions']) ? $options['queryOptions'] : [];
						$found = static::findQuery($options['id'], $options['modelClass'], $queryOptions)->one();
						$model = ($found instanceof $options['modelClass']) ? $found : $model;
						break;
					}
					/*switch(isset($options['provider']) && !is_null($options['provider']) && $model->hasMethod($options['provider']))
					{
						case true:
						$model = call_user_func_array([$model, $options['provider']], $options['args']);
						$model->requestModel = $model;
						break;
					}*/
					break;
					
					default:
					//Get the data according to get$options['param'] functions
					$model->requestModel->queryFilters['limit'] = 1;
					$model->requestModel->queryFilters[$model->requestModel->primaryKey()[0]] = $model->requestModel->getId();
					$model = array_shift($model->requestModel->getArrays());
					if(!$model)
						$model = new $options['modelClass'](@$options['construct']);
					else
						switch($model->hasMethod($options['provider']))
						{
							case true:
							call_user_func_array([$model, $options['provider']], $args);
							$model->queryFilters['limit'] = 1;
							$found = $model->getArrays();
							$model = empty($found) ? $model : $found[0];
							break;
						}
					break;
				}
				switch(!is_null($model) || $force)
				{
					case true:
					
					/**
					 * Get scenario and form options
					 */
					$scenario = isset($options['scenario']) ? $options['scenario'] : ($model->getIsNewRecord() ? 'create' : 'update');
					$model->setScenario($scenario);
					$action = isset($options['action']) ? $options['action'] : ($model->getIsNewRecord() ? 'create' : 'update');
					$formOptions = array_replace_recursive([
						'container' => [
							'id' => $model->isWhat()."-form".$model->getId().'-container',
							'class' => implode(' ', [
								$model->isWhat().'-'.$model->getScenario(),
								\Yii::$app->request->isAjax ? '' : 'wrapper'
							])
						],
						'action' => "/".$model->isWhat()."/$action".($action == 'create' ? '' : "/".$model->getId()),
						'options' => [
							'id' => $model->isWhat()."-form".$model->getId(),
							'role' => $scenario.$model->formName()
						]
					], \yii\helpers\ArrayHelper::getValue($options, 'formOptions', []));
					
					/**
					 * Setup view options
					 */
					$options['viewArgs'] = (isset($options['viewArgs']) && is_array($options['viewArgs'])) ? $options['viewArgs'] : (isset($options['viewArgs']) ? [$options['viewArgs']] : []);
					$footer = isset($options['footer']) ? $options['footer'] : Html::submitButton($model->isNewRecord ? \Yii::t('app', 'Create') : \Yii::t('app', 'Update'), [
						'class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary',
						'form' => $formOptions['options']['id'],
					]);
					
					$formArgs = [
						"view" => $options['view'],
						'modalOptions' => static::getModalOptions($modalOptions, $model),
						'title' => static::getTitle($model, $options['title']),
						'footer' => $footer
					];
						
					if($setViewOptions)
						Response::viewOptions(null, $formArgs);
					
					/**
					 * Get data provider information
					 */
					$dataProviderOptions = array_intersect_key($options, [
						'provider' => null,
						'args' => null,
						'force' => null
					]);
					$ret_val['data'] = static::getDataProvider($model, $dataProviderOptions);
					
					$formArgs['args'] = array_merge([
						'scenario' => $scenario,
						"formOptions" => $formOptions,
						"model" => $model,
						'dataProvider' => $ret_val['data'],
						'action' => $action,
						'type' => $model->isWhat(),
					], $options['viewArgs']);
					
					if($setViewOptions) {
						Response::viewOptions("args", $formArgs['args']);
						switch(\Yii::$app->request->isAjax)
						{
							case false:
							Response::viewOptions('options', [
								'style' => 'padding: 0 15px; position: absolute; height: 100%, width: 100%'
							]);
							break;
						}
					}
					else
					{
						$ret_val['form'] = $formArgs;
					}
					$ret_val['success'] = true;
					$ret_val['action'] = $options['param'];
					break;
				}
				break;
			}
			break;
		}
		return $ret_val;
	}
	
	protected static function findQuery($id, $modelClass, $options=[])
	{
		$find = $modelClass::find()->select('*')->where([$modelClass::primaryKey()[0] => $id]);
		if((sizeof($options) >= 1) && is_array($options))
		{
			foreach($options as $type=>$value)
			{
				$find->$type($value);
			}
		}
		return $find;
	}
	
	public static function getDataProvider($model, $options)
	{
		$ret_val = new \yii\data\ArrayDataProvider;
		if(!isset($options['provider']))
			return $ret_val;
		switch(is_array($options['provider']))
		{
			case true:
			$object = $model;
			foreach($options['provider'] as $func=>$property)
			{
				if(is_callable($property))
					$object = call_user_func($property, $object);
				else if(is_object($property))
					$object = call_user_func([$property, $func], $object);
				else if(is_object($object) && $object->hasMethod($property))
					$object = call_user_func_array([$object, $property], isset($options['args']) ? $options['args'] : []);
				else if(is_object($object) && $object->hasAttribute($property))
					$object = $object->$property;
			}
			$ret_val->setModels((array)$object);
			break;
			
			default:
			if($model->hasMethod($options['provider']) || (isset($options['force']) && $options['force'] == true))
				$ret_val->setModels(call_user_func_array([$model, $options['provider']], (array)@$options['args']));
			else if($model->hasAttribute($options['provider']) || $model->hasProperty($options['provider']))
					$options['dataProvider']->setModels((array)$model->getAttribute($options['provider']));
			break;
		}
		return $ret_val;
	}
	
	public static function getTitle($model, $options)
	{
		switch(1)
		{
			case is_callable($options):
			$title = $options($model);
			break;
			
			case ($model->hasProperty(@$options[0]) || $model->hasAttribute(@$options[0])):
			$title = $model->getAttribute($options[0]);
			break;
			
			case is_string($options):
			$title = $options;
			break;
			
			case is_array($options):
			$title = @$options[1];
			break;
			
			default:
			$title = ($model->getIsNewRecord() ? "Create" : "Update")." ".ucfirst($model->properName($model->isWhat()));
			break;
		}
		return $title;
	}
	
	protected static function getModalOptions($options, $model)
	{
		foreach($options as $option=>$settings)
		{
			switch(is_callable($settings))
			{
				case true:
				$options[$option] = $settings($model);
				break;
				
				default:
				$options[$option] = (array) $settings;
				break;
			}
		}
		return $options;
	}
	
	public static function getHtmlOptions($items=[], $idKey='id', $valueKey = 'name')
	{
		$ret_val = [];
		foreach($items as $idx=>$item)
		{
			switch(is_array($item->$valueKey))
			{
				case true:
				$ret_val[$idx] = static::getHtmlOptions($item->$valueKey);
				break;
				
				default:
				$ret_val[$item->$idKey] = $item->$valueKey;
				break;
			}
		}
		return $ret_val;
	}
	
}
?>