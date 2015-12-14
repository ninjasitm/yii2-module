<?php

namespace nitm\components\configer;

use Yii;
use yii\base\Model;
use nitm\helpers\Session;
use nitm\helpers\Cache;
use nitm\helpers\ArrayHelper;
use nitm\helpers\Json;
use nitm\models\configer\Container;
use nitm\models\configer\Section;
use nitm\models\configer\Value;
use nitm\models\DB;

/**
 * Class Configer
 * @package nitm\components\configer
 */

class DBStore extends BaseStore
{
	//public data
	public $is = 'db';

	public function init()
	{
		parent::init();
		$this->resource = \Yii::$app->db;
	}

	public function write($container, $data)
	{
		$message = "";
		$result = 'failure';
		$action = ["success" => "Create Config", "failure" => "Create Config Fail"];
		//write the sections
		foreach($data as $name=>$values)
		{
			$model = new Section(['scenario' => 'create']);
			$section->name = $name;
			$model->containerid = $this->container()->id;
			switch($model->validate())
			{
				case true:
				$model->save();
				$this->insert(array_keys($section), array_values($section));
				$sections[$name] = ['model' => $model, 'values' => $values];
				break;
			}
		}
		//write the values
		foreach($sections as $name=>$values)
		{
			foreach($values['values'] as $k=>$v)
			{
				$model = new Value(['scenario' => 'create']);
				$model->load($v);
				$model->containerid = $this->container()->id;
				$model->sectionid = $values['model']->id;
				switch($model->validate())
				{
					case true:
					$model->save();
					break;
				}
			}
		}
	}

	public function load($container, $fromSection=false)
	{
		$ret_val = \yii\helpers\ArrayHelper::getValue($this->container($container), 'values', []);
		return $ret_val;
	}

	public function read($contents)
	{
		//convert the raw config to the proper hierarchy;
		if(!is_array($contents))
			$contents = !$this->container($contents) ? [] : $this->container($contents)->getValues()->asArray()->all();
		$ret_val = [];

		if(is_array($contents))
			foreach($contents as $idx=>$data)
			{
				$section = $data['section_name'];
				$val_key = $data['name'];

				if(!isset($ret_val[$section]))
					$ret_val[$section] = [];

				//set the value
				$ret_val[$section][$val_key] = ArrayHelper::toArray($data);
			}
		else
			$ret_val = [];
		return $ret_val;
	}

	public function create($key, $originalValue, $container, $isSection=false)
	{
		$ret_val = ['success' => false];

		list($name, $section, $hierarchy) = array_values($this->resolveNameAndSection($key, true));
		$containerId = $this->container($container)->id;
		switch($isSection)
		{
			//We're creating a section
			case true:
			$value = [
				'containerid' => $containerId,
				'name' => $section,
			];
			$model = new Section($value);
			$message = "Added new section '%name%'";
			break;

			//We're creating a value
			default:
			$value = [
				'containerid' => $containerId,
				'sectionid' => $this->section($section, $containerId, false)->id,
				'value' => $originalValue,
				'name' => $name
			];
			$model = new Value($value);
			$message = "Added value '%name%' to section '%section%'";
			break;
		}
		$model->setScenario('create');
		switch($model->save())
		{
			case true:
			$ret_val['value'] = rawurlencode($originalValue);
			$ret_val['id'] = $model->id;
			$ret_val['container_name'] = $this->container($container)->name;
			$ret_val['unique_id'] = $key;
			$ret_val['section_name'] = $section;
			$ret_val = array_merge($ret_val, $value);
			$ret_val['success'] = true;
			$ret_val['message'] = $this->getMessage($message, $model);
			break;

			default:
			$ret_val['message'] = implode('<br>', array_map(function ($value) {
				return array_shift($value);
			}, $model->getErrors()));
			break;
		}
		return $ret_val;
	}

	public function update($id, $key, $value, $container, $isSection=false)
	{
		$ret_val = ['success' => false];

		list($name, $section, $hierarchy) = array_values($this->resolveNameAndSection($key, true));

		switch($isSection)
		{
			//we're updating a section
			case true:
			$message = "Updated the section name from '%oldValue%' to '%value%'";
			$value = ['name' => $value];
			$model = $this->section($id, $container, false);
			break;

			//we're updating a value
			default:
			$message = "Updated the value '%key%' from '%oldValue%' to '%value%'";
			$value = ['value' => $value];
			$ret_val['name'] = $name;
			$model = $this->value($section, $id, $key, false);
			break;
		}
		switch(is_object($model))
		{
			case true:
			$model->setScenario('update');
			$model->refresh();
			$model->load([$model->formName() => $value]);
			//Need to get the update message here sincea save rewrites the old attributes
			$ret_val['message'] = $this->getMessage($message, $model);
			switch($model->save())
			{
				case true:
				$ret_val['id'] = $model->id;
				$ret_val['container_name'] = $this->container($container)->name;
				$ret_val['unique_id'] = $key;
				$ret_val['section_name'] = $section;
				$ret_val = array_merge($ret_val, $value);
				$ret_val['success'] = true;
				break;

				default:
				$ret_val['message'] = implode('<br>', array_map(function ($value) {
					return array_shift($value);
				}, $model->getErrors()));
				break;
			}
			break;
		}
		return $ret_val;
	}

