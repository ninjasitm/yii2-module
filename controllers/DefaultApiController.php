<?php

namespace nitm\controllers;

use Yii;
use yii\helpers\Html;
use yii\rest\Controller;
use nitm\helpers\Session;
use nitm\helpers\Response;
use nitm\models\Configer;
use nitm\helpers\ArrayHelper;

class DefaultApiController extends Controller
{
	use \nitm\traits\Configer, \nitm\traits\Controller, \nitm\traits\ControllerActions;

	public $model;

	public function behaviors()
	{
		$behaviors = array(
			'verbFilter' => [
				'class' => \yii\filters\VerbFilter::className(),
				'actions' => [
					'filter' => ['get', 'post'],
				]
			],
		);
		return array_merge(parent::behaviors(), $behaviors);
	}

	public function init()
	{
		// get the default css and meta tags
		$this->initAssets();
		$registered = Session::isRegistered(Session::settings);
		switch(!$registered || !(Session::size(Session::settings)))
		{
			case true:
			$this->initConfig();
			break;
		}
		//$this->initConfig(@Yii::$app->controller->id);
		parent::init();
		if(!$this->isResponseFormatSpecified)
			$this->setResponseFormat('json');
	}

	public static function has()
	{
		return [
		];
	}

	public function actionSearch()
	{
		$ret_val = [
			"success" => false,
			'action' => 'filter',
			"pour" => $this->model->isWhat(),
			"format" => $this->getResponseFormat(),
			'message' => "No data found for this filter"
		];
		switch(class_exists('@app/models/search/'.$this->model->isWhat()))
		{
			case true:
			$class = '@app/models/search/'.$this->model->isWhat();
			$className = $class::className();
			$searchModel = new $className();
			break;

			default:
			$serchModel = $this->model;
			break;
		}
		//$search->setScenario('filter');
		$class = array_pop(explode('\\', $this->model->className()));
        $this->data = $searchModel->search($_REQUEST[$class]['filter']);
		$partial = true;
		switch($this->model->successful())
		{
			case true:
			switch(\Yii::$app->request->isAjax)
			{
				case true:
				$ret_val['data'] = $this->renderPartial('data', ["model" => $this->model]);
				$ret_val['success'] = true;
				$ret_val['message'] = "Found data for this filter";
				break;

				default:
				$this->setResponseFormat('html');
				Response::$viewOptions['args'] = [
					"content" => $this->renderAjax('data', ["model" => $this->model]),
				];
				$partial = false;
				break;
			}
			break;
		}
		return $this->renderResponse($ret_val, Response::$viewOptions, $partial);
	}

	/**
	 * [listInternal description]
	 * @param  string $for  [description]
	 * @param  string|null $type [description]
	 * @return [type]       [description]
	 */
	protected function listInternal($for, $type=null)
	{
		$this->setResponseFormat('json');
		$options = [];
		list($className, $typeName) = $this->getTypeClass($for);

		if(!is_null($className))
			return [['id' => 0, 'text' => "No result Found"]];

		return $this->getList($className, $typeName, $options);
	}

