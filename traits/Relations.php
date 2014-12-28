<?php
namespace nitm\traits;

use nitm\helpers\Cache;

/**
 * Traits defined for expanding active relation scopes until yii2 resolves traits issue
 */

trait Relations {
	
	/**
	 * User based relations
	 */
	
	protected function getUserRelationQuery($link, $options=[], $className=null)
	{
		if(is_null($className))
		{
			if(\Yii::$app->hasProperty('user'))
				$className = \Yii::$app->user->identityClass;
			else
				$className = \nitm\models\User::className();
		}
		$options['select'] = isset($options['select']) ? $options['select'] : ['id', 'username', 'disabled'];
		$options['with'] = isset($options['with']) ? $options['select'] : ['profile'];
		$options['where'] = [];
		return $this->getRelationQuery($className, $link, $options);
	}
	
	protected function getCachedUserModel($idKey, $className=null)
	{
		$className = is_null($className) ? \Yii::$app->user->identityClass : \nitm\models\User::className();
		return $this->getCachedRelation(Cache::cacheKey($this, $idKey, 'user'), $className, [], false, \nitm\helpers\Helper::getCallerName());
	}
	 
    /**
	 * Get user relation
	 * @param array $options Options for the relation
     * @return \yii\db\ActiveQuery
     */
    public function getUser($options=[])
    {
		return $this->getUserRelationQuery(['id' => 'user_id'], $options);
    }
	
	public function user()
	{
		return $this->getCachedUserModel('user_id');
	}

    /**
	 * Get user relation
	 * @param array $options Options for the relation
     * @return \yii\db\ActiveQuery
     */
    public function getAuthor($options=[])
    {
		return $this->getUserRelationQuery(['id' => 'author_id'], $options);
    }
	
	public function author()
	{
		return $this->getCachedUserModel('author_id');
	}

    /**
	 * Get user relation
	 * @param array $options Options for the relation
     * @return \yii\db\ActiveQuery
     */
    public function getEditor($options=[])
    {
		return $this->getUserRelationQuery(['id' => 'editor_id'], $options);
    }
	
	public function editor()
	{
		return $this->getCachedUserModel('editor_id');
	}

    /**
	 * Get user relation
	 * @param array $options Options for the relation
     * @return \yii\db\ActiveQuery
     */
    public function getCompletedBy($options=[])
    {
		return $this->getUserRelationQuery(['id' => 'completed_by'], $options);
    }
	
	public function completedBy()
	{
		return $this->getCachedUserModel('completed_by');
	}

    /**
	 * Get user relation
	 * @param array $options Options for the relation
     * @return \yii\db\ActiveQuery
     */
    public function getResolvedBy($options=[])
    {
		return $this->getUserRelationQuery(['id' => 'resolved_by'], $options);
    }
	
	public function resolvedBy()
	{
		return $this->getCachedUserModel('resolved_by');
	}

    /**
	 * Get user relation
	 * @param array $options Options for the relation
     * @return \yii\db\ActiveQuery
     */
    public function getClosedBy($options=[])
    {
		return $this->getUserRelationQuery(['id' => 'closed_by'], $options);
    }
	
	public function closedBy()
	{
		return $this->getCachedUserModel('closed_by');
	}

    /**
	 * Get user relation
	 * @param array $options Options for the relation
     * @return \yii\db\ActiveQuery
     */
    public function getDisabledBy($options=[])
    {
		return $this->getUserRelationQuery(['id' => 'disabled_by'], $options);
    }
	
	public function disabledBy()
	{
		return $this->getCachedUserModel('disabled_by');
	}

    /**
	 * Get user relation
	 * @param array $options Options for the relation
     * @return \yii\db\ActiveQuery
     */
    public function getDeletedBy($options=[])
    {
		return $this->getUserRelationQuery(['id' => 'deleted_by'], $options);
    }
	
	public function deletedBy()
	{
		return $this->getCachedUserModel('deleted_by');
	}
	
	/**
	 * Category based relations
	 */
	
	protected function getCategoryRelation($link, $options=[], $className=null, $many=false)
	{
		$className = is_null($className) ? \nitm\models\Category::className() : $className;
		$options['select'] = isset($options['select']) ? $options['select'] : ['id', 'parent_ids', 'name', 'slug'];
		$options['with'] = isset($options['with']) ? $options['select'] : [];
		return $this->getRelationQuery($className, $link, $options, $many);
	}	
	
