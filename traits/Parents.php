<?php

/**
 * @package mhdevent/yii2-module
 *
 * Log traits for the Data model
 */
namespace nitm\traits;

use nitm\helpers\Cache as RealCache;
use yii\helpers\ArrayHelper;
use nitm\models\ParentMap;

trait Parents {

	/**
	 * Get the parent list
	 * @param boolean $url Get the url?
	 * @return string
	 */
	public function getParentList($url=true, $titleAttr='name', $clearable=false)
	{
		return \nitm\helpers\Helper::concatAttributes($this->parents(), function ($model) use($url, $titleAttr){
			return \yii\helpers\Html::tag('strong', ($url ? $model->url('id', $titleAttr) : $model->$titleAttr));
		}, ', ', true);
	}

    /**
	 * Adds the parents for this model
	 * ParentMap are specieid in the parent_ids attribute
	 * Parent object belong to the same table
	 * @param array parents THe parent Ids
	 */
	public function addParentMap($parents=[])
	{
		if(count($parents) >= 1)
		{
			$attributes = [
				'remote_type', 'remote_id', 'remote_class', 'remote_table',
				'parent_type', 'parent_id', 'parent_class', 'parent_table'
			];
			sort($attributes);

			/**
			 * Go through the parents and make sure the id mapping is correct
			 */
			foreach($parents as $idx=>$parent)
			{
				if(!$parent['parent_type'] || !$parent['parent_id'] || !$parent['parent_class'] || !$parent['parent_table'])
					continue;
				$parents[$parent['parent_id']] = array_merge([
					'remote_id' => $this->getId(),
					'remote_type' => $this->isWhat(),
					'remote_class' => $this->className(),
					'remote_table' => $this->tableName(),
				], $parent);

				ksort($parents[$parent['parent_id']]);
				unset($parents[$idx]);
			}

			$query = ParentMap::find();
			foreach($parents as $parent)
				$query->orWhere($parent);

			$toAdd = array_diff_key($parents, $query->indexBy('parent_id')->asArray()->all());
			if(count($toAdd) >= 1) {
				\Yii::$app->db->createCommand()->batchInsert(ParentMap::tableName(), $attributes, array_map('array_values', $toAdd))->execute();
				$this->trigger(self::AFTER_ADD_PARENT_MAP, new \yii\base\ModelEvent([
					'data' => $toAdd
				]));
			}
		}
		return isset($toAdd) ? $toAdd : false;
	}
}
