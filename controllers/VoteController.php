<?php

namespace nitm\controllers;

use nitm\models\Vote;

/**
 * Upvoting based on democratic one vote per user system
 *
 */

class VoteController extends \nitm\controllers\DefaultController
{
	
	public function behaviors()
	{
		$behaviors = [
			'access' => [
				'class' => \yii\web\AccessControl::className(),
				'only' => ['down', 'up', 'reset'],
				'rules' => [
					[
						'actions' => ['down', 'up', ],
						'allow' => true,
						'roles' => ['@'],
					],
				],
			],
			'verbs' => [
				'class' => \yii\web\VerbFilter::className(),
				'actions' => [
					'down' => ['get'],
					'up' => ['get'],
					'reset' => ['get'],
				],
			],
		];
		
		return array_replace_recursive(parent::behaviors(), $behaviors);
	}
	
	/**
	 * Place a downvote
	 * @param string $type the type of object
	 * @param int $id the id
	 * @return boolean should we allow more downvoting?
	 */
    public function actionDown($type, $id)
    {
		$ret_val = ['success' => false];
		$existing = new Vote();
		$existing->queryFilters['author'] = \Yii::$app->user->getId();
		$existing->queryFilters['parent_type'] = $type;
		$existing->queryFilters['parent_id'] = $id;
		$vote = $existing->find()->where($existing->queryFilters)->one();
		switch($vote instanceof Vote)
		{
			case false:
			$vote = new Vote();
			$vote->setScenario('create');
			$vote->load([
				'parent_type' => $type, 
				'parent_id' => $id, 
				'user_id' => \Yii::$app->user->getId()
			]);
			break;
			
			default:
			$vote->setScenario('update');
			break;
		}
		$vote->value = $vote->allowMultiple() ? $vote->value-1 : -1;
		$ret_val['success'] = $vote->save();
		unset($existing->queryFilters['user_id']);
		$ret_val['value'] = $vote->getRating();
		$ret_val['atMax'] = $vote->allowMultiple() ? false : $ret_val['value']['positive'] >= $vote->getMax();
		$ret_val['atMin'] = $vote->allowMultiple() ? false : @$ret_val['value']['negative'] <= 0;
		$this->renderResponse($ret_val);
    }
	
	/**
	 * Place an upvote
	 * @param string $type the type of object
	 * @param int $id the id
	 * @return boolean should we allow more upvoting?
	 */
    public function actionUp($type, $id)
    {
		$ret_val = ['success' => false, 'value' => null];
		$existing = new Vote();
		$existing->queryFilters['user_id'] = \Yii::$app->user->getId();
		$existing->queryFilters['parent_type'] = $type;
		$existing->queryFilters['parent_id'] = $id;
		$vote = $existing->find()->where($existing->queryFilters)->one();
		switch($vote instanceof Vote)
		{
			case false:
			$vote = new Vote();
			$vote->setScenario('create');
			$vote->load([
				'Vote' => [
					'parent_type' => $type, 
					'parent_id' => $id, 
					'user_id' => \Yii::$app->user->getId()
				]
			]);
			break;
			
			default:
			$vote->setScenario('update');
			$vote->value = $vote->allowMultiple() ? $vote->value+1 : 1;
			break;
		}
		$ret_val['success'] = $vote->save();
		unset($existing->queryFilters['user_id']);
		$ret_val['value'] = $vote->getRating();
		$ret_val['atMax'] = $vote->allowMultiple() ? false : $ret_val['value']['positive'] >= $vote->getMax();
		$ret_val['atMin'] = $vote->allowMultiple() ? false : @$ret_val['value']['negative'] <= 0;
		$this->renderResponse($ret_val);
    }

}