	public function comment($id, $key, $value, $container, $isSection=false)
	{
		$ret_val = ['success' => false];

		list($name, $section, $hierarchy) = array_values($this->resolveNameAndSection($key, true));

		switch($isSection)
		{
			//we're updating a section
			case true:
			$message = "Updated comment for section '%name%'";
			$value = ['comment' => $value];
			$model = null;
			//$model = $this->section($id, $container, false);
			break;

			//we're updating a value
			default:
			$message = "Updated the comment for '%name%' from '%oldValue%' to '%value%'";
			$value = ['comment' => $value];
			$ret_val['name'] = $name;
			$model = $this->value($section, $id, $key, false);
			break;
		}
		switch(is_object($model))
		{
			case true:
			$model->setScenario('update');
			$model->refresh();
			$model->load([$model->formName() => $value]);
			//Need to get the update message here sincea save rewrites the old attributes
			$ret_val['message'] = $this->getMessage($message, $model, 'comment');
			switch($model->save())
			{
				case true:
				$ret_val['id'] = $model->id;
				$ret_val['container_name'] = $this->container($container)->name;
				$ret_val['unique_id'] = $key;
				$ret_val['section_name'] = $section;
				$ret_val = array_merge($ret_val, $value);
				$ret_val['success'] = true;
				break;

				default:
				$ret_val['message'] = implode('<br>', array_map(function ($value) {
					return array_shift($value);
				}, $model->getErrors()));
				break;
			}
			break;
		}
		return $ret_val;
	}

	public function delete($id, $key, $container, $isSection=false)
	{
		$ret_val = ['success' => false, 'value' => null];

		list($name, $section, $hierarchy) = array_values($this->resolveNameAndSection($key, true));

		switch($isSection)
		{
			//we're deleting a section
			case true:
			$model = $this->section(!$id, $container, false);
			$message = "Deleted the section: $key";
			$delete['process'] = true;
			break;

			//we're deleting a value
			default:
			$ret_val['name'] = $id;
			$message = "Deleted the value: $key";
			$model = $this->value($section, $id, $key, false);
			break;
		}
		$model->refresh();
		//Need to get the update message here sincea save rewrites the old attributes
		$ret_val['message'] = $this->getMessage($message, $model);
		switch($model->save())
		{
			case true:
			if($model instanceof Value)
				$ret_val['value'] = $model->value;
			else
				$ret_val['isSection'] = true;
			$ret_val['success'] = true;
			break;

			default:
			$ret_val['success'] = true;
			$ret_val['message'] = "'$key' may have already been deleted";
			break;
		}
		return $ret_val;
	}

	public function createContainer($name, $in=null)
	{
		$ret_val = ["success" => false];
		$this->containerModel = new Container([
			'name' => $name,
			'scenario' => 'create'
		]);
		$ret_val['message'] = '';
		switch($this->containerModel->save())
		{
			case true:
			$ret_val['message']  .= "created container for $in";
			$data["sections"]['containerid'] = $this->containerModel->id;
			$data["sections"]['name'] = 'global';
			break;

			default:
			$ret_val['message']  ."Counldn't create container $name";
			break;
		}
		return $ret_val;
	}

	public function removeContainer($container, $in=null)
	{
		$ret_val = ["success" => false];
		switch(Section::updateAll(['deleted' => 1], ['containerid' => $name]))
		{
			case true:
			$ret_val['success'] = true;
			$message .= "deleted config for $name in $name\n\n";
			break;

			default;
			$message .= "couldn't delete config for $name\n\n";
			break;
		}
		$ret_val['message'] = "I ".$message;
		return $ret_val;
	}

	public function getSections($in=null)
	{
		$ret_val = [];
		switch(is_null($in))
		{
			case true:
			if($this->container())
				$result = (array)$this->container()->sections;
			else
				$result = [];
			break;

			default:
			if($this->container($in))
				$result = (array)$this->container($in)->getSections()->select(['id', 'name'])->all();
			else
				$result = [];
			break;
		}
		array_walk($result, function ($val, $key) use(&$ret_val) {
			$ret_val[$val->name] = $val->name;
		});
		return $ret_val;
	}

	public function getContainers($in, $objectsOnly=false)
	{
		if(!isset(static::$_containers))
		{
			$result = Container::find()->select(['id', 'name'])->indexBy('name')->all();
			static::$_containers = $result;
			array_walk($result, function ($val, $key) use(&$ret_val, $in) {
				if($in == $val->name)
					$this->containerModel = $val;
				$ret_val[$val->name] = $val->name;
			});
			static::$_containers = $ret_val;
		}
		return static::$_containers;
	}

