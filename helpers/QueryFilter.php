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
		$joined = $ret_val = [];
		if($query instanceof \yii\db\Query)
			$orderBy =& $query->orderBy;
		else if(is_array($query))
			$orderBy =& $query;
		
		if(!isset($orderBy) || !is_array($orderBy))
			return;
		
		if($query instanceof \yii\db\Query)
			$with =& $query->with;
		else if(is_array($query))
			$with =& $query;
		else
			$with = [];
		
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
			
			/**
			 * If the field belongs to the current table then alias it and add it to the list of sorted fields and continue
			 */
			if($class::tableName() == $table || (strpos($table, '(') || strpos($table, ')') !== false)) {
				$query->orderBy[$table.'.'.$field] = $order;
				$ret_val[$field] = $order;
				unset($query->orderBy[$field]);
				continue;
			}
			
			/**
			 * Or if the field is a yii DB expression
			 */
			if($field instanceof \yii\db\Expression) {
				continue;
			}
			
			$db =  $query->createCommand()->db;
			
			//If $table is actually the name of a relation then investigate further
			if(isset($table) && !in_array($table, $joined)) {
				//If a relation was specified as the joining value then we need to join the relation and order by the aliased table
				if(in_array($table, $with)) {
					$toJoin = $table;
					if($relation = $model->hasRelation($toJoin)) {
						$relationClass = $relation->modelClass;
						$relationTable = $relationClass::tableName();
						
						//We will skip for various conditions
						
						//If we already joined this relation 	
						if(in_array($toJoin, $joined))
							continue;
						
						if($relationClass::tableName() == $class::tableName())
							$alias = '';
						else
							$alias = $toJoin;
						
						//We join on the relation links
						foreach($relation->link as $relationField=>$targetAttr)
						{
							$on[$alias.'.'.$relationField] = new \yii\db\Expression($db->quoteTableName($class::tableName()).'.'.$db->quoteColumnName($targetAttr));
						}
						//Left join to return the values from the subject table only
						$query->leftJoin($relationTable.' '.$alias, $on);
						$query->orderBy[$alias.'.'.$field] = $order;
						$ret_val[implode(',', array_values((array)$relation->link))] = $order;
						unset($query->orderBy[$toJoin.'.'.$field]);
					}
				} else {
					$query->orderBy[$table.'.'.$field] = $order;
					$ret_val[$field] = $order;
					unset($query->orderBy[$field]);
				}
				$joined[] = $table;
			}
		}
		//Returnt he original fields that were sorted by
		return $ret_val;
	}
	
	public static function aliasFields(&$query, $model)
	{
		self::aliasSelectFields($query, $model);
		self::aliasOrderByFields($query, $model);
		self::aliasWhereFields($query, $model);
	}
	
	public static function setDataProviderOrders($dataProvider, $orders)
	{
		$dataProvider->sort->params = $sort = [];
		foreach($orders as $key=>$direction) {
			$sort[] = ($direction == SORT_ASC) ? $key : '-'.$key; 
		}
		$dataProvider->sort->params[$dataProvider->sort->sortParam] = implode(',', $sort);
		$dataProvider->sort->getOrders(true);
	}
}
?>