<?php

namespace nitm\traits;

use Yii;
use yii\base\Model;
use yii\base\Event;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use nitm\models\User;
use nitm\widgets\models\Category;
use nitm\models\ParentMap;
use nitm\helpers\Cache as CacheHelper;

/**
 * Class Replies
 * @package nitm\module\models
 */

trait Nitm
{
	public function url($attribute='id', $text=null, $url=null, $options=[])
	{
		if(is_array($text)){
			$object = is_object(($text[0])) ? array_shift($text) : $this;
			$property = empty($text) ? [$attribute] : (array)$text;
			$text = \nitm\helpers\Helper::concatAttributes($object, $property, ' ');
		}
		else {
			$text = is_null($text) ? $attribute : $text;
			$text = $this->hasAttribute($text) ? ArrayHelper::getValue($this, $text, $text) : $text;
		}

		$url = is_null($url) ? \Yii::$app->request->url : $url;
		$urlOptions = array_merge(\Yii::$app->request->queryParams, [
			$this->formName()."[".$attribute."]" => $this->getAttribute($attribute)
		]);
		array_unshift($urlOptions, $url);
		$htmlOptions = array_merge([
			'href' => \Yii::$app->urlManager->createUrl($urlOptions),
			'role' => $this->formName().'Link',
			'id' => $this->isWhat().'-link-'.uniqid(),
			'data-pjax' => 1
		], $options);
		return Html::tag('a', $text, $htmlOptions);
	}

	public function nitmScenarios()
	{
		return [
			'disable' => ['disabled', 'disabled_at', 'disabled'],
			'complete' => ['completed', 'completed_at', 'closed'],
			'close' => ['closed', 'closed_at', 'completed', 'resolved'],
			'resolve' => ['resolved', 'resolved_at', 'closed']
		];
	}

	public function getStatuses()
	{
		return [
			'Normal',
			'Important',
			'Critical'
		];
	}

	public function getLevelCssClass()
	{
		$ret_val = 'default';
		switch(1)
		{
			case $this->level()->slug == 'visibility-private':
			$ret_val = 'error';
			break;

			case $this->level()->slug == 'visibility-admin':
			$ret_val = 'info';
			break;

			default:
			$ret_val = 'success';
			break;
		}
		return $ret_val;
	}


	public function getStatusTag($text = null, $label = null)
	{
		$label = is_null($label) ? $this->getStatus() : $label;
		$text = is_null($text) ? $this->getStatusName() : $text;
		return Html::tag('span', $text, ['class' => 'label label-'.$label]);
	}

	public function getStatus()
	{
		$ret_val = 'default';
		switch(1)
		{
			case $this->hasAttribute('duplicate') && $this->duplicate:
			$ret_val = 'duplicate'; //need to add duplicate css class
			break;

			case $this->hasAttribute('closed') && $this->hasAttribute('resolved'):
			switch(1)
			{
				case $this->closed && $this->resolved:
				$ret_val = 'success';
				break;

				case $this->closed && !$this->resolved:
				$ret_val = 'warning';
				break;

				case !$this->closed && $this->resolved:
				$ret_val = 'info';
				break;

				default:
				$ret_val = 'danger';
				break;
			}
			break;

			case $this->hasAttribute('closed') && $this->hasAttribute('completed'):
			switch(1)
			{
				case $this->closed && $this->completed:
				$ret_val = 'success';
				break;

				case $this->closed && !$this->completed:
				$ret_val = 'warning';
				break;

				case !$this->closed && $this->completed:
				$ret_val = 'info';
				break;

				default:
				$ret_val = 'danger';
				break;
			}
			break;

			case $this->hasAttribute('disabled'):
			switch(1)
			{
				case $this->disabled:
				$ret_val = 'disabled';
				break;

				default:
				$ret_val = 'success';
				break;
			}
			break;

			case isset(self::$statuses):
			$ret_val = isset(self::$statuses[$this->getAttribute('status')]) ? self::$statuses[$this->getAttribute('status')] : 'default';
			break;

			default:
			$ret_val = 'default';
			break;
		}
		return $ret_val;
	}

	public function getStatusName()
	{
		$ret_val = 'status';
		switch(1)
		{
			case $this->hasAttribute($attribute = 'status'):
			case $this->hasAttribute($attribute = 'level'):
			$ret_val = $this->$attribute;
			break;

			case $this->hasAttribute('duplicate') && $this->duplicate:
			$ret_val = 'duplicate'; //need to add duplicate css class
			break;

			case $this->hasAttribute('closed') && $this->hasAttribute('resolved'):
			switch(1)
			{
				case $this->closed && $this->resolved:
				$ret_val = 'closed resolved';
				break;

				case $this->closed && !$this->resolved:
				$ret_val = 'closed un-resolved';
				break;

				case !$this->closed && $this->resolved:
				$ret_val = 'open resolved';
				break;

				default:
				$ret_val = 'open un-resolved';
				break;
			}
			break;

			case $this->hasAttribute('closed') && $this->hasAttribute('completed'):
			switch(1)
			{
				case $this->closed && $this->completed:
				$ret_val = 'closed completed';
				break;

				case $this->closed && !$this->completed:
				$ret_val = 'closed incomplete';
				break;

				case !$this->closed && $this->completed:
				$ret_val = 'open completed';
				break;

				default:
				$ret_val = 'open incomplete';
				break;
			}
			break;

			case $this->hasAttribute('disabled'):
			switch(1)
			{
				case $this->disabled:
				$ret_val = 'disabled';
				break;

				default:
				$ret_val = 'enabled';
				break;
			}
			break;
		}
		return $ret_val;
	}

    /**
	 * Get Categories
     * @return array
     */
    public static function getCategories($type)
    {
		return Category::find()->where([
			'parent_ids' => (new \yii\db\Query)->
				select('id')->
				from(Category::tableName())->
				where(['slug' => $type])
		])->orderBy(['name' => SORT_ASC])->all();
	}

    /**
	 * Get types for use in an HTML element
     * @return array
     */
    public static function getCategoryList($type)
    {
		$key = 'cl'.ucfirst($type);
		switch(CacheHelper::cache()->exists($key))
		{
			case false:
			$model = new Category([
				'queryOptions' => [
					'select' => ['name', 'id'],
					'where' => 'id IN ('.new \yii\db\Expression(ParentMap::find()
						->select('remote_id')
						->where([
							'parent_id' => new \yii\db\Expression('('.ParentMap::find()
								->select('remote_id')
								->where([
									'remote_type' => $type,
									'remote_table' => Category::tableName(),
								])
								->createCommand()->getRawSql().')'),
							'parent_table' => Category::tableName(),
						])
						->createCommand()->getRawSql().')'),
					'orderBy' => ['name' => SORT_ASC]
				]
			]);
			$ret_val = $model->getList('name', null, [], $key);
			CacheHelper::cache()->set($key, $ret_val, 600);
			break;

			default:
			$ret_val = CacheHelper::cache()->get($key);
			break;
		}
		asort($ret_val);
		return $ret_val;
    }

	/*
	 * Return a string imploded with ucfirst characters
	 * @param string $name
	 * @return string
	 */
	protected static function resolveModelClass($value)
	{
		$ret_val = empty($value) ?  [] : array_map('ucfirst', preg_split("/[_-]/", $value));
		return implode($ret_val);
	}
}
?>