	public function container($container=null)
	{
		if(is_object($container))
			throw new \yii\base\Exception("Container is an object");

		switch(1)
		{
			case $container == null:
			case $this->containerModel instanceof Container && $container == $this->containerModel->name:
			$ret_val = $this->containerModel;
			break;

			default:
			$ret_val = new Container([
				'name' => $container
			]);
			$ret_val->populateRelation('values', []);
			break;
		}

		$containerKey = $this->containerKey($container);
		$hasNew = static::hasNew();

		if(isset(static::$_containers[$containerKey]))
			$this->containerModel = $ret_val = static::$_containers[$containerKey];
		else if(Cache::cache()->exists($containerKey)) {
			$this->containerModel = $ret_val = static::$_containers[$containerKey] = Cache::getModel($this, $containerKey);
		} else {
			switch(1)
			{
				case !$this->containerModel instanceof Container:
				case (is_object($this->containerModel) && !($this->containerModel->name == $container || $this->containerModel->id == $container)):

				//Are we looking for an id or name?
				$where = is_numeric($container) ? ['id' => $container] : ['name' => $container];
				$ret_val = Container::find()
					->where($where)
					->with('sections')
					->one();

				if(!($ret_val instanceof Container)) {
					$ret_val = new Container(['name' => $container]);
					$ret_val->populateRelation('values', []);
					$ret_val->populateRelation('sections', []);
				}
				$this->containerModel = $ret_val;
				Cache::setModel($containerKey, [
					'_class' => Container::className(),
					'_data' => array_merge(ArrayHelper::toArray($this->containerModel), [
						'values' => ArrayHelper::toArray($this->containerModel->values),
						'sections' => ArrayHelper::toArray($this->containerModel->sections)
					])
				], false, 30);
				break;
			}
		}
		return $ret_val;
	}

	public function section($section, $container=null, $asArray=true)
	{
		$ret_val = null;
		if(is_null($container))
			if(!is_null($this->containerModel))
				$container = $this->containerModel->name;
			else
				throw new \yii\base\IException("The container has not been instanciated nor was one passed");

		switch(isset($this->container($container)->sections[$section]))
		{
			case false:
			if(!$this->sectionModel instanceof Section)
			{
				$where = is_numeric($section) ? ['id' => $section] : ['name' => $section];
				$found = $this->containerModel->getSections()
					->where($where)
					->one();
				$this->sectionModel = $found instanceof Section ? $found : null;
				$ret_val = $this->sectionModel;
				$this->containerModel->populateRelation('sections', array_merge($this->containerModel->sections, [$section => $ret_val]));
				Cache::setModel('config-container-'.$this->container()->name, $this->containerModel);
			}
			break;

			default:
			$ret_val = $this->containerModel->sections[$section];
			break;
		}
		if(!$asArray && is_array($ret_val))
			$ret_val = new Section($ret_val);
		return $ret_val;
	}

	public function value($section, $id, $key=null, $asArray=true)
	{
		$ret_val = null;
		$sectionModel = $this->section($section, null, false);

		if(!($sectionModel instanceof Section))
			return null;
		else if(isset($this->containerModel->values[$id])) {
			$ret_val = $this->containerModel->values[$id];
		} else if(isset($this->containerModel->values[$key])) {
			$ret_val = $this->containerModel->values[$key];
		} else {
			$where = is_numeric($id) ? ['id' => $id] : ['name' => $id];

			$where['sectionid'] = $sectionModel->getId();
			$where['containerid'] = $sectionModel->containerid;
			$query = Value::find()
				->where($where);
			if($asArray)
				$query->asArray();
			$ret_val = $query->one();
		}

		if(!$asArray && is_array($ret_val))
			$ret_val = new Value($ret_val);
		return $ret_val;
	}

	/**
	 * Get the return message for a model
	 * @param string $template The tokeniczed string using %token% format
	 * @param object $model
	 * @param string $requestedOldAttribute The attributte containing the changed value
	 * @return string
	 */
	protected function getMessage($template, $model, $requestedOldAttribute=null)
	{
		$oldAttribute = is_null($requestedOldAttribute) ? 'name' : $requestedOldAttribute;
		$value = $model->name;
		switch(1)
		{
			case $model instanceof Section:
			$section = $model->name;
			$container = $this->container()->name;
			break;

			case $model instanceof Container:
			$container = $model->name;
			$section = '';
			break;

			default:
			$section = $model->getSection()->one()->name;
			$container = $this->container()->name;
			$value = $model->value;
			$oldAttribute = is_null($requestedOldAttribute) ? 'value' : $requestedOldAttribute;
			break;
		}
		return str_replace([
				'%id%',
				'%value%',
				'%oldValue%',
				'%key%',
				'%name%',
				'%section%',
				'%container%'
			], [
				$model->id,
				$value,
				$model->getOldAttribute($oldAttribute),
				$model->name,
				$model->name,
				$section,
				$container

			], $template);
	}

	private static function hasNew()
	{
		if(!isset(self::$hasNew))
		{
			self::$hasNew = current(Value::find()
				->select("('".static::getLastChecked()."' < MAX(updated_at)) AS hasNew")
				->asArray()
				->one()) ? true : false;
			static::setLastChecked();
		}
		return self::$hasNew;
	}
}
?>
