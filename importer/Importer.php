<?php

namespace nitm\importer;

use yii\base\Model;
use yii\helpers\ArrayHelper;
use nitm\helpers\Session;
use nitm\helpers\Network;

class Importer extends \yii\base\Object
{
	//constant data
	const SOURCE_CSV = 'csv';
	const SOURCE_JSON = 'json';
	
	//public data
	public $currentUser;
	
	private $_types;
	private $_parsers;
	private $_sources;
	private $_parser;
	
	public function init()
	{
		parent::init();
		$this->currentUser = (\Yii::$app->hasProperty('user') && \Yii::$app->user->getId()) ? \Yii::$app->user->getIdentity() : new \nitm\models\User(['username' => (php_sapi_name() == 'cli' ? 'console' : 'web')]);
		
		if(!isset($this->_parsers))
			$this->setParsers();
		if(!isset($this->_sources))
			$this->setSources();
		if(!isset($this->_types))
			$this->setTypes();
	}
	
	public function behaviors()
	{
		$behaviors = [
		];
		return array_merge(parent::behaviors(), $behaviors);
	}
	
	public function getParser($type=false)
	{
		if(isset($this->_parser[$type]))
			return $this->_parser[$type];
			
		switch($type)
		{
			case in_array($type, $this->getTypes()):
			$class = ArrayHelper::getValue($this->getParsers(), $type.'.class', null);
			break;
			
			default:
			$class = CsvParser::className();
			break;
		}
		if(!class_exists($class))
			throw new \yii\base\ErrorExcetpion("Couldn't find parser for '$type'");
		
		$this->_parser[$type] = \Yii::createObject(['class' => $class]);
		return $this->_parser[$type];
	}
	
	/**
	 * Import data base on
	 * @param array $string|array $data
	 * @param string $type The type of the data
	 */
	public function import($data, $type='csv')
	{
		$ret_val = false;
		switch($type)
		{
			case in_array($type, $this->Types()):
			$ret_val = $this->getParser($type)->import($data);
			break;
			
			default:
			break;
		}
	}
	
	public function setTypes($types=[])
	{
		$this->_types = $types;
	}
	
	public function setParsers($parsers=[])
	{
		$this->_parsers = array_merge($parsers, [
			'csv' => [
				'name' => 'CSV',
				'class' => \nitm\importer\CsvParser::className(),
			],
			'json' => [
				'name' => 'Json',
				'class' => \nitm\importer\JsonParser::className(),
			]
		]);
	}
	
	public function setSources($sources=[])
	{
		$this->_sources = array_merge($sources, [
			'file' => 'File',
			'text' => 'Text'
		]);
	}
	
	public function getTypes($what=null)
	{
		return $this->extractValues($this->_types, $what);
	}
	
	public function getSources($what=null)
	{
		return $this->extractValues($this->_sources, $what);
	}
	
	public function getParsers($what=null)
	{
		return $this->extractValues($this->_parsers, $what);
	}
	
	protected function extractValues($from, $what)
	{
		switch($what)
		{
			case 'class':
			return array_map(function ($value) {
				return $value['class'];
			}, $from);
			break;
			
			case 'name':
			return array_map(function ($value) {
				return $value['name'];
			}, $from);
			break;
			
			default:
			return $from;
			break;
		}
	}
}
// end log class 
?>