	/**
	 * [getList description]
	 * @param  string $searchClass [description]
	 * @param  array $options     [description]
	 * @param  string $level       [description]
	 * @return array              List of items based on $searchClass
	 */
	public function getList($searchClass, $options=[], $level='summary')
	{
		$params = \Yii::$app->request->get();
		$returnDataProvider = ArrayHelper::remove($options, 'returnDataProvider', false);
		$params['text'] = isset($params['text']) ? $params['text'] : \Yii::$app->request->get('term');

		$cacheKey = json_encode($params);
		if(\nitm\helpers\Cache::exists($cacheKey))
			$ret_val = \nitm\helpers\Cache::get($cacheKey);
		else {
			$ret_val = [];
			$construct = ArrayHelper::remove($options, 'construct', []);
	        $model = new $searchClass(array_merge([
				'booleanSearch' => true,
				'inclusiveSearch' => true,
				'exclusiveSearch' => false,
				'mergeInclusive' => false,
				'defaults' => [
					'orderby' => ['id' => SORT_DESC],
					'sort' => ['id' => SORT_DESC]
				]
			], $construct));

			$dataProvider = $model->search($params);

			$model->applyFilters($dataProvider->query, ArrayHelper::getValue($options, 'queryOptions', []));

			$dataProvider->pagination = [
				'pageSize' => ArrayHelper::getValue(\Yii::$app->params, 'pagination.'.\Yii::$app->controller->id, 20)
			];

			if($returnDataProvider)
				return $dataProvider;
			else {
				$ret_val = $this->processData($dataProvider, $level);
			}
		}

		return (sizeof(array_filter($ret_val)) >= 1) ? $ret_val : [['id' => 0, 'text' => "Nothing Found"]];
	}

	protected function processData($dataProvider, $level=null)
	{
		$ret_val = [];
		foreach($dataProvider->getModels() as $id=>$item)
		{
			$level = isset($options['getter']) && is_callable($options['getter']) ? 'getter' : $level;
			switch($level)
			{
				case 'full':
				$_ = $this->getFullInfo($item);
				break;

				case 'summary':
				$_ = $item->toArray();
				$this->addExtraFields($item);
				break;

				default:
				$_ = $this->getListInfo($item);
				break;
			}
			$ret_val[] = $_;
		}
		return $ret_val;
	}

	/**
	 * Add the extra fields to the array
	 * @param array  $array The array to add relations to
	 * @param \pickledup\models\Entity $model The model to get info from
	 */
	protected function addExtraFields($model)
	{
		$extraFields = $fields = [];
		// Get the related fields that hace keys int eh model
		$fields = array_intersect_key(array_keys($model->extraFields()), array_keys($model->fields()));
		$extraFields = $model->toArray($model->extraFields(), $fields);

		$filterField = function ($field) use(&$filterField) {
			switch(true)
			{
				case is_array($field) && is_array(current($field)):
				foreach($field as $f=>$v)
				{
					$v = $filterField($v);
					if(is_array($v)) {
						$field[current($v)['id']] = current($v);
						unset($field[$f]);
					} else {
						unset($field[$f]);
					}
				}
				return $field == [] ? null : $field;
				break;

				case is_null($field) || is_array($field) && @is_null($field['id']):
				case is_null($field);
				return null;
				break;

				case !is_array($field):
				case is_array($field) && !isset($field['id']):
				case is_array($field) && isset($field['id']) && is_null($field['id']):
				return null;
				break;
			}
			$relatedKeys = preg_grep('/(_id$)/', array_keys($field));
			array_walk($relatedKeys, function ($related) use($field) {
				$field[$related] = (array) $field[$related];
			});
			$field = [$field['id'] => $field];
			return $field;
		};

		array_walk($extraFields, function (&$values, $group) use($filterField) {
			$values = $filterField($values);
			if(is_array($values)) {
				//
				$values = array_values($values);
				try {
					if(isset($this->extraFields[$group])) {
						$this->extraFields[$group] = array_merge($this->extraFields[$group], $values);
					} else {
						$this->extraFields[$group] = $values;
					}
				} catch (\Exception $e) {
					\Yii::error($e);
				}
			}
		});
	}

	/**
	 * [getListInfo description]
	 * @param  object $item The item being earched
	 * @return [type]       An array of the list info attributes
	 */
	protected function getListInfo($item)
	{
		return [
			"id" => $item->getId(),
			"value" => $item->getId(),
			"text" =>  $item->title(),
			"label" => $item->title()
		];
	}

	protected function getFullInfo($model)
	{
		 return array_merge($model->toArray(), $model->toArray($model->extraFields(), array_keys($model->extraFields())));
	}

}

?>
