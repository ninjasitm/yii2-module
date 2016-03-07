<?php
namespace nitm\traits;

use nitm\helpers\ArrayHelper;
use nitm\helpers\Cache as CacheHelper;

/**
 * Traits defined for expanding query scopes until yii2 resolves traits issue
 */
trait Query {

	public $queryOptions = [];

	protected $success;

	/**
	 * Sets the successfull parameter for query
	 */
	public function successful()
	{
		return $this->success === true;
	}

	/**
	 * Overriding default find function
	 */
	public static function find($model=null, $options=null)
	{
		$query = parent::find($options);
		$query->from = [$model instanceof self ? $model->tableName() : static::tableName()];
		if($model instanceof self) {
			$model->aliasColumns($query);
			foreach($model->queryOptions as $filter=>$value)
			{
				switch(strtolower($filter))
				{
					case 'select':
					case 'indexby':
					case 'orderby':
					if(is_string($value) && ($value == 'primaryKey'))
					{
						unset($model->queryOptions[$filter]);
						$query->$filter(static::primaryKey()[0]);
					}
					break;
				}
			}
			static::applyFilters($query, $model->queryOptions);
		} else {
			static::aliasColumns($query);
		}
		return $query;
	}

	/**
	 * Get the array of arrays
	 * @return array
	 */
	public function getArrays()
	{
		$query = $this->find($this);
		$ret_val = $query->asArray()->all();
		$this->success = (sizeof($ret_val) >= 1) ? true : false;
		return $ret_val;
	}

	/**
	 * Get array of objects
	 * @return mixed
	 */
	public function getModels()
	{
		$query = $this->find($this);
		$ret_val = $query->all();
		$this->success = (sizeof($ret_val) >= 1) ? true : false;
		return $ret_val;
	}

	/**
	 * Get a single record
	 */
	public function getOne()
	{
		$query = $this->find($this);
		$ret_val = $query->one();
		$this->success = (!is_null($ret_val)) ? true : false;
		return $ret_val;
	}

	/**
	 * Function to get class name for locating items
	 * @return string
	 */
	private function locateClassForItems($options)
	{
		return ArrayHelper::remove($options, 'class', (is_subclass_of(static::className(), __CLASS__) ? static::className() : __CLASS__));
	}

	/**
	 * Function to get items for the List methods
	 * @return array
	 */
	private function locateItems($options, $count=false)
	{
		$class = self::locateClassForItems($options);

		$items = [];
		if(isset($this) && is_subclass_of(static::className(), __CLASS__)) {
			$this->queryOptions = array_merge($this->queryOptions, array_merge([
				'limit' => 100,
				'select' => '*',
			], $options));
			if($count === true) {
				$items = $this->find($this)->count();
			}
			else
				$items = $this->getModels();
		}
		else {
			$query = $class::find();
			foreach($this->queryOptions as $name=>$value)
				if($query->hasMethod($name))
					$query->$name($value);
			if($count === true)
				$items = $query->count();
			else
				$items = $query->all();
		}
		return $items;
	}

	/**
	 * Get a one dimensional associative array
	 * @param mixed $label
	 * @param mixed $separator
	 * @return array
	 */
	public function getList($label='name', $separator=null, $queryOptions=[], $key=null)
	{
		$class = self::locateClassForItems($queryOptions);
		$ret_val = ['' => 'Select...'];
		$separator = is_null($separator) ? ' ' : $separator;
		$label = is_null($label) ? 'name' : $label;

		$cacheKey = CacheHelper::getKey($this, null, 'list', true);

		if(CacheHelper::cache()->exists($cacheKey))
			$ret_val = CacheHelper::cache()->get($cacheKey);

		if(!isset($queryOptions['orderBy']))
			$queryOptions['orderBy'] = [(is_array($label) ? end($label) : $label) => SORT_ASC];

		$queryOptions['groupBy'] = [$label, 'id'];
		if(count($ret_val) < self::locateItems($queryOptions, true)) {
			$items = self::locateItems($queryOptions);
			switch(count($items) >= 1)
			{
				case true:
				foreach($items as $item)
				{
					$ret_val[$item['id']] = $class::getLabel($item, $label, $separator);
				}
				break;

				default:
				$ret_val[] = ["No ".$class::isWhat()." found"];
				break;
			}
			CacheHelper::cache()->set($cacheKey, $ret_val, 120);
		}
		return $ret_val;
	}

