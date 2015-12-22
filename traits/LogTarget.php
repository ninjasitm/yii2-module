<?php

namespace nitm\traits;

use Yii;
use nitm\helpers\ArrayHelper;
use nitm\components\Logger;

/**
 * Trait LogTarget
 * Implements some common traits for log targets
 * @package nitm\module\traits
 */

trait LogTarget
{
	/**
	 * ***Modified this version to support level and category checking
	 * Processes the given log messages.
	 * This method will filter the given messages with [[levels]] and [[categories]].
	 * And if requested, it will also export the filtering result to specific medium (e.g. email).
	 * @param array $messages log messages to be processed. See [[Logger::messages]] for the structure
	 * of each message.
	 * @param boolean $final whether this method is called at the end of the current application
	 */
	public function collect($messages, $final)
	{
		/**
		 * Extract the keys for each log entry so that filter can work properly
		 */
		$extracted = [];
		array_walk($messages, function ($value, $key) use(&$extracted){
			$extracted['keys'][$key] = array_keys($value);
			$extracted['values'][$key] = array_values($value);
		});

		/**
		 * Now remap the keys to the messages so that insert can work properly
		 */
		if(is_array($values = ArrayHelper::getValue($extracted, 'values', null)))
			if(is_array($filtered = $this->filterMessages($values, $this->getLevels(), $this->categories, $this->except))) {
				$messages = [];
				if(is_array($filtered) && $filtered != []) {
					array_walk($filtered, function ($value, $key) use(&$messages, $extracted){
						if(is_array($extracted) && $extracted != [])
							$messages[$key] = array_combine($extracted['keys'][$key], $value);
					});
				}
			}
		else
			$messages = [];

		$this->messages += $messages;

		$extracted = $messages = [];

		$count = count($this->messages);
		if ($count > 0 && ($final || $this->exportInterval > 0 && $count >= $this->exportInterval)) {
			if (($context = $this->getContextMessage()) !== '') {
				$this->messages[] = [$context, Logger::LEVEL_INFO, 'application', YII_BEGIN_TIME];
			}
			// set exportInterval to 0 to avoid triggering export again while exporting
			$oldExportInterval = $this->exportInterval;
			$this->exportInterval = 0;
			$this->export();
			$this->exportInterval = $oldExportInterval;
		}
	}
}
?>
