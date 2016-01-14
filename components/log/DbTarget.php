<?php
/**
 * @link http://www.ninjasitm.com/
 * @copyright Copyright (c) 2014 NinjasITM INC
 * @license http://www.ninjasitm.com/license/
 */

namespace nitm\components\log;

use nitm\helpers\ArrayHelper;

/**
 * DbTarget stores log messages in a database table.
 *
 * The database connection is specified by [[db]]. Database schema could be initialized by applying migration:
 *
 * ```
 * yii migrate --migrationPath=@yii/log/migrations/
 * ```
 *
 * If you don't want to use migration and need SQL instead, files for all databases are in migrations directory.
 *
 * You may change the name of the table used to store the data by setting [[logTable]].
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class DbTarget extends \yii\log\DbTarget
{
	use \nitm\traits\LogTarget;

	public $collectionName = 'nitmlog';
	public $logTable = 'logs';

	/**
     * Stores log messages to DB.
     */
    public function export()
    {
        $tableName = $this->db->quoteTableName($this->logTable);
		$message = [];
		$toInsert = [];
        foreach ($this->messages as $message) {
			//If the entry is shorter than standard length then ignore it
			if(count($message) < 6)
				continue;
			$message = $this->restoreFields($message);
			//If we couldn't restore the entry then ignore it
			if($message == null || !ArrayHelper::isAssociative($message))
				continue;

            if (isset($message['message']) && !is_string($message['message'])) {
                // exceptions may not be serializable if in the call stack somewhere is a Closure
                if ($message['message'] instanceof \Exception) {
                    $message['message'] = (string) $message['message'];
                } else {
                    $message['message'] = \yii\helpers\VarDumper::export($message['message']);
                }
            }

			$message['timestamp'] = date('Y-m-d H:i:s', (!isset($message['timestamp']) ? strtotime('now') : $message['timestamp']));
			$dbEntry = new \nitm\models\log\DbEntry();
			$dbEntry->load($message, '');
			if($dbEntry->validate()) {
				$toInsert[] = $dbEntry->getDirtyAttributes();
			} else {
				throw new \yii\base\ErrorException(json_encode($dbEntry->getErrors()));
			}
        }
		if($toInsert != []) {
			try {
				$dbEntry->getDb()->createCommand()->batchInsert($dbEntry->tableName(), array_keys(current($toInsert)), $toInsert)->execute();
			} catch (\Exception $e) {
				throw $e;
			}
		}
		$this->messages = [];
    }

	/**
	 * Restore a log message by slicing the first half of the message with the appropriary fields
	 */
	protected function restoreFields($entry)
	{
		$fields = [
			'message', 'level', 'category', 'timestamp', 'internal_category'
		];

		$indexedFields = array_slice($entry, 0, sizeof($fields));
		$assocFields = array_slice($entry, sizeof($fields));

		if(count($fields) == count($indexedFields))
			return array_combine($fields, $indexedFields) + $assocFields;
		else
			return null;
	}
}
