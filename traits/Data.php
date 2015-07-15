<?php
namespace nitm\traits;

use nitm\helpers\ArrayHelper;
use nitm\helpers\Cache as CacheHelper;

/**
 * Traits defined for expanding query scopes until yii2 resolves traits issue
 */
trait Data {
	
	public $noDbInit = false;
	public $queryOptions = [];
	
	protected $_count;
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
			$this->is = $is;
		else
			static:: $_is = $is;
	}
	
	/*
	 * What does this claim to be?
	 */
	public function isWhat()
	{
		$purify = function ($value) {
			$stack = explode('\\', $value);
			return strtolower(implode('-', preg_split('/(?=[A-Z])/', array_pop($stack), -1, PREG_SPLIT_NO_EMPTY)));
		};
		if(isset($this)){
			$class = isset($this->is) ? $this->is : $this->className();
			//If it's a model then get the instantiated $is value
			return $purify($class);
		} else {
			//Otherwise get the static class value
			$class = get_called_class();
			if(!isset($class::$_is))
				$class::$_is = $purify($class);
			return $class::$_is;
		}
	}
	
	/**
	 * Get the unique ID of this object
	 * @return string|int
	 */
	public function getId()
	{
		$key = $this->primaryKey();
		return (int)(string)$this->$key[0];
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
	public function properClassName($value=null)
	{
		if(isset($this))
			$value = is_null($value) ? $this->className() : $value;
		else
			$value = is_null($value) ?  static::className() : $value;
		return \nitm\helpers\ClassHelper::properClassName($value);
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
	
	public function addWith($with)
	{
		$with = is_array($with) ? $with : [$with];
		$this->queryOptions['with'] = array_merge(ArrayHelper::getValue($this->queryOptions, 'with', []), $with);
	}

    /**
	 * This is here to allow base classes to modify the query before finding the count
     * @return \yii\db\ActiveQuery
     */
    public function getCount($link=null)
    {
		$primaryKey = current($this->primaryKey());
		$link = is_array($link) ? $link : [$primaryKey => $primaryKey];
		$tableName = static::tableName();
		$tableNameAlias = $tableName.'_alias';
        $query = $this->hasOne(static::className(), $link)
			->select([
				'_count' => "COUNT(".$primaryKey.")",
			]);
		foreach(['where', 'orwhere', 'andwhere'] as $option)
			if(isset($this->queryOptions[$option]))
				$query->$option($this->queryOptions[$option]);
		return $query;
    }
	
	public function count()
	{
		return \nitm\helpers\Relations::getRelatedRecord('count', $this, static::className(), [
			'_count' => 0
		])->_count;
	}	

	/*
	 * Get the array of arrays
	 * @return mixed
	 */
	public function getArrays()
	{
		$query = $this->find($this);
		$ret_val = $query->asArray()->all();
		$this->success = (sizeof($ret_val) >= 1) ? true : false;
		return $ret_val;
	}
	
	private function locateClassForItems($options)
	{
		if(isset($this) && get_class($this) == static::className())
			return static::className();
		else
			return ArrayHelper::remove($options, 'class', __CLASS__);
	}
	
	/**
	 * Function to get items for the List methods
	 * @return array
	 */
	private function locateItems($options)
	{
		$class = $this->locateClassForItems($options);
			
		$items = [];
		$this->queryOptions = array_merge($this->queryOptions, array_merge([
				'limit' => 100,
				'select' => '*',
			], $options));
		if(isset($this)) {
			$items = $this->getModels();
		}
		else {
			$query = $class::find();
			foreach($this->queryOptions as $name=>$value)
				if($query->hasMethod($name))
					$query->$name($value);
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
		$class = $this->locateClassForItems($queryOptions);
		
		$ret_val = [];
		$separator = is_null($separator) ? ' ' : $separator;
		$label = is_null($label) ? 'name' : $label;
		
		$cacheKey = CacheHelper::getKey(is_string($key) ? $key : $class::formName(), null, 'list', true);
		
		if(CacheHelper::cache()->exists($cacheKey))
			$ret_val = CacheHelper::cache()->get($cacheKey);
		else {
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
	public function getJsonList($labelField='name', $separator=null, $options=[], $key=null)
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
					"text" =>  $item->$labelField, 
					"label" => static::getLabel($item, $label, $separator)
				];
				if(isset($options['with']))
				{
					foreach($options['with'] as $attribute)
					{
						switch($attribute)
						{
							case 'htmlView':
							$view = isset($options['view']['file']) ? $options['view']['file'] : "/".$item->isWhat()."/view";
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

	/*
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

	/*
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
	 * Get the parent list
	 * @param boolean $url Get the url?
	 * @return string
	 */	
	public function getParentList($url=true, $titleAttr='name')
	{
		return \nitm\helpers\Helper::concatAttributes($this->parents(), function ($model) use($url, $titleAttr){
			return \yii\helpers\Html::tag('strong', ($url ? $model->url('id', $titleAttr) : $model->$titleAttr));
		}, ', ', true);
	}
}