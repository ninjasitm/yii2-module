<?php
namespace nitm\traits;

use yii\helpers\Inflector;
use nitm\helpers\ArrayHelper;
use nitm\helpers\Cache as CacheHelper;

/**
 * Traits defined for expanding query scopes until yii2 resolves traits issue
 */
trait Data {
	public $slugIs = [];
	public static $_slugIs = [];

	protected $is;
	protected static $_is;
	protected static $tableName;
	protected static $_flags = [];

	public function title() {
		return $this->getId();
	}

	public function setIs($is)
	{
		if(isset($this))
			$this->is = static::$_is = $is;
		else
			static:: $_is = $is;
	}

	public function isWhat(...$arguments)
	{
		return self::getIsA(...$arguments);
	}

	/*
	 * What does this claim to be?
	 * @param bollean|null $pluralize Should the returnvalue be pluralized or singularized. WHen set to null nothing is done
	 * @param boolean $forceClassType resolution Don't check for type if this is set to type
	 */
	public function getIsA($pluralize=null, $forceClassType=false)
	{
		$slugify = function ($value) {
			$stack = explode('\\', $value);
			return Inflector::slug(implode(' ', preg_split('/(?=[A-Z])/', array_pop($stack), -1, PREG_SPLIT_NO_EMPTY)));
		};

		if(isset($this) && is_null($pluralize) && $this instanceof \nitm\models\Data) {
			if(isset($this->is))
				return $this->is;
			else if(!$forceClassType && $this->hasMethod('type') && !empty($this->type()))
				$ret_val = $this->type();
			else
				$ret_val = $this->className();
			$class = $this->className();
			//Otherwise get the static class value
		} else {
			$class = static::class;
			if(isset($class::$_is) && is_null($pluralize))
				return $class::$_is;
			else if(!$forceClassType && method_exists($class, 'type') && !empty($class::type()))
				$ret_val = $class::type();
			else
				$ret_val = $class;
		}

		if(is_null($pluralize))
			$inflector = 'slug';
		else
			$inflector = $pluralize === true ? 'pluralize' : 'singularize';

		if(isset($this) && $this instanceof \nitm\models\Data) {
			if(!isset($this->slugIs[$inflector]) && isset($class::$_slugIs[$inflector][$ret_val])) {
				$ret_val = $this->slugIs[$inflector] = $class::$_slugIs[$inflector][$ret_val];
			} else if(!isset($this->slugIs[$inflector])) {
				$ret_val = $class::$_slugIs[$inflector][$ret_val] = $this->slugIs[$inflector] = Inflector::$inflector($slugify($ret_val));
			} else
				$ret_val = $this->slugIs[$inflector];

			//If we didn't set the inflector then set the is value to the return value
			if(is_null($pluralize) && !isset($this->is))
				$this->is = $ret_val;
		} else {
			if(!isset($class::$_slugIs[$inflector][$ret_val]))
				$ret_val = $class::$_slugIs[$inflector][$ret_val] = Inflector::$inflector($slugify($ret_val));
			else
				$ret_val = $class::$_slugIs[$inflector][$ret_val];

			//If we didn't set the inflector then set the $_is value to the return value
			if(is_null($pluralize) && !isset($class::$_is))
				$class::$_is = $ret_val;
		}

		return $ret_val;
	}

	/**
	 * Get the unique ID of this object
	 * @return string|int
	 */
	public function getId($splitter='', $key=null)
	{
		$key = $key ?: $this->primaryKey();
		$splitter = $splitter ?: '';
		$id = implode($splitter, array_filter(array_map(function ($attribute) {
			return $this->getAttribute($attribute);
		}, $key)));

		if(is_numeric($id))
			return (int)$id;
		return $id;
	}

	public function hasRelation($name, $model=null)
	{
		$ret_val = null;
		$model = is_null($model) ? $this : $model;
		$method = 'get'.$name;
		try {
			if($model->hasMethod($method) && (new \ReflectionMethod($model, $method))->isPublic()) {
				$ret_val = call_user_func([$model, $method]);
				if(!($ret_val instanceof \yii\db\ActiveQuery))
					$ret_val = null;
			}
		} catch (\Exception $e) {}
		return $ret_val;
	}

	/*
	 * Return a string imploded with ucfirst characters
	 * @param string $name
	 * @return string
	 */
	public function properName($value=null)
	{
		if(isset($this))
			$value = is_null($value) ? $this->isWhat() : $value;
		else
			$value = is_null($value) ?  static::isWhat() : $value;
		return \nitm\helpers\ClassHelper::properName($value);
	}

	/*
	 * Return a string imploded with ucfirst characters
	 * @param string $name
	 * @return string
	 */
	public function properFormName($value=null)
	{
		if(isset($this))
			$value = is_null($value) ? $this->isWhat() : $value;
		else
			$value = is_null($value) ?  static::isWhat() : $value;
		return \nitm\helpers\ClassHelper::properFormName($value);
	}

	/*
	 * Return a string imploded with ucfirst characters
	 * @param string $name
	 * @return string
	 */
	public function properClassName($value=null)
	{
		if(isset($this))
			$value = is_null($value) ? $this->className() : $value;
		else
			$value = is_null($value) ?  static::className() : $value;
		return \nitm\helpers\ClassHelper::properClassName($value, $this->namespace);
	}