	protected function getCachedCategoryModel($idKey, $className=null, $relation=null, $many=false)
	{
		$className = is_null($className) ? \nitm\models\Category::className() : $className;
		$relation = is_null($relation) ? \nitm\helpers\Helper::getCallerName() : $relation;
		return $this->getCachedRelation(Cache::cacheKey($this, $idKey, 'category', $many), $className, [], $many, $relation);
	}

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getParent()
    {
		$options['where'] = !isset($options['where']) ? [] : $options['where'];
		return $this->getCategoryRelation(['id' => 'parent_ids'], $options);
    }
	
	public function parent()
	{
		return $this->getCachedCategoryModel('parent_ids');
	}

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getParents()
    {
		return $this->getCategoryRelation(['id' => 'parent_ids'], $options, null, true);
    }
	
	public function parents()
	{
		return $this->getCachedCategoryModel('parent_ids');
	}

    /**
	 * Get type relation
	 * @param array $options Options for the relation
     * @return \yii\db\ActiveQuery
     */
    public function getType($options=[])
    {
		$options['where'] = !isset($options['where']) ? [] : $options['where'];
		return $this->getCategoryRelation(['id' => 'type_id'], $options);
    }
	/**
	 * Changed becuase of clash with \yii\elasticsearch\ActiveRecord::type()
	 */
	public function typeOf()
	{
		return $this->getCachedCategoryModel('type_id', null, 'type');
	}

    /**
	 * Get category relation
	 * @param array $options Options for the relation
     * @return \yii\db\ActiveQuery
     */
    public function getCategory($options=[])
    {
		$options['where'] = !isset($options['where']) ? [] : $options['where'];
		return $this->getCategoryRelation(['id' => 'category_id'], $options);
    }
	
	public function category()
	{
		return $this->getCachedCategoryModel('category_id');
	}
	
	public static function getRelationClass($relationClass, $callingClass)
	{
		$parts = explode('\\', $relationClass);
		$baseName = array_pop($parts);
		if(\nitm\search\traits\SearchTrait::useSearchClass($callingClass) !== false)
			$parts[] = 'search';
		$parts[] = $baseName;
		return implode('\\', $parts);
	}
	
	protected function getRelationQuery($className, $link, $options=[], $many=false)
	{
		$className = $this->getRelationClass($className, get_called_class());
		$callers = debug_backtrace(null, 3);
		$relation = $callers[2]['function'];
		$options['where'] = isset($options['where']) ? $options['where'] : ["parent_type" => $this->isWhat()]; 
		$options['select'] = isset($options['select']) ? $options['select'] : '*';
		//Disabled due to Yii framework inability to return statistical relations
		//if(static::className() != $className)
			//$ret_val->with(['count', 'newCount']);
		$relationFunction = ($many === true) ? 'hasMany' : 'hasOne';
		$ret_val = $this->$relationFunction($className, $link);
		if(is_array($options) && !empty($options))
		{
			foreach($options as $option=>$params)
			{
				if(is_string($option))
					$ret_val->$option($params);
			}
		}
		return $ret_val;
	}
	
	public function getCachedRelation($key, $modelClass, $options=[], $many=false, $relation=null)
	{
		$relation = is_null($relation) ? \nitm\helpers\Helper::getCallerName() : $relation;
		//Disabled due to Yii framework inability to return statistical relations
		//if(static::className() != $className)
			//$ret_val->with(['count', 'newCount']);
		$cacheFunction = $many === true ? 'getCachedModelArray' : 'getCachedModel';
		return Cache::$cacheFunction($this, $key, $modelClass, $relation, $options);
	}
	
	public static function setCachedRelationModel($model, $idKey='id', $relation=null, $many=false)
	{
		return Cache::setCachedModel(Cache::cacheKey($model, $idKey, $relation, $many), $model);
	}
	
	public static function deleteCachedRelationModel($model, $idKey='id', $relation=null, $many=false)
	{
		$relation = is_null($relation) ? \nitm\helpers\Helper::getCallerName() : $relation;
		return Cache::deleteCachedModel(Cache::cacheKey($model, $idKey, $relation, $many));
	}
 }
?>
