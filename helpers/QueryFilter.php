<?php

namespace nitm\helpers;

use yii\db\Expression;
use yii\db\Query;
use yii\db\ActiveRecord;

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
		return new Expression("(SELECT SUM(hasNew) FROM (".implode(' UNION ALL ', $select).") hasNewData) AS hasNewActivity");
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

	public static function isExpression($string)
	{
		return is_string($string) && (strpos($string, '(') !== false || strpos($string, ')') !== false);
	}

	public static function joinFields($parts, $glue='.')
	{
		return (string)implode($glue, array_filter($parts, 'strlen'));
	}

	/**
	 * Alias the select fields for a query
	 * @param Query|array $query THe query or conditions being modified
	 * @param Object|string $model Either a model or the table name
	 */
	public static function aliasSelectFields(&$query, $model)
	{
		if(is_object($query)) {
			$query->select = !$query->select ? '*' : $query->select;
			if(!is_array($query->select)) {
				$class = $query->modelClass;
				$query->select = array_keys($class::getTableSchema()->columns);
			}
			$select =& $query->select;
		} else {
			$select =& $query;
		}

		$tableName = static::getAlias($query);

		foreach((array)$select as $idx=>$field) {

			if(is_string($idx))
				$field = $idx;

			if($field instanceof Query || $field instanceof Expression)
					continue;
			if((strpos($field, '(') || strpos($field, ')')) !== false)
				continue;

			if(is_string($field) && strpos($field, '.') !== false)
				continue;
			if(is_string($field) && $model instanceof ActiveRecord)
				$select[$idx] = $tableName.'.'.$field;
			else if(is_string($field) && is_string($model))
				$select[$idx] = $model.'.'.$field;
		}
		$query->select = $select;
	}

	public static function getAlias($query, $model=null, $alias=null)
	{
		if($query instanceof \yii\db\Query) {
			return ArrayHelper::isIndexed($query->from) ? ($alias ?: $query->from[0]) : key($query->from);
		} else if (!is_null($alias)){
			return $alias;
		} else if(is_object($model)) {
			return is_string($model) ? $model : $model->tableName();
		}
	}

	/**
	 * Alias the where fields for a query
	 * @param Query|array $query THe query or conditions being modified
	 * @param Object|string $model Either a model or the table name
	 */
	public static function aliasWhereFields(&$query, $model, $alias = null)
	{
		if($query instanceof Query)
			$where =& $query->where;
		else if(is_array($query))
			$where =& $query;

		if(!isset($where) || !is_array($where))
			return;

		//Model will be the tablename if a string is passed for $model
		if(is_null($alias)) {
		 	if(is_string($model))
				$alias = $model;
			else
				$alias = static::getAlias($query, $model, $alias);
		}
		foreach($where as $field=>$value) {
			if(is_string($field) && strpos('.', $field) === false) {
				//If an object was passed get the table name
				if(is_object($model) && $model->hasAttribute($field))
					$where[$alias.'.'.$field] = $value;
				//Otherwise only a table name could have been passed
				else if(is_string($model))
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
		if($query instanceof Query)
			$orderBy =& $query->orderBy;
		else if(is_array($query))
			$orderBy =& $query;

		if(!isset($orderBy) || !is_array($orderBy))
			return;

		if($query instanceof Query)
			$with =& $query->with;

		$with = (array)$with;

		$db =  $query->createCommand()->db;
		$newOrderBy = $groupBy = [];
		foreach($orderBy as $field=>$order)
		{
			if($order instanceof Query || $order instanceof Expression) {
				$newOrderBy[$field] = $order;
				continue;
			}

			$originalField = $field;

			$table = '';

			/**
			 * Try to see if this is a serialized string
			 */
			if(is_string($field) && (($unserialized = @unserialize($field)) !== false))
				$field = $unserialized;

			if(is_object($field) && !($field instanceof Expression))
				throw new \yii\base\InvalidArgumentException("The only object supported is a \yii\db\Expression object");

			$fieldParts = explode('.', $field);
			$table = !static::isExpression($field) ? array_shift($fieldParts) : $table;

			if($field instanceof Expression) {
				//The field/relation is most likely the first part before the first period. We should remove it if this is an expression
				$field = explode('.', $field);
				$table = array_shift($field);
				if(static::isExpression($table)) {
					array_unshift($field, $table);
					$table = '';
				}
				$field = new Expression(static::joinFields((array)$field));
			} else if(static::isExpression($field)) {
				//This is an expression that hasn't been wrapped yet. Wrap it in yii db Expresion
				$field = new Expression(static::joinFields([$table, $field]));
			} else if((strpos($field, '.') !== false) && !static::isExpression($field)) {
				$field = array_pop(explode('.', $field));
			} else
				$table = is_string($model) ? $model : static::getAlias($query);

			$class = $query->modelClass;

			if($field instanceof Expression && (static::isExpression($table) || empty($table))) {
				$newOrderBy[(string)$field] = $order;
				continue;
			}

			/**
			 * If the field belongs to the current table then alias it and add it to the list of sorted fields and continue
			 */
			if($class::tableName() == $table && !static::isExpression($table)) {
				$key = static::joinFields([$table, $field]);
				$newOrderBy[$key] = $order;
				$ret_val[$field] = $order;
				continue;
			}

			//If $table is actually the name of a relation then investigate further
			if(isset($table) && !in_array($table, $joined)) {
				//If a relation was specified as the joining value then we need to join the relation and order by the aliased table
				if(in_array($table, $with)) {
					$toJoin = $table;
					if($relation = $model->hasRelation($toJoin)) {

						$relationClass = $relation->modelClass;
						$relationTable = $relationClass::tableName();

						//Left join to return the values from the subject table only
						//$on = '0=1';
						//$query->innerJoin(static::joinFields([$relationTable, $alias], ' '), $on);
						$relationLink = $relation->link;

						if($relationClass::tableName() == $class::tableName())
							$alias = '';
						else
							$alias = $toJoin;

						$query->innerJoinWith([
							$toJoin => function ($relation) use($db, $class, $alias, $relationTable) {
								$relationClass = $relation->modelClass;
								$relationTable = $relationClass::tableName();
								$relation->from([$alias => $relationTable]);
								$relation->select($alias.'.*');
							}
						]);
						$aliasField = static::joinFields($field instanceof Expression ? [$field] : [$table, $field]);
						$newOrderBy[$aliasField] = $order;
						//Ignore the universal 'id' attribute
						$ret_val[static::joinFields($relationLink, ', ')] = $order;
						$relationLink = array_merge($relationLink, array_keys((array)$relation->where));
						self::aliasSelectFields($relationLink, $alias);
						$groupBy = array_values(array_unique(array_merge($groupBy, $relationLink)));
						$groupBy =array_merge($groupBy, array_map(function ($key) use($model) {
							return $model->tableName().'.'.$key;
						}, $model->primaryKey()));
					}
				} else {
					$newOrderBy[static::joinFields([$table, $field])] = $order;
					$ret_val[$field.''] = $order;
				}
				$joined[] = $table;
			}
		}
		if($query instanceof Query) {
			$query->orderBy($newOrderBy);
			if(!empty($groupBy))
				$query->groupBy($groupBy);
		} else {
			$query = $newOrderBy;
		}
		//Return the original fields that were sorted by
		return $ret_val;
	}

	public static function aliasFields(&$query, $model)
	{
		self::aliasSelectFields($query, $model);
		self::aliasOrderByFields($query, $model);
		self::aliasWhereFields($query, $model);
	}

	public static function setDataProviderOrders($dataProvider, $orders=[])
	{
		$dataProvider->sort->params = $sort = [];
		if(is_array($orders))
		{
			foreach($orders as $key=>$direction) {
				try {
					$sortParams = $dataProvider->sort->attributes[$key];
					foreach(['asc', 'desc'] as $order)
					{
						foreach($sortParams[$order] as $field=>$fieldDirection)
						{
							unset($dataProvider->sort->attributes[$key][$order][$field]);
							$field = ($unserialized = @unserialize($field)) !== false ? $unserialized : $field;
							/*$newField = explode('.', $field);
							$newField[0] .= 'OrderBy';
							$field = static::joinFields($newField);*/
							$dataProvider->sort->attributes[$key][$order][$field] = $fieldDirection;
						}
					}
				} catch (\Exception $e) {}

				$sort[] = ($direction == SORT_ASC) ? $key : '-'.$key;
			}
			$dataProvider->sort->params[$dataProvider->sort->sortParam] = implode(',', $sort);
			$dataProvider->sort->getOrders(true);
		}
	}
}
?>
