<?php
/**
 * @link http://www.ninjasitm.com/
 * @copyright Copyright (c) 2014 NinjasITM INC
 * @license http://www.ninjasitm.com/license/
 */

namespace nitm\importer;

use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\FileHelper;

/**
 * JsonParser parses a json string of data.
 */
class JsonParser extends BaseParser
{
	public function parse($data, $offset = 0, $limit = 150)
	{
		return array_slice(json_decode($data, true), $offset, $limit);
	}
}
