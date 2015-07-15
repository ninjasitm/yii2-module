<?php

namespace nitm\traits;

use Yii;
use nitm\models\log\search\AlertEntry;

/**
 * Class Replies
 * @package nitm\module\models
 */

trait Alerts
{
	private $_alertModel;
	
	public function getLastAlertSent()
	{
		return $this->hasOne(AlertEntry::className(), ['remote_id' => 'id'])
			->select(['timestamp', 'remote_type', 'remote_id'])
			->where([
				'remote_type' => $this->isWhat()
			])
			->orderBy(['timestamp' => SORT_DESC]);
	}
	
	public function lastAlertSent()
	{
		return \nitm\helpers\Relations::getRelatedRecord('lastAlertSent', $this, AlertEntry::className(), [
			'timestamp' => strtotime('now')
		]);
	}
	
	public function canSendPushAlert()
	{
		if(!isset($this->_alertModel))
			$this->_alertModel = new \nitm\models\Alerts(['noDbInit' => true]);
			
		$interval = !$this->_alertModel->setting('globals.push_interval') ? \Yii::$app->getModule('nitm')->alerts->pushInterval : $this->_alertModel->setting('globals.push_interval');
		echo $interval;
		return (round(strtotime('now') - $this->lastAlertSent()->timestamp)/60) >= $interval;
	}
}
?>