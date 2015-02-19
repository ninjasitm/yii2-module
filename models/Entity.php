<?php

namespace nitm\models;

use Yii;
use yii\base\Event;
use yii\db\ActiveRecord;

/**
 */
class Entity extends Data
{	
	use \nitm\traits\Nitm, \nitm\traits\Relations;
	
	public $hasNewActivity;	
	
	public function init()
	{
		parent::init();
		$this->initEvents();
	}
	
	protected function initEvents()
	{
		$this->on(ActiveRecord::EVENT_BEFORE_INSERT, [$this, 'beforeSaveEvent']);
		$this->on(ActiveRecord::EVENT_BEFORE_UPDATE, [$this, 'beforeSaveEvent']);
		$this->on(ActiveRecord::EVENT_AFTER_INSERT, [$this, 'afterSaveEvent']);
		$this->on(ActiveRecord::EVENT_AFTER_UPDATE, [$this, 'afterSaveEvent']);
	}
	
	public function scenarios()
	{
		return array_merge(parent::scenarios(), [
			'default' => []
		]);
	}
	
	public function beforeSaveEvent($event=null)
	{
		$event = is_null($event) ? new \yii\base\Event([
			'sender' => $this
		]) : $event;
		
		if(\Yii::$app->hasModule('nitm'))
		{
			$event->data = [
				'action' => $event->sender->getScenario(),
				'variables' => [
					'%id%' => $event->sender->getId(),
					"%viewLink%" => \yii\helpers\Html::a(\Yii::$app->urlManager->createAbsoluteUrl($event->sender->isWhat()."/view/".$event->sender->getId()), \Yii::$app->urlManager->createAbsoluteUrl($event->sender->isWhat()."/view/".$event->sender->getId()))
				],
			];
			\Yii::$app->getModule('nitm')->alerts->prepareAlerts($event);
		}
		return $event->handled;
	}
	
	public function afterSaveEvent($event)
	{
		if(\Yii::$app->hasModule('nitm'))
		{
			\Yii::$app->getModule('nitm')->alerts->processAlerts($event, $this->getAlertOptions($event));
		}
		if(\Yii::$app->hasModule('nitm-search'))
			\Yii::$app->getModule('nitm-search')->processRecord($event);
	}
	
	protected function getAlertOptions($event)
	{
		$options = [];
		switch($event->sender->getScenario())
		{
			case 'create':
			$options = [
				'subject' => ['view' => '@app/mail/subjects/new/'.$event->sender->isWhat()],
				'message' => [
					'email' => ['view' => '@app/mail/email/new/'.$event->sender->isWhat()],
					'mobile' => ['view' => '@app/mail/mobile/new/'.$event->sender->isWhat()]
				]
			];
			break;
			
			case 'complete':
			case 'resolve':
			case 'disable':
			case 'update':
			case 'close':
			$options = [
				'subject' => ['view' => '@app/mail/subjects/update/'.$event->sender->isWhat()],
				'message' => [
					'email' => ['view' => '@app/mail/email/update/'.$event->sender->isWhat()],
					'mobile' => ['view' => '@app/mail/mobile/update/'.$event->sender->isWhat()]
				]
			];
			break;
		}
		if(!empty($options) && !empty($event->sender->getId()))
		{
			$options['owner_id'] = $event->sender->hasAttribute('author_id') ? $event->sender->author_id : null;
		}
		return $options;
	}
}
