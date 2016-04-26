<?php
namespace nitm\traits;

use yii\base\Event;
use yii\db\ActiveRecord;
use nitm\helpers\ArrayHelper;

/**
 * Traits defined for expanding active relation scopes until yii2 resolves traits issue
 */

trait RemoteRelations {

	public $_value;
	public $constrain;
	public $constraints = [];
	public $initSearchClass = true;

	public static $usePercentages;
	public static $allowMultiple;
	public static $individualCounts;
	public static $statuses = [
		'normal' => 'default',
		'important' => 'info',
		'critical' => 'error'
	];

	protected $_new;
	protected $_supportedConstraints =  [
		'parent_id' => [0, 'id', 'parent_id'],
		'parent_type' => [1, 'type', 'parent_type'],
	];

	protected static $userLastActive;
	protected static $currentUser;

	private static $_dateFormat = "D M d Y h:iA";

	public function scenarios()
	{
		$scenarios = [
			'count' => ['parent_id', 'parent_type'],
		];
		return array_merge(parent::scenarios(), $scenarios);
	}

	public function fields()
	{
		return array_merge(parent::fields(), ['_value']);
	}

	/**
	 * Get the constraints for a widget model
	 */
	public function getConstraints()
	{
		switch(sizeof($this->constraints) == 0)
		{
			case true:
			foreach($this->_supportedConstraints as $attribute=>$supported)
			{
				if($this->hasProperty($attribute) || $this->hasAttribute($attribute))
				{
					$this->constraints[$attribute] = $this->$attribute;
				}
			}
			$this->queryOptions = $this->constraints;
			break;
		}
		return $this->constraints;
	}

	/*
	 * Set the constrining parameters
	 * @param mixed $using
	 */
	public function setConstraints($using)
	{
		foreach($this->_supportedConstraints as $attribute=>$supported)
		{
			foreach($supported as $attr)
			{
				switch(isset($using[$attr]))
				{
					case true:
					switch($attribute)
					{
						case 'parent_type':
						$value = explode('\\', $using[$attr]);
						$using[$attr] = strtolower(array_pop($value));
						break;
					}
					$this->constraints[$attribute] = $using[$attr];
					$this->$attribute = $using[$attr];
					break;
				}
			}
		}
		$this->queryOptions['andWhere'] = array_replace((array)@$this->queryOptions['andWhere'], $this->constraints);
	}

	/**
	 * Find a model
	 */
	 public static function findModel($constrain)
	 {
		$model = self::initCache($constrain);
		$model->setConstraints($constrain);
		$model->addWith([
			'last' => function ($query) use($model) {
				$query->andWhere($model->queryOptions['andWhere']);
			}
		]);
		$ret_val = $model->find()->where($model->constraints)->one();
		switch(is_a($ret_val, static::className()))
		{
			case true:
			$ret_val->queryOptions = $model->queryOptions;
			$ret_val->constraints = $model->constraints;
			break;

			default:
			$ret_val = $model;
			break;
		}
		return $ret_val;
	 }

	/**
	 * Get the count for the current parameters
	 * @return \yii\db\ActiveQuery
	 */
	 public function getCount($link=null)
	 {
		$primaryKey = $this->primaryKey()[0];
		$ret_val = parent::getCount($link ?: $this->link);
		switch(isset($this->queryOptions['ahdWhere']['value']))
		{
			case true:
			switch($this->queryOptions['andWhere']['value'])
			{
				case -1:
				$andWhere = ['<=', 'value',  0];
				break;

				case 1:
				$andWhere = ['>=', 'value', 1];
				break;
			}
			unset($this->queryOptions['andWhere']['value']);
			$ret_val->andWhere($andWhere);
			break;
		}
		$ret_val->select(array_merge($this->link, $ret_val->select));
		return $ret_val;
	 }

    /**
	 * This is here to allow base classes to modify the query before finding the count
     * @return \yii\db\ActiveQuery
     */
    public function getFetchedValue()
    {
		$primaryKey = $this->primaryKey()[0];
		$ret_val = $this->hasOne(static::className(), $this->link);
		$valueFilter = @$this->queryOptions['value'];
		unset($this->queryOptions['andWhere']['value']);
		switch(static::$allowMultiple)
		{
			case true:
			$select = [
				"_down" => "SUM(IF(value<=0, value, 0))",
				"_up" => "SUM(IF(value>=1, value, 0))"
			];
			break;

			default:
			$select = [
				'_down' => "SUM(value=-1)",
				"_up" => "SUM(value=1)"
			];
			break;
		}
		$filters = $this->queryOptions['andWhere'];
		unset($filters['parent_id'], $filters['parent_type']);
		return $ret_val->select(array_merge($this->link, $select))
			->groupBy(array_keys($this->link))
			->andWhere($filters);
    }

