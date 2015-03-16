<?php
/**
 * @link http://www.ninjasitm.com/
 * @copyright Copyright (c) 2014 NinjasITM INC
 * @license http://www.ninjasitm.com/license/
 */

namespace nitm\importer;

use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;

/**
 * This is the importer that other importers derive from.
 */
class BaseParser extends \yii\base\Object
{
	public $parsedData;
	
	protected $_limit = 100;
	protected $_offset = 0;
	protected $_data;
	
	public $fields = [];
	
	public function parse($data)
	{
		throw new \yii\base\InvalidCallException("Child classes must implement ".__FUNCTION__);
	}
	
	public function columns()
	{
		throw new \yii\base\InvalidCallException("Child classes must implement ".__FUNCTION__);
	}
	
	public function setData($data)
	{
		$this->_data = $data;
	}
	
	public function getData($path=null)
	{
		if(is_string($path))
			return ArrayHelper::getValue($this->_data, $path, null);
		return $this->_data;
	}
	
	public function setLimit($limit, $offset=0)
	{
		$this->_limit = (int)$limit;
		$this->_offset = (int)$offset;
	}
	
	public function setOffset($offset)
	{
		$this->_offset = (int)$offset;
	}
	
	public function getOffset()
	{
		return (int)$this->_offset;
	}
	
	public function getLimit()
	{
		return (int)$this->_limit;
	}
}
