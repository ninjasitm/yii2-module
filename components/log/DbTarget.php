<?php
/**
 * @link http://www.ninjasitm.com/
 * @copyright Copyright (c) 2014 NinjasITM INC
 * @license http://www.ninjasitm.com/license/
 */

namespace nitm\components\log;

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
        foreach ($this->messages as $message) {

            if (isset($message['message']) && !is_string($message['message'])) {
                // exceptions may not be serializable if in the call stack somewhere is a Closure
                if ($message['message'] instanceof \Exception) {
                    $message['message'] = (string) $message['message'];
                } else {
                    $message['message'] = \yii\helpers\VarDumper::export($message['message']);
                }
            }

			$message['timestamp'] = date('Y-m-d H:i:s', (!isset($message['timestamp']) ? strtotime('now') : $message['timestamp']));
			$message['log_time'] = $message['timestamp'];
			$dbEntry = new \nitm\models\log\DbEntry();
			$dbEntry->load($message, '');
			try {
				$dbEntry->getDb()->createCommand()->insert($dbEntry->tableName(), $dbEntry->getDirtyAttributes())->execute();
			} catch (\Exception $e) {
				throw ($e);
			}
        }
    }
}
