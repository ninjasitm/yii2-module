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
 * This is the importer that other importers derive from.
 */
class BaseParser extends \yii\base\Object
{
	public $data;
	public $parsedData;
	public $limit = 100;
	public $offset = 0;
	
	public $fields = [];
	
	public function parse($data)
	{
		throw new \yii\base\InvalidCallException("Child classes must implement ".__FUNCTION__);
	}
	
	public function columns()
	{
		throw new \yii\base\InvalidCallException("Child classes must implement ".__FUNCTION__);
	}
}