	public function fetchedValue($key=null)
	{
		$ret_val = \nitm\helpers\Relations::getRelatedRecord($this, 'fetchedValue', static::className(), [
			'_value' => 0,
			'_up' => 0,
			'_down' => 0
		]);

		return ArrayHelper::getValue(ArrayHelper::toArray($ret_val), $key, $ret_val);
	}

	public function hasNew()
	{
		return \nitm\helpers\Relations::getRelatedRecord($this, 'newCount', static::className(), [
			'_new' => 0
		])['_new'];
	}

	public function getNewCount()
	{
		$primaryKey = $this->primaryKey()[0];
		$ret_val = $this->hasOne(static::className(), $this->link);
		$andWhere = ['or', "created_at>='".static::currentUser()->lastActive()."'"];
		$ret_val->select(array_merge($this->link, [
				'_new' => 'COUNT('.$primaryKey.')'
			]))
			->groupBy(array_keys($this->link))
			->andWhere($andWhere)
			->asArray();
		static::currentUser()->updateActivity();
		return $ret_val;
	}

	/*
	 * Get the author for this object
	 * @return mixed user array
	 */
	public function isNew()
	{
		static::$userLastActive = is_null(static::$userLastActive) ? static::currentUser()->lastActive() : static::$userLastActive;
		return strtotime($this->created_at) > strtotime(static::$userLastActive);
	}

	/*
	 * Get the author for this object
	 * @return boolean
	 */
	public function hasAny()
	{
		return $this->count() >= 1;
	}


	/*
	 * Get the author for this object
	 * @return mixed user array
	 */
	public function getLast()
	{
		$ret_val = $this->hasOne(static::className(), $this->link)
			->orderBy([array_shift($this->primaryKey()) => SORT_DESC])
			->groupBy(array_keys($this->link))
			->with('author');
		return $ret_val;
	}

	public function currentUser()
	{
		if(isset(static::$currentUser))
			return static::$currentUser;

		if(\Yii::$app instanceof \yii\console\Application)
			static::$currentUser = new \nitm\models\User(['username' => 'console']);
		else if(\Yii::$app->getUser() && \Yii::$app->getUser()->getIsGuest()) {
			static::$currentUser = \nitm\helpers\Cache::getModel($this,
				'currentUser',
				false,
				\Yii::$app->getUser()->identityClass,
				null, [
					'id' => 1
				]);
		}
		else {
			static::$currentUser = \Yii::$app->getUser()->getIdentity();
		}
		return static::$currentUser;
	}

	protected function updateCurrentUserActivity()
	{
		if(is_object(static::currentUser()))
			static::$userLastActive = date('Y-m-d G:i:s', strtotime(is_null(static::$userLastActive) ? static::currentUser()->lastActive() : static::$userLastActive));
	}

	protected function populateMetadata()
	{
		switch(!isset($this->count) && !isset($this->hasNew))
		{
			case true:
			$sql = static::find()->select([
				"_count" => 'COUNT(id)',
				"_hasNew" => 'SUM(IF(created_at>='.static::currentUser()->lastActive().", 1, 0))"
			])
			->where($this->getConstraints());
			$metadata = $sql->createCommand()->queryAll();
			static::currentUser()->updateActivity();
			break;
		}
	}

	protected static function initCache($constrain, $key=null)
	{
		if(!\nitm\helpers\Cache::exists($key))
		{
			$class = static::className();
			$model = new $class(['initSearchClass' => false]);
			$model->setConstraints($constrain);
			$key = is_null($key) ? \nitm\helpers\Cache::cacheKey($model, ['parent_id', 'parent_type']) : array_keys($constrain);
			\nitm\helpers\Cache::setModel($key, [$model->className(), \yii\helpers\ArrayHelper::toArray($model)]);
		}
		else {
			$array = \nitm\helpers\Cache::getModel($key);
			$model = new $array[0]($array[1]);
		}
		return $model;
	}

	public function title()
	{
		if($this->hasProperty('title') || $this->hasAttribute('title'))
			$ret_val = $this->title;
		else if($this->hasProperty('name') || $this->hasAttribute('name'))
			$ret_val = $this->name;
		else if($this->hasProperty('parent_type') || $this->hasAttribute('parent_type'))
			$ret_val = \nitm\helpers\ClassHelper::properName($this->parent_type).' ('.$this->parent_id.')';
		else if($this->hasProperty('remote_type') || $this->hasAttribute('remote_type'))
			$ret_val = \nitm\helpers\ClassHelper::properName($this->remote_type).' ('.$this->remote_id.')';
		else
			$ret_val = $this->getId();
		return $ret_val;
	}
}
?>
