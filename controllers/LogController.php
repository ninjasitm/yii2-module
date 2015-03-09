<?php

namespace nitm\controllers;

use Yii;
use nitm\models\log\Entry;
use nitm\models\log\search\Entry AS EntrySearch;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

/**
 * LogController implements the CRUD actions for Entry model.
 */
class LogController extends DefaultController
{
	public function init()
	{
		parent::init();
		$this->model = new EntrySearch;
	}
	
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['post'],
                ],
            ],
        ];
    }

    /**
     * Lists all Log Entry models.
     * @return mixed
     */
    public function actionIndex($type=null)
    {
		if(!is_null($type))
			Entry::$collectionName = EntrySearch::$collectionName = $type;
		else
			Entry::$collectionName = EntrySearch::$collectionName = $this->module->logCollections[0];
		
		return parent::actionIndex(EntrySearch::className(), [
			'with' => [
				'user',
			],
		]);
    }
}