	public function getSearchClass()
	{
		$namespace = explode('\\', $this->namespace);
		if(end($namespace) != 'search')
			$namespace[] = 'search';
		return \Yii::$app->getModule('nitm')->getSearchClass($this->isWhat(), implode('\\', $namespace));
	}

	public function getCategoryClass()
	{
		$parts = explode('\\', static::className());
		$modelName = array_shift($parts);
		$class = implode('\\', $parts).'\\'.((strpos($modelName, 'Category') !== false) ? $modelName : $modelName.'Category');
		return class_exists($class) ? $class : \nitm\models\Category::className();
	}

	public function getNamespace()
	{
		if(isset($this))
			$class = $this->className();
		else
			$class = static::className();
		return \nitm\helpers\ClassHelper::getNameSpace($class);
	}

	public function flagId($flag)
	{
		if(isset($this) && $this instanceof \nitm\models\Data)
			return self::getNamespace().'\\'.$this->getId();
		else
			return self::getNamespace().'\\';
	}

	/**
	 * Some support for setting custom flags at runtime
	 */
	public function setFlag($flag, $value)
	{
		self::$_flags[self::flagId($flag)] = $value;
	}

	public function getFlag($flag)
	{
		return ArrayHelper::getValue(self::$_flags, self::flagId($flag), null);
	}

	public static function unsetFlag($flag)
	{
		return ArrayHelper::remove(self::$_flags, self::flagId($flag), null);
	}

	/**
	 * Get the label for use based on
	 * @param $model The model being resolved
	 * @param mixed $label Either a string or array indicating where the label lies
	 * @param mixed $separator the separator being used to clue everything together
	 * @return string
	 */
	protected static function getLabel($model, $label, $separator)
	{
		switch(is_array($label))
		{
			case true:
			$resolvedLabel = '';
			/**
			 * We're supporting multi propertiy/relation properties for labels
			 */
			foreach($label as $idx=>$l)
			{
				$workingItem = $model;
				$properties = explode('.', $l);
				foreach($properties as $prop)
				{
					if(is_object($workingItem) && ($workingItem->hasAttribute($prop) || $workingItem->hasProperty($prop)))
					{
						$workingItem = $workingItem->$prop;
					}
				}
				/**
				 * Support enacpsulating sub values when $separator is sent as a length==2 array
				 */
				switch(is_array($separator) && ($idx == sizeof($label)-1))
				{
					case true:
					$resolvedLabel .= $separator[0].$workingItem.$separator[1];
					break;

					default:
					$resolvedLabel .= $workingItem.(is_array($separator) ? $separator[2] : $separator);
					break;
				}
			}
			$ret_val= $resolvedLabel;
			break;

			default:
			$ret_val = $model->hasAttribute($label) || $model->hasProperty($label) ? $model->$label : $label;
			break;
		}
		return $ret_val;
	}

	/**
	 * Yii DataProvider sort creator
	 * @param array $to     Add the sort options to $to
	 * @param array  $labels THe labels for the sort
	 * @param array $params THe params for the sort
	 */
	public function addSortParams(&$to, array $labels, array $params=[])
	{
		foreach($labels as $attr=>$options)
		{
			@list($relation, $label, $orderAttr) = (array)$options;
			$relation = is_null($relation) ? $attr : $relation;

			if($orderAttr instanceof \yii\db\Expression)
				$relation = serialize(new \yii\db\Expression($relation.'.'.$orderAttr));
			else
				$relation .= is_null($orderAttr) ? '' : '.'.$orderAttr;

			$to[$attr] = array_merge([
				'asc' => [$relation => SORT_ASC],
				'desc' => [$relation => SORT_DESC],
				'default' => SORT_DESC,
				'label' => $label
			], $params);
		}
	}

	/**
	 * Get the query that orders items by their activity
	 */
	public function getSort()
	{
		$ret_val = [];
		//Create the user sort parameters
		static::addSortParams($ret_val, [
			'author_id' => ['author', 'Author', 'username'],
			'editor_id' => ['editor', 'Editor', 'username'],
			'resolved_by' => ['resolvedBy', 'Resolved By', 'username'],
			'closed_by' => ['closedBy', 'Closed By', 'username'],
			'disabled_by' => ['disabledBy', 'Disabled By', 'username'],
			'deleted_by' => ['deletedBy', 'Deleted By', 'username'],
			'completed_by' => ['completedBy', 'Completed By', 'username']
		]);

		//Create the date sort parameters
		static::addSortParams($ret_val, [
			'created_at' => [null, 'Created At'],
			'updated_at' => [null, 'Updated At'],
			'resolved_at' => [null, 'Resolved At'],
			'closed_at' => [null, 'Closed At'],
			'disabled_at' => [null, 'Disabled At'],
			'deleted_at' => [null, 'Deleted At'],
			'completed_at' => [null, 'Completed At']
		]);

		$ret_val['date'] = [
			'asc' => ['created_at' => SORT_ASC, 'updated_at' => SORT_ASC],
			'desc' => ['created_at' => SORT_DESC, 'updated_at' => SORT_DESC],
			'default' => SORT_DESC,
			'label' => 'Date'
		];

		//Create the category sort parameters
		static::addSortParams($ret_val, [
			'type_id' => ['type', 'Type', 'name'],
			'category_id' => ['category', 'Category', 'name'],
			'level_id' => ['level', 'Level', 'name'],
		]);

		return $ret_val;
	}
}
