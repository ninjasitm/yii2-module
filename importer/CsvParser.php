<?php
/**
 * @link http://www.ninjasitm.com/
 * @copyright Copyright (c) 2014 NinjasITM INC
 * @license http://www.ninjasitm.com/license/
 */

namespace nitm\importer;

/**
 * CsvParser parses a CSV file.
 */
class CsvParser extends BaseParser
{
	private $_handle;
	
	public function parse($data)
	{
		$this->data = $data;
		$isFile = file_exists($data);
		if(($firstLine = $this->read($isFile)) !== false)
		{
			$this->fields = $firstLine;
			while((($data = $this->read($isFile)) !== false))
			{
				$data = array_filter($data);
				if(count($data) >= 1)
					$this->parsedData[] = $data;
				unset($data);
			}
		}
		$this->close();
		return $this;
	}
	
	protected function read($isFile=false)
	{
		if($isFile)
			return fgetcsv($this->handle($this->data), 100000, ',', '"');
		else
			return str_getcsv($this->data, $this->limit, ',', '"');
	}
	
	public function handle($path)
	{
		if(!is_resource($this->_handle))
			$this->_handle = fopen($path, 'r');
		return $this->_handle;
	}
	
	public function close()
	{
		if(is_resource($this->_handle))
			fclose($this->_handle);
		return true;
	}
}
