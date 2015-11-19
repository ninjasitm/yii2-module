<?php
namespace nitm\traits;

use yii\base\Event;

/**
 * Controller actions
 */
trait EventTraits {

	/*
	 * Map the class events to the specified classes. Two formats are supported:
	 * The first format uses default events
	 * [
	 *		\namespace\SomeClass
	 *		...
	 * ]
	 *
	 * In the second the user specifies the various events
	 * [
	 *		(start|process) => [
	 *			EVENT
	 *			...
	 *		]
	 * ]
	 */
	public $eventClassMap;

	protected function initEventClassMap()
	{
		$this->eventClassMap = $this->eventClassMap == [] ? [\nitm\models\Entity::className()] : $this->eventClassMap;
		$class = \nitm\models\Entity::className();
		if($this->eventClassMap !== false && empty($this->eventClassMap)) {
			$this->eventClassMap = $this->getDefaultEventsFor($class);
		} else if(is_array($this->eventClassMap))
			$this->eventClassMap = array_merge($this->getDefaultEventsFor($class), $this->eventClassMap);
	}

	protected function getDefaultEventsFor($class)
	{
		return [
			self::EVENT_START => [
				$class::EVENT_BEFORE_INSERT,
				$class::EVENT_BEFORE_UPDATE,
				$class::EVENT_BEFORE_DELETE
			],
			self::EVENT_PROCESS => [
				$class::EVENT_AFTER_INSERT,
				$class::EVENT_AFTER_UPDATE,
				$class::EVENT_AFTER_DELETE
			]
		];
	}

	protected function attachToEvents($events)
	{
		if($this->eventClassMap === false)
			return;

		if($this->eventClassMap !== false) {
			$this->initEventClassMap();
		}

		//Setup the event handlers for specified events
		foreach($events as $event=>$handler)
			$this->on($event, $handler);

		foreach ($this->eventClassMap as $k=>$v)
		{
			switch(1)
			{
				//Allow user to specify the events as values for an associated array
				case is_string($k):
				$class = $k;
				$events = (array)$v;
				break;

				default:
				$class = $v;
				$events = $this->getDefaultEventsFor($class);
				break;
			}
			if(class_exists($class)) {
				foreach($events as $type=>$group) {
					foreach($group as $e) {
						Event::on($class::className(), $e, function ($event) {
							$trigger = $event->data['group'];
							\Yii::trace("Handling [$trigger on $event->name] event for ".get_class($event->sender)."\n\n".json_encode($event, JSON_PRETTY_PRINT));
							unset($event->data['group']);
							$this->trigger($trigger, $event);
						}, ['group' => $type]);
					}
				}
			}
		}
	}

	protected function setDefaultEventClassMap()
	{
	}
}
