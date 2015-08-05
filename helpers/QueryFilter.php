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
		return "(SELECT SUM(hasNew) FROM (".implode(' UNION ALL ', $select).") hasNewData) AS hasNewActivity";
	}
	
	/**
	 * Get the query that orders items by their activity
	 */
	public static function getOrderByQuery()
	{
		return [
			"updated_at" => SORT_DESC,
			"created_at" => SORT_DESC,
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
}
?>