	/**
	 * Get a multi dimensional associative array suitable for Json return values
	 * @param mixed $label
	 * @param mixed $separator
	 * @return array
	 */
	public function getJsonList($label='name', $separator=null, $options=[], $key=null)
	{
		$class = $this->locateClassForItems($options);

		$cacheKey = CacheHelper::getKey(is_string($key) ? $key : $class::formName(), null, 'list', true);

		if(CacheHelper::cache()->exists($cacheKey))
			$ret_val = CacheHelper::cache()->get($cacheKey);
		else {
			$ret_val = [];
			$separator = is_null($separator) ? ' ' : $separator;

			foreach(self::locateItems($options) as $item)
			{
				$_ = [
					"id" => $item->getId(),
					"value" => $item->getId(),
					"text" =>  $item->$label,
					"label" => static::getLabel($item, $label, $separator)
				];
				if(isset($options['with']))
				{
					foreach($options['with'] as $attribute)
					{
						switch($attribute)
						{
							case 'htmlView':
							$view = \Yii::$app->getViewPath();
							if(!isset($options['view']['file'])) {
								if(file_exists(\Yii::getAlias($path.$item->isWhat(true).'/view.php')))
									$view = "/".$item->isWhat(true)."/view";
								else if(file_exists(\Yii::getAlias($path.$item->isWhat().'/view.php')))
									$view = "/".$item->isWhat()."/view";
							}
							$viewOptions = isset($options['view']['options']) ? $options['view']['options'] : ["model" => $item];
							$_['html'] = \Yii::$app->getView()->renderAjax($view, $viewOptions);
							break;

							case 'icon':
							/*$_['label'] = \lab1\widgets\Thumbnail::widget([
								"model" => $item->getIcon()->one(),
								"htmlIcon" => $item->html_icon,
								"size" => "tiny",
								"options" => [
									"class" => "thumbnail text-center",
								]
							]).$_['label'];*/
							break;
						}
					}
				}
				$ret_val[] = $_;
			}
			CacheHelper::cache()->set($cacheKey, $ret_val, 120);
		}
		return (sizeof(array_filter($ret_val)) >= 1) ? $ret_val : [['id' => 0, 'text' => "No ".$this->properName($this->isWhat())." Found"]];
	}

	public function addWith($with)
	{
		$with = is_array($with) ? $with : [$with];
		$this->queryOptions['with'] = array_merge(ArrayHelper::getValue($this->queryOptions, 'with', []), $with);
	}

	public static function filters()
	{
		return [
				'author' => null,
				'editor' => null,
				'status' => null,
				'order' => null,
				'order_by' => null,
				'index_by' => null,
				'show' => null,
				'limit' => null,
				'unique' => null,
				'boolean' => null,
		];
	}

	public static function has()
	{
		return [
			'created_at' => null,
			'updated_at' => null,
		];
	}

	/*
	 * Apply the filters specified by the end user
     * @param ActiveQuery $query
	 * @param mixed $filters
	 */
	public static function applyFilters($query, $filters=null)
	{
		//search for special filters
		switch(true)
		{
			case $query instanceof \yii\mongo\ActivQuery:
			case $query instanceof \yii\elasticsearch\ActivQuery:
			$target = null;
			break;

			default:
			$target = isset($query->primaryModel) ? $query->primaryModel : $query instanceof \yii\db\ActiveQuery  ?  $query->from[0] : null;
			break;
		}

		switch(is_array($filters))
		{
			case true:
			foreach($filters as $name=>$value)
			{
				switch(static::hasFilter($name))
				{
					case true:
					switch(strtolower($name))
					{
						case 'select':
						\nitm\helpers\QueryFilter::aliasSelectFields($value, $target);
						break;

						case 'orderby':
						\nitm\helpers\QueryFilter::aliasOrderByFields($value, $target);
						if(isset($filters['order']))
							unset($filters['order']);
						break;

						case 'groupby':
						\nitm\helpers\QueryFilter::aliasOrderByFields($value, $target);
						break;

						case 'where':
						\nitm\helpers\QueryFilter::aliasWhereFields($value, $target);
						break;
					}
					$filters[$name] = $value;
					break;
				}
			}

			foreach($filters as $type=>$args)
			{
				try {
					$query->$type($args);
					unset($filters[$type]);
				} catch (\Exception $e) {}
			}

			if(is_array($filters) && (sizeof($filters) >= 1)) {
				\nitm\helpers\QueryFilter::aliasWhereFields($filters, $target);
				$query->andWhere($filters);
			}
			break;
		}
		return $query;
	}

	/*
	 * Some common filters
	 * @param $name The name of the filter
	 * @param $default Should a default value be appended?
	 * @return mixed $filter
	 */
	public function getFilter($name=null, $default=true)
	{
		$ret_val = null;
		switch(static::hasFilter($name))
		{
			case true:
			$filters = static::filters();
			switch(is_null($filters[$name]))
			{
				case false;
				switch(is_array($filters[$name]))
				{
					case true:
					switch(sizeof($filters[$name]))
					{
						case 3:
						$class = @$filters[$name][0];
						$method = @$filters[$name][1];
						$args = @$filters[$name][2];
						break;

						case 2:
						$class = get_class($this);
						$method = @$filters[$name][0];
						$args = @$filters[$name][1];
						break;
					}
				}
				$r = new ReflectionClass($class);
				switch($r->hasMethod($method))
				{
					case false:
					throw new \base\ErrorException("The method: $method does not exist in class: $class");
					return false;
					break;
				}
				break;

				default:
				$class = null;
				break;
			}
			switch($name)
			{
				case 'author':
				case 'editor':
				switch($class == null)
				{
					case true:
					$class = \Yii::$app->getModule('nitm')->getSearchClass('user');
					$o = new $class;
					$o->addWith('profile');
					$filters = $o->getList(['profile.name', 'username'], ['(', ')', ' ']);
					break;

					default:
					$filters = call_user_func_array(array($class, $method), $args);
					break;
				}
				$ret_val = $filters;
				break;

				case 'status':
				$ret_val = ['0' => 'Disabled', '1' => 'Enabled'];
				$ret_val = ($default === true) ? array_merge(['' => 'Any'], $ret_val) : $ret_val;
				break;

				case 'boolean':
				$ret_val = ['0' => 'No', '1' => 'Yes'];
				break;

				case 'rating':
				break;

				case 'order':
				$ret_val = ['desc' => 'Descending', 'asc' => 'Ascending'];
				break;

				case 'order_by':
				foreach($this->getTableSchema()->columns as $colName=>$info)
				{
					switch($info->type)
					{
						case 'text':
						case 'binary':
						break;

						default:
						if($info->isPrimaryKey) {
							$primaryKey = [$colName => $this->properName($colName)];
							continue;
						}
						$ret_val[$colName] = $this->properName($colName);
						break;
					}
				}

				foreach($this->getSort() as $attr=>$options)
				{
					@list($relation, $label, $orderAttr) = (array)$options;
					if($this->hasMethod('get'.$relation))
						$ret_val[$attr] = $options['label'];
				}

				ksort($ret_val);

				if(isset($primaryKey)) {
					$ret_val = array_reverse($ret_val, true);
					$ret_val[key($primaryKey)] = current($primaryKey);
					$ret_val = array_reverse($ret_val, true);
				}
				break;

				default:
				switch($class == null)
				{
					case true:
					$filters = isset(static::$settings[static::isWhat()]['filter'][$name]) ? static::$settings[static::isWhat()]['filter'][$name] : [];
					break;

					default:
					$filters = call_user_func_array(array($class, $method), $args);
					break;
				}
				$ret_val = ($default === true) ? array_merge(['' => 'Select '.$this->properName($name)], $filters) : $filters;
				break;
			}
			break;
		}
		return $ret_val;
	}

	/*
	 * Does this object support this filter?
	 * @param string|int #name
	 * @return boolean
	 */
	public static function hasFilter($name)
	{
		return array_key_exists($name, static::filters());
	}

	/*
	 * Set the aliased fields according to the class columns() function
     * @param ActiveQuery $query
	 */
	public function aliasColumns($query)
	{
		$class = isset($this) ? $this->className() :  static::className();
		$ret_val = [];
		$has = is_array($class::has()) ? $class::has() : null;
		switch(is_null($has))
		{
			case false:
			foreach($has as $property=>$value)
			{
				$special = explode(':', $property);
				switch(sizeof($special))
				{
					case 2:
					$property = $special[1];
					$column = $special[0];
					break;

					case 1:
					$property = $special[0];
					$column = $property;
					break;

					default:
					$column = $property;
					break;
				}
			}
			break;
		}
		if(count($ret_val))
			$query->select(array_merge($ret_val, array_keys($class::getTableSchema()->columns)));
		else
			$query->select('*');

		\nitm\helpers\QueryFilter::aliasFields($query, $class::tableName());
		return $query;
	}
}
?>
