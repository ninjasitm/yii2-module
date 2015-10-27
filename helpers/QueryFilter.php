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

			if($field instanceof Query || $field instanceof Expression)
					continue;
			if((strpos($field, '(') || strpos($field, ')')) !== false)
				continue;

			if(is_string($field) && strpos($field, '.') !== false)
				continue;
			if(is_string($field) && $model instanceof ActiveRecord)
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
	public static function aliasWhereFields(&$query, $model, $alias = null)
	{
		if($query instanceof Query)
			$where =& $query->where;
		else if(is_array($query))
			$where =& $query;

		if(!isset($where) || !is_array($where))
			return;

		$alias = is_null($alias) ? $model->tableName() : $alias;
		foreach($where as $field=>$value) {
			if(is_string($field) && strpos('.', $field) === false) {
				//If an object was passed get the table name
				if(is_object($model) && $model->hasAttribute($field))
					$where[$alias.'.'.$field] = $value;
				//Otherwise only a table name could have been passed
				else if(!is_object($model))
					$where[$model.'.'.$field] = $value;
				unset($where[$field]);
			} else if(is_array($value))
				static::aliasWhereFields($value, $model);
		}
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
		else
			$with = [];

		$db =  $query->createCommand()->db;
		$newOrderBy = [];
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

			$table = !static::isExpression($field) ? array_shift(explode('.', $field)) : $table;

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
				$table = is_string($model) ? $model : $model->tableName();

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

						//We will skip for various conditions

						//If we already joined this relation
						if(in_array($toJoin, $joined))
							continue;

						if($relationClass::tableName() == $class::tableName())
							$alias = '';
						else
							$alias = $toJoin;

						//We join on the relation links
						$on = [];
						foreach($relation->link as $relationField=>$targetAttr)
						{
							$on[$alias.'.'.$relationField] = new Expression(static::joinFields([
								$db->quoteTableName($class::tableName()),
								$db->quoteColumnName($targetAttr)
							]));
						}

						//We join on the where as well;
						static::aliasWhereFields($relation, new $relationClass, $alias);
						if(is_array($relation->where))
							$on += $relation->where;

						//Left join to return the values from the subject table only
						$query->leftJoin(static::joinFields([$relationTable, $alias], ' '), $on);
						$aliasField = static::joinFields($field instanceof Expression ? [$field] : [$alias, $field]);
						$newOrderBy[$aliasField] = $order;
						//Ignore the universal 'id' attribute
						$relationLink = array_filter($relation->link, function ($attribute) {
							return $attribute != 'id';
						});
						$ret_val[static::joinFields($relationLink, ', ')] = $order;
						$ret_val[$toJoin] = $order;
					}
				} else {
					$newOrderBy[static::joinFields([$table, $field])] = $order;
					$ret_val[$field.''] = $order;
				}
				$joined[] = $table;
			}
		}
		if($query instanceof Query)
			$query->orderBy($newOrderBy);
		else {
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
							$field = unserialize($field);
							$dataProvider->sort->attributes[$key][$order][substr($field, strpos($field, '.')+1)] = $fieldDirection;
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
