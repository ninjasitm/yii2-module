<?php

namespace nitm\models;

use Yii;
use yii\base\Event;
use yii\db\ActiveRecord;
use nitm\helpers\alerts\Dispatcher;
use nitm\Module as Nitm;
use nitm\search\Module as NitmSearch;

/**
 */
class Entity extends Data
{	
	use \nitm\traits\Nitm, \nitm\traits\Relations;
	
	public $hasNewActivity;	
	
	public function init()
	{
		parent::init();
	}
	
	public function scenarios()
	{
		return array_merge(parent::scenarios(), [
			'default' => []
		]);
	}
	
	public function initalizeEventData(&$event)
	{
		$event->data = [
			'action' => $event->sender->getScenario(),
			'variables' => [
				'%id%' => $event->sender->getId(),
				"%viewLink%" => \yii\helpers\Html::a(
				\Yii::$app->urlManager->createAbsoluteUrl($event->sender->isWhat()."/view/".$event->sender->getId()), 
				\Yii::$app->urlManager->createAbsoluteUrl($event->sender->isWhat()."/view/".$event->sender->getId()))
			],
		];
	}
	
	public function getAlertOptions($event)
	{
		$event->sender->initalizeEventData($event);
		$options = [	
			'criteria' => [
				'action' => $event->sender->getScenario(),
				'priority' => 'normal',
				'remote_type' => $event->sender->isWhat(),
				'remote_for' => 'any'
			],
			'variables' => [
				'%createdAt%' => @\Yii::$app->formatter->asDatetime($event->sender->getAttribute('created_at')),
				'%udpatedAt%' => @\Yii::$app->formatter->asDatetime($event->sender->getAttribute('updatedAt')),
				'%resolvedAt%' => @\Yii::$app->formatter->asDatetime($event->sender->getAttribute('resolvedAt')),
				'%completedAt%' => @\Yii::$app->formatter->asDatetime($event->sender->getAttribute('completed_at')),
				'%disabledAt%' => @\Yii::$app->formatter->asDatetime($event->sender->getAttribute('disabled_at')),
			]
		];
			
		switch($event->sender->getScenario())
		{
			case 'create':
			$options = array_merge($options, [
				'subject' => ['view' => '@app/mail/subjects/new/'.$event->sender->isWhat()],
				'message' => [
					'email' => ['view' => '@app/mail/email/new/'.$event->sender->isWhat()],
					'mobile' => ['view' => '@app/mail/mobile/new/'.$event->sender->isWhat()]
				]
			]);
			break;
			
			case 'complete':
			case 'resolve':
			case 'disable':
			case 'update':
			case 'close':
			$options = array_merge($options, [
				'subject' => ['view' => '@app/mail/subjects/update/'.$event->sender->isWhat()],
				'message' => [
					'email' => ['view' => '@app/mail/email/update/'.$event->sender->isWhat()],
					'mobile' => ['view' => '@app/mail/mobile/update/'.$event->sender->isWhat()]
				]
			]);
			break;
		}
		if(!count($options) && !$event->sender->getId())
		{
			$options['owner_id'] = $event->sender->hasAttribute('author_id') ? $event->sender->author_id : null;
		}
		return $options;
	}
}
