<?php
namespace nitm\traits;

use nitm\helpers\Cache;
use nitm\helpers\Relations as RelationsHelper;
use nitm\models\ParentMap;
use nitm\models\Category;
use nitm\helpers\ArrayHelper;
use nitm\helpers\QueryFilter;

/**
 * Traits defined for expanding active relation scopes until yii2 resolves traits issue
 */

trait Relations {

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
			])
			->groupBy(array_values($link));
		foreach(['where', 'orwhere', 'andwhere'] as $option)
			if(isset($this->queryOptions[$option]))
				$query->$option($this->queryOptions[$option]);
		return $query;
    }

	public function count($returnNull=false)
	{
		$ret_val = RelationsHelper::getRelatedRecord($this, 'count', static::className(), [
			'_count' => 0
		])['_count'];

		if($ret_val == 0 && $returnNull)
			return null;
		return $ret_val;
	}

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
		$options['with'] = isset($options['with']) ? $options['with'] : ['profile'];
		$options['where'] = [];
		return $this->getRelationQuery($className, $link, $options);
	}

	protected function getCachedUserModel($idKey, $className=null)
	{
		$className = is_null($className) ? \Yii::$app->user->identityClass : $className;
		if(\Yii::$app->getModule('nitm')->useModelCache)
		{
			return $this->getCachedRelation($idKey, $className, [], false, \nitm\helpers\Helper::getCallerName(), 'user');
		}  else
			return RelationsHelper::getRelatedRecord($this, \nitm\helpers\Helper::getCallerName(), $className, [], false);
	}

    /**
	 * Get user relation
	 * @param array $options Options for the relation
     * @return \yii\db\ActiveQuery
     */
    public function getUser($options=[])
    {
		return $this->getUserRelationQuery(['id' => 'user_id'], $options)->with('profile');
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
		return $this->getUserRelationQuery(['id' => 'author_id'], $options)->with('profile');
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
		return $this->getUserRelationQuery(['id' => 'editor_id'], $options)->with('profile');
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
		return $this->getUserRelationQuery(['id' => 'completed_by'], $options)->with('profile');
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
		return $this->getUserRelationQuery(['id' => 'resolved_by'], $options)->with('profile');
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
		return $this->getUserRelationQuery(['id' => 'closed_by'], $options)->with('profile');
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
		return $this->getUserRelationQuery(['id' => 'disabled_by'], $options)->with('profile');
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
		return $this->getUserRelationQuery(['id' => 'deleted_by'], $options)->with('profile');
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
		$options['select'] = isset($options['select']) ? $options['select'] : ['id', 'parent_ids', 'name', 'slug', 'html_icon'];
		$options['with'] = isset($options['with']) ? $options['select'] : [];
		$options['orderBy'] = isset($options['orderBy']) ? $options['orderBy'] : ['name' => SORT_DESC];
		return $this->getRelationQuery($className, $link, $options, $many);
	}

	protected function getCachedCategoryModel($idKey, $className=null, $relation=null, $many=false)
	{
		$className = is_null($className) ? \nitm\models\Category::className() : $className;
		$relation = is_null($relation) ? \nitm\helpers\Helper::getCallerName() : $relation;
		if(\Yii::$app->getModule('nitm')->useModelCache)
			return $this->getCachedRelation($idKey, $className, [], $many, $relation);
		else
			return RelationsHelper::getRelatedRecord($this, $relation, $className, [], $many);
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
     * @return \yii\db\ActiveQuery
     */
    public function getLevel()
    {
		return $this->getCategoryRelation(['id' => 'level_id']);
    }

	public function level()
	{
		return $this->getCachedCategoryModel('level_id', null, 'level');
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
		if(\nitm\search\traits\SearchTrait::useSearchClass($callingClass) !== false && (strpos($callingClass, 'search') === false))
			$parts[] = 'search';
		$parts[] = $baseName;
		return implode('\\', $parts);
	}

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getParentMap()
    {
		$options = [
            'from' => [
                'parentMap' => ParentMap::tableName()
            ],
			'where' => ['parentMap.remote_type' => $this->isWhat()],
		];
		return $this->getRelationQuery(ParentMap::className(), ['remote_id' => 'id'], $options);
    }

	public function parentMap()
	{
		return $this->getCachedRelation('id', ParentMap::className(), [], false, 'parentMap');
	}

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getParentsMap()
    {
		$options = [
            'from' => [
                'parentsMap' => ParentMap::tableName()
            ],
			'where' => ['parentsMap.remote_type' => $this->isWhat()],
		];
		return $this->getRelationQuery(ParentMap::className(), ['remote_id' => 'id'], $options, true);
    }

	public function parentsMap()
	{
		return $this->getCachedRelation('id', ParentMap::className(), [], true, 'parentsMap');
	}

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getParent()
    {
		/**
		 * parent_id represents the outter part of the query and will matcch to the static::className's id
		 * This is the parent_id in the ParentMap table
		 * remote_id maps to the current class's id
		 */
		return $this->getRelationQuery($this->className(), ['id' => 'parent_id'])
             ->from([
                 '_parent' => $this->tableName()
             ])
			->viaTable(ParentMap::tableName(), ['remote_id' => 'id'], function($query) {
                $alias = QueryFilter::getAlias($query, $this, 'parentMap');
                $query->from([
                    $alias => $query->from[0]
                ]);
				$query->where([$alias.'.remote_class' => $this->className()]);
				return $query;
			});
    }

	public function parent()
	{
		return $this->getCachedRelation('id', $this->className(), [], false, 'parent');
	}

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getParents()
    {
		/**
		 * parent_id represents the outter part of the query and will matcch to the static::className's id
		 * This is the parent_id in the ParentMap table
		 * remote_id maps to the current class's id
		 */;
		return $this->getRelationQuery($this->className(), ['id' => 'parent_id'], ['where' => []], true)
			->viaTable(ParentMap::tableName(), ['remote_id' => 'id']);
    }

	public function parents()
	{
		return $this->getCachedRelation('id', $this->className(), [], true, 'parents');
	}

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getChildren()
    {
		/**
		 * parent_id represents the outter part of the query and will matcch to the static::className's id
		 * This is the parent_id in the ParentMap table
		 * remote_id maps to the current class's id
		 */;
		return $this->getRelationQuery($this->className(), ['id' => 'remote_id'], ['where' => []], true)
			->viaTable(ParentMap::tableName(), ['parent_id' => 'id']);
    }

	public function children()
	{
		return $this->getCachedRelation('id', $this->className(), [], true, 'children');
	}

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getSiblings()
    {
		/**
		 * parent_id represents the outter part of the query and will matcch to the static::className's id
		 * This is the parent_id in the ParentMap table
		 * remote_id maps to the current class's id
		 */;
       return $this->getRelationQuery($this->className(), ['id' => 'id'], [], true)
            ->from([
                'siblings' => $this->tableName(),
            ])
            ->joinWith([
                "parent" => function ($query) {
                    $query->joinWith('children');
                }
            ]);
    }

	public function siblings()
	{
		return $this->getCachedRelation('id', $this->className(), [], true, 'siblings');
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getMetadata()
	{
	    $metadataClass = $this->getMetadataClass();
	    return $this->hasMany($metadataClass, $metadataClass::metadataLink())
            ->indexBy('key');
	}

 	/**
 	 * Get metadata, either from key or all metadata
 	 * @param string $key
 	 * @return mixed
 	 */
 	public function metadata($key=null)
 	{
		return ArrayHelper::getValue($this->metadata, $key, is_null($key) ? $this->metadata : null);
 	}


	protected function getRelationQuery($className, $link, $options=[], $many=false)
	{
		$className = $this->getRelationClass($className, get_called_class());
		$relationFunction = ($many === true) ? 'hasMany' : 'hasOne';
		$ret_val = $this->$relationFunction($className, $link);

		$callers = debug_backtrace(null, 3);

		$relation = $callers[2]['function'];
		$options['select'] = isset($options['select']) ? $options['select'] : null;
		/*$options['groupBy'] = array_map(function ($group){
			if(strpos($group, $this->tableName()) === false)
				$group = $this->tableName().'.'.$group;
			return $group;
		}, array_keys(isset($options['groupBy']) ? $options['groupBy'] : $link));*/
		if(is_array($options) && !empty($options))
		{
			foreach($options as $option=>$params)
			{
				if(is_string($option)) {
					$params = is_array($params) ? array_filter($params) : $params;
					$ret_val->$option($params);
				}
			}
		}
		return $ret_val;
	}

	public function getCachedRelation($idKey, $modelClass, $options=[], $many=false, $relation=null)
	{
		$relation = is_null($relation) ? \nitm\helpers\Helper::getCallerName() : $relation;
		return RelationsHelper::getCachedRelation($this, $idKey, $many, $modelClass, $relation, $options);
	}

	public function setCachedRelation($idKey, $modelClass, $options=[], $many=false, $relation=null)
	{
		$relation = is_null($relation) ? \nitm\helpers\Helper::getCallerName() : $relation;
		return RelationsHelper::setCachedRelation($this, $idKey, $many, $modelClass, $relation);
	}

	public function deleteCachedRelation($idKey, $modelClass, $options=[], $many=false, $relation=null)
	{
		$relation = is_null($relation) ? \nitm\helpers\Helper::getCallerName() : $relation;
		return RelationsHelper::deleteCachedRelation($this, $idKey, $many, $modelClass, $relation);
	}

	public function resolveRelation($idKey, $modelClass, $useCache=false, $options=[], $many=false, $relation=null)
	{
		$relation = is_null($relation) ? \nitm\helpers\Helper::getCallerName() : $relation;
		return RelationsHelper::resolveRelation($this, $idKey, $modelClass, $useCache, $many, $options, $relation);
	}
 }
?>
