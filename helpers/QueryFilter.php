<?php

namespace nitm\helpers;

class QueryFilter 
{
	/**
	 * Get the query that orders items by their activity
	 */
	public static function getHasNewQuery($model, $filterCallback=null)
	{
		//Check parent_id columns in issues, vote...etc tables
		$types = \Yii::$app->getModule('nitm-widgets')->checkActivityFor;
		if(!count($types))
			return "";
		
		$currentUser = \Yii::$app->getUser()->getIdentity();
		
		if(is_null($filterCallback))
			$filterCallback = function ($model) {
				return [
					'parent_id='.$model->tableName().'.id', 
					['parent_type' => $model->isWhat()],
				];
			};
		else if(!is_callable($filterCallback))
			throw new \Exception("The second argument to ".__FUNCTION__." must be a callback function ");
		
		foreach((array)$types as $type=>$where)
		{
			if(is_int($type)) {
				$type = $where;
				$where = $filterCallback($model);
			}
			$query = $type::find();
			$query->from([
				$type::tableName(),
				$model->tableName()
			]);
			$query->select(["SUM(IF((
					(
						'".$currentUser->lastActive()."'<=".$model->tableName().".created_at 
						OR 
						'".$currentUser->lastActive()."'<=".$model->tableName().".updated_at
					) 
					AND 
					(
						".$type::tableName().".updated_at>=".$model->tableName().".created_at 
						OR 
						".$type::tableName().".updated_at>=".$model->tableName().".updated_at
						OR 
						".$type::tableName().".created_at>=".$model->tableName().".created_at
						OR 
						".$type::tableName().".created_at>=".$model->tableName().".updated_at
					)
				), 
				1, 0)
			) AS hasNew"]);
			foreach($where as $filter)
			{
				$query->andWhere($filter);
			}
			$select[] = $query->createCommand()->getRawSql();
			unset($query);
		}
		return new \yii\db\Expression("(SELECT SUM(hasNew) FROM (".implode(' UNION ALL ', $select).") hasNewData) AS hasNewActivity");
	}
	
	/**
	 * Get the query that orders items by their activity
	 */
	public static function getOrderByQuery($model)
	{
		return [
			"COALESCE(".$model->tableName().".updated_at, ".$model->tableName().".created_at)" => SORT_DESC,
		];
	}
	
	public static function getVisibilityFilter()
	{
		$currentUser = \Yii::$app->getUser()->getIdentity();
		
		$where = [
			'or', 
			'author_id='.$currentUser->getId()
		];
		$slugs = ['visibility-public'];
		
		if((boolean)\Yii::$app->user->identity->isAdmin())
			array_push($slugs, 'visibility-admin');
		
		foreach($slugs as $slug) {
			array_push($where, 
				'level_id=('.\nitm\models\Category::find()
					->select('id')
					->where(['slug' => $slug])
					->limit(1)
					->createCommand()->getRawSql().')'
			);
		}
		return $where;
	}
	
	/**
	 * Alias the select fields for a query
	 * @param Query|array $query THe query or conditions being modified
	 * @param Object|string $model Either a model or the table name
	 */
	public static function aliasSelectFields(&$query, $model)
	{
		$query->select = !$query->select ? '*' : $query->select;
		if(!is_array($query->select))
			$query->select = [$query->select];
			
		foreach($query->select as $idx=>$field) {
			
			if($field instanceof \yii\db\Query
				|| $field instanceof \yii\db\Expression)
					continue;
			if((strpos($field, '(') || strpos($field, ')')) !== false)
				continue;
		
			if(is_string($field) && strpos($field, '.') !== false)
				continue;
			if(is_string($field) && $model instanceof \yii\db\ActiveRecord)
				$query->select[$idx] = $model->tableName().'.'.$field;
			else if(is_string($field) && is_string($model))
				$query->select[$idx] = $model.'.'.$field;
		}
	}
	
	/**
	 * Alias the where fields for a query
	 * @param Query|array $query THe query or conditions being modified
	 * @param Object|string $model Either a model or the table name
	 */
	public static function aliasWhereFields(&$query, $model)
	{
		if($query instanceof \yii\db\Query)
			$where =& $query->where;
		else if(is_array($query))
			$where =& $query;
		
		if(!isset($where) || !is_array($where))
			return;
			
		foreach($where as $field=>$value) {
			if(is_string($field)) {
				//If an object was passed get the table name
				if(is_object($model) && $model->hasAttribute($field))
					$where[$model->tableName().'.'.$field] = $value;
				//Otherwise only a table name could have been passed
				else
					$where[$model.'.'.$field] = $value;
				unset($where[$field]);
			} else if(is_array($value))
				static::aliasWhereFields($value, $model);
		}
	}
	
	/**
	 * Alias the orderBy fields for a query
	 * @param Query|array $query THe query or conditions being modified
	 * @param Object|string $model Either a model or the table name
	 */
	public static function aliasOrderByFields(&$query, $model)
	{	
		$joined = [];
		if($query instanceof \yii\db\Query)
			$orderBy =& $query->orderBy;
		else if(is_array($query))
			$orderBy =& $query;
		
		if(!isset($orderBy) || !is_array($orderBy))
			return;
		
		foreach($orderBy as $field=>$order)
		{
			if($order instanceof \yii\db\Query
				|| $order instanceof \yii\db\Expression
				|| $field instanceof \yii\db\Query
				|| $field instanceof \yii\db\Expression)
					continue;
					
			if(is_string($field) && strpos($field, '.') !== false) {
				$table = array_shift(explode('.', $field));
				$field = array_pop(explode('.', $field));
			} else
				$table = is_string($model) ? $model : $model->tableName();
			
			$class = $query->modelClass;
			
			if($class::tableName() == $table || (strpos($table, '(') || strpos($table, ')') !== false))
				continue;
				
			if(isset($table) && !in_array($table, $joined)) {
				$query->orderBy[$table.'.'.$field] = $order;
				unset($query->orderBy[$field]);
				//$query->from[] = $table;
				$query->leftJoin($table, '0=1');
				$joined[] = $table;
			}
		}
		$query->from(array_unique($query->from));
	}
	
	/**
	 * Add related tables to from selection for relations and ordering by relations
	 */
	public function addWithTables(&$query, $model, $attributes=[])
	{
		$joined = [];
		if($query instanceof \yii\db\Query)
			$with =& $query->with;
		else if(is_array($query))
			$with =& $query;
		
		if(!isset($with) || !is_array($with))
			return;
			
		foreach($with as $idx=>$toJoin) {
			if(($relation = $model->hasRelation($toJoin)) && isset($attributes[$toJoin])) {
				$class = $relation->modelClass;
				$table = $class::tableName();
				if($model->tableName() == $class::tableName())
					continue;
				
				if(in_array($table, $joined) || (strpos($table, '(') || strpos($table, ')') !== false))
					continue;
					
				self::aliasFields($relation, $table);
				$query->leftJoin($table, '0=1');
				$joined[] = $table;
				
				/*$this->dataProvider->query->joinWith([
					 $toJoin => function ($query) use($relation, $toJoin, $idx, $class) {
						$query->from($class::tableName().' '.$class::isWhat().ucfirst($toJoin).$idx);
						QueryFilter::aliasFields($query, $class::tableName());
					 }
				]);*/
			}
		}
		if(($query instanceof \yii\db\Query) && is_array($query->join)) {
			$query->join = array_map('unserialize', array_unique(array_map('serialize', $query->join)));
		}
	}
	
	public static function aliasFields(&$query, $model)
	{
		self::aliasSelectFields($query, $model);
		self::aliasOrderByFields($query, $model);
		self::aliasWhereFields($query, $model);
	}
}
?>