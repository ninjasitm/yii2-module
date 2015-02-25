<?php
/**
 * @link http://www.ninjasitm.com/
 * @copyright Copyright (c) 2014 NinjasITM INC
 * @license http://www.ninjasitm.com/license/
 */

namespace nitm\importer;

use \SplFileObject;

/**
 * CsvParser parses a CSV file.
 */
class CsvParser extends BaseParser
{
	private $_handle;
	private $_isFile = false;
	
	public function parse($data, $offset = 0, $limit = 150)
	{
		$this->parsedData = null;
		$this->data = $data;
		if(!count($this->fields))
			if(($firstLine = $this->read()) !== false)
				$this->fields = $firstLine;
		else			
			$this->seek($offset);
		
		$line = $offset;
		while((($line <= $limit) && !$this->isEnd()) && ((($data = $this->read()) !== false)))
		{
			$data = array_filter($data);
			if(count($data) >= 1)
				$this->parsedData[] = $data;
			unset($data);
			$line++;
		}
		return $this;
	}
	
	protected function isEnd()
	{
		if($this->_isFile)
			!$this->handle()->valid();
	}
	protected function next()
	{
		if($this->_isFile)
			$this->handle()->next();
	}
	
	protected function seek($to)
	{
		if($this->_isFile)
			$this->handle()->seek($to);
	}
	
	protected function read()
	{
		$this->handle();
		if($this->_isFile) {
			$ret_val = $this->handle($this->data)->fgetcsv( ',', '"');
			return $ret_val;
		}
		else
			return str_getcsv($this->data, $this->limit, ',', '"');
	}
	
	public function handle($path=null)
	{
		if(is_object($this->_handle))
			return $this->_handle;
			
		$path = is_null($path) ? $this->data : $path;
		$this->_isFile = file_exists($path);
		
		if($this->_isFile && !is_object($this->_handle)) {
			$this->_handle = new SplFileObject($path, 'r');
			$this->_handle->setFlags(SplFileObject::SKIP_EMPTY);
		}
			
		return $this->_handle;
	}
	
	public function close()
	{
		if(is_object($this->_handle))
			//$this->_handle->fclose();
		$this->parsedData = [];
		return true;
	}
}
