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
	public static $usersWhere = [];
	
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
		if(!\Yii::$app->getModule('nitm')->enableAlerts)
			return false;			
		$interval = !$this->setting('globals.push_interval') ? \Yii::$app->getModule('nitm')->alerts->pushInterval : $this->setting('globals.push_interval');
		return (round(strtotime('now') - $this->lastAlertSent()->timestamp)/60) >= $interval;
	}
	
	public function getPriority()
	{
		switch($this->priority)
		{
			case 'critical':
			$ret_val = 'error';
			break;
			
			case 'important':
			$ret_val = 'info';
			break;
			
			default:
			$ret_val = 'default';
			break;
		}
		return $ret_val;
	}
}
?>