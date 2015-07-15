<?php

namespace nitm\models\configer;

use Yii;
use nitm\helpers\Directory;
use nitm\helpers\FileHelper;
use nitm\helpers\ArrayHelper;
use nitm\models\configer\formats\JsonFile;
use nitm\models\configer\formats\PlainFile;

/**
 * Text file parser for configer
 */
class File extends \yii\base\Model
{
	public $type = 'json';
	/**
	 * These value allow the file to interprest a Container, Section or Value
	 */
	public $container_id;
	public $section_id;
	
	public $sections = [];
	public $values = [];
	
	public $container;
	public $value;
	public $name;
	public $id;
	
	protected $canWrite;
	protected $contents;
	protected $dir;
	
	private $filemode = 0775;
	private $handle = false;
	private $_parser;
	
	public function init()
	{
		if(array_key_exists($this->type, $this->types())) {
			$class = $this->types($this->type);
			$this->_parser = new $class();
		} else
			throw new \yii\base\InvalidConfigException(__CLASS__.": Unsupported type ".$this->type);
	}
	
	protected function types($type=null)
	{
		$types = [
			'json' => formats\JsonFile::className(),
			'plain' => formats\PlainFile::className()
		];
		return ArrayHelper::getValue($types, $type, $types);
	}
	
	public function canWrite()
	{
		return $this->canWrite;
	}
	
	public function getId()
	{
		return basename($this->file);
	}
	
	public function write($container, $backups=false)
	{
		$ret_val = false;
		if(is_resource($this->open($container, 'write')))
		{
			fwrite($this->handle, stripslashes($this->contents));
			$ret_val = true;
		}
		$this->close();
		if($this->backups && !empty($this->contents))
		{
			$backup_dir = "/backup/".date("F-j-Y")."/";
			$container_backup = ($container[0] == '@') ? $this->defaultDir.$backup_dir.substr($container, 1, strlen($container)) : dirname($container).$backup_dir.basename($container);
			$container_backup .= '.'.date("D_M_d_H_Y", strtotime('now')).$this->backupExtention;
			if(!is_dir(dirname($container_backup)))
			{
				mkdir(dirname($container_backup), $this->filemode, true);
			}
			fopen($container_backup, 'c');
			chmod($container_backup, $this->filemode);
			if(is_resource($this->open($container_backup, 'write')))
			{
				fwrite($this->handle, stripslashes($this->contents));
			}	
			$this->close();
		}
		return $ret_val;
	}
	
	public function getNames($in)
	{
		return $this->getFiles($in, true);
	}
	
	public function getFiles($in, $namesOnly)
	{
		$directory = new Directory();
		$ret_val = [];
		switch(is_dir($in))
		{
			case true:
			foreach(scandir($in) as $container)
			{
				switch($namesOnly)
				{
					case true:
					switch(is_dir($in.$container))
					{
						case true:
						$ret_val[$container] = $container;
						break;
					}
					break;
					
					default:
					switch(1)
					{
						case is_dir($in.$container):
						switch($multi)
						{
							case true:
							$ret_val[$container] = $directory->getFilesMatching($in.$container, $multi, $containers_objectsnly);
							break;
						}
						break;
						
						case $container == '..':
						case $container == '.':
						break;
						
						default:
						$info = pathinfo($in.$container);
						switch(1)
						{
							case true:
							$label = str_replace(['-', '_'], ' ', $info['filename']);
							$ret_val[$info['filename']] = $label;
							break;
						}
						break;
					}
					break;
				}
			}
			break;
		}
		return $ret_val;
	}
	
	public function load($file, $force)
	{
		$ret_val = '';
		switch(file_exists($file))
		{
			case true:
			switch((filemtime($file) >= fileatime($file)) || ($force === true))
			{
				case true: 
				if(is_resource($this->open($file, 'read')))
				{
					$this->contents = file($file, 1);
					$this->close();
					$ret_val = $this->contents;
				}
				break;
			}
			break;
		}
		return $ret_val;
	}
	
	public function prepare($data)
	{
		$this->content = $this->_parser->prepare($data);
	}
	 
	public function read($contents, $commentchar='#')
	{
		return $this->_parser->read($contents);
	}
	
	public function createSection($name)
	{
		return $this->_parser->createSection($section, $name);
	}
	
	public function createValue($section, $name, $value)
	{
		return $this->_parser->createValue($section, $name, $value);
	}
	
	public function updateSection($section, $name)
	{
		return $this->_parser->updateSection($section, $name);
	}
	
	public function updateValue($section, $name, $value)
	{
		return $this->_parser->updateValue($section, $name, $value);	
	}
	
	public function deleteSection($section, $name)
	{	
		return $this->_parser->deleteSection($section, $name);
	}
	
	public function deleteValue($section, $name, $value)
	{
		return $this->_parser->deleteValue($section, $name, $value);
	}
	
	public function deleteFile($file)
	{
		$args['command'] = "rm -f '%s'";
		return $this->_parser->command($command, [$file]);	
	}
	
	public function createFile($name)
	{
		$ret_val = false;
		switch(file_exists($name))
		{
			case false:
			switch($this->open($name, 'c'))
			{
				case true:
				$ret_val = true;
				chmod($name, $this->filemode);
				break;
			}
			break;
		}
		return $ret_val;
	}
	
	private function open($container, $rw='read')
	{
		if(!$container)
		{
			die("Attempting to open an empty file\n$this->open($container, $rw, $mode) (2);");
		}
		//the handle that neds to be returned if succesful
		$ret_val = false;
		$continue = false;
		$flmode = null;
		switch($rw)
		{
			case $this->canWrite:
			$mode = 'w';
			switch(file_exists($container))
			{
				case true:
				$this->handle = @fopen($container, $mode);
				switch(is_resource($this->handle))
				{
					case true:
					$continue = true;
					$flmode = LOCK_EX;
					break;
					
					default:
					@chmod($container, $this->filemode);
					$this->handle = @fopen($container, $mode);
					switch(is_resource($this->handle))
					{
						case true:
						$continue = true;
						$flmode = LOCK_EX;
						break;
						
						default:
						break;
					}
					break;
				}
				break;
			}
			switch(is_resource($this->handle))
			{
				case false:
				die("Cannot open $container for writing\n$this->open($container, $rw) (2);");
				break;
			}
			break;
		
			default:
			$mode = 'r';
			$this->handle = @fopen($container, $mode);
			switch(is_resource($this->handle))
			{
				case true:
				$continue = true;
				$flmode = LOCK_SH;
				break;
				
				default:
				//die("Cannot open $container for reading\n<br>$this->open($container, $rw) (2);");
				break;
			}
			break;
		}
		if($continue)
		{
			$interval = 10;
			while(flock($this->handle, $flmode) === false)
			{
				//sleep a little longer each time (in microseconds)
				usleep($interval+10);
			}
			$ret_val = $this->handle;
		}
		return $ret_val;
	}
	
	private function close()
	{
		if(is_resource($this->handle))
		{
			flock($this->handle, LOCK_UN);
			fclose($this->handle);
		}
	}
}
