<?php

namespace nitm\helpers;

use yii\base\Behavior;

/*
* This is the directory interface class that finds directory information and returns it
*/
class Directory extends Behavior {
	
	public $parent = false;
	public $directory = null;
	public $files = [];
	public $directories = [];
	
	protected $appendPath = false;
	protected $appendExtension = false;
	protected $match = null;
	protected $group = false;
	protected $empty = false;
	protected $skipThese = null;
	protected $currentDirectory = null;
	protected $imagesOnly = false;
	
	/*---------------------
		Public Functions
	---------------------*/
	/*
	* Get all the files in a directory in a one dimensional array
	* @param string $directory the directory to create an array out of
	* @param boolean $appath should the full path of the file ba appended or omitted?
	* @param boolean $empty include empty files?
	* @return mixed $ret_val;
	*/
	public function getDirectories($directory,  $appendPath=false, $empty=false, $recursively=false)
	{
		$directory = \Yii::getAlias($directory);
		if(is_dir($directory))
		{
			$this->setAppendPath($appendPath);
			$this->returnEmpty($empty);
			$directories = glob($directory."/*", GLOB_ONLYDIR);
			foreach((array)$directories as $dir)
			{
				$dir = array_pop(explode(DIRECTORY_SEPARATOR, $dir));
				$key = $this->appendPath ? rtrim('/', $directory).DIRECTORY_SEPARATOR.$dir : $dir;
				$value = $key;
				$this->directories[$key] = $value;
			}
		}
		return $this->directories;
	}
	
	/*
	* Get all the files in a directory in a one dimensional array
	* @param string $directory the directory to create an array out of
	* @param boolean $appath should the full path of the file ba appended or omitted?
	* @param boolean $empty include empty files?
	* @return mixed $ret_val;
	*/
	public function getAllFiles($directory,  $appendPath=false, $empty=false)
	{
		$this->setAppendPath($appendPath);
		$this->returnEmpty($empty);
		$this->files = $this->readDirectory($directory);
		return $this->files;
	}
	
	/*
	* Get all the files and group by directory
	* @param string $directory the directory to create an array out of
	* @param boolean $appath should the full path of the file ba appended or omitted?
	* @param boolean $empty include empty files?
	* @return mixed $ret_val;
	*/
	public function getFilesGrouping($directory, $appendPath=false, $empty=false)
	{
		$this->setAppendPath($appendPath);
		$this->returnEmpty($empty);
		$this->setGrouping(true);
		$this->files = $this->readDirectory($directory);
		return $this->files;
	}
	
	/*
	* Get files which match a certain pattern
	* @param string $directory the directory to create an array out of
	* @param mixed $match the files to match
	* @param boolean $appath should the full path of the file ba appended or omitted?
	* @param boolean $group should files be grouped?
	* @param boolean $empty include empty files?
	* @return mixed $ret_val;
	*/
	public function getFilesMatching($directory, $match=array(), $appendPath=false, $group=false, $empty=false)
	{
		$this->setAppendPath($appendPath);
		$this->returnEmpty($empty);
		$this->setGrouping($group);
		$this->matchThese($match);
		$this->files = $this->readDirectory($directory);
		return $this->files;
	}
	
	/*
	* Get files which match a certain pattern
	* @param string $directory the directory to create an array out of
	* @param mixed $match the files to match
	* @param boolean $appath should the full path of the file ba appended or omitted?
	* @param boolean $group should files be grouped?
	* @param boolean $empty include empty files?
	* @return mixed $ret_val;								$name = array('full' => $file->getFilename(), 'short' => $file->getPathname()):

	*/
	public function getImages($directory)
	{
	$this->imagesOnly = true;
	}
	
	/*
	 * Set the skip array
	 * @param mixed $skip
	 */
	public function setSkip($skip = null)
	{
		$this->skipThese = is_array($skip) ? $skip : array();
	}
	
	/*
	 * Should we append paths to the files in the array?
	 * @param boolean $app
	 */
	public function setAppendPath($appendPath = false)
	{
		$this->appendPath = ($appendPath == true) ? true : false;
	}
	
	/*
	 * Should we append extensions to the files in the array?
	 * @param boolean $app
	 */
	public function setAppendExtension($appendExtension = false)
	{
		$this->appendExtension = ($appendExtension == true) ? true : false;
	}
	
	/*
	 * Should we group the files in the array?
	 * @param boolean $group
	 */
	public function setGrouping($group = false)
	{
		$this->group = ($group == true) ? true : false;
	}
	
	/*
	 * Should we include empty files??
	 * @param boolean $empty
	 */
	public function returnEmpty($empty = false)
	{
		$this->empty = ($empty == true) ? true : false;
	}
	
	/*
	 * Set the files to match against
	 * @param mixed $match
	 */
	public function matchThese($match)
	{
		$this->match = empty($match) ? array(null) : (is_array($match) ? $match : array($match));
	}
	
	public static function dirExists($directory)
	{
		$directory = static::solveDirectory($directory);
		return is_dir($directory) && is_readable($directory);
	}
	
	/*---------------------
		Protected Functions
	---------------------*/
	
	/*
	* Get the proper directory
	* @param string $directory
	* @return string
	*/
	protected static function solveDirectory($directory)
	{
		$directory = ($directory[strlen($directory)-1] == DIRECTORY_SEPARATOR) ? $directory : $directory.DIRECTORY_SEPARATOR;
		return \Yii::getAlias($directory);
	}
	
	/*
	 * Is this file an image?
	 */
	protected static function isImage($file)
	{
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		return array_shift(explode('/', finfo_file($finfo, $file))) == 'image';
	}
	
	
	/*---------------------
		Private Functions
	---------------------*/
	
	/*
	 * Should we skip this directory?
	 */
	private function shouldSkip($directory)
	{
		return (is_array($this->skipThese)) ? (in_array($directory, $this->skipThese)) : false;
	}
	
	/*
	*Read a directory based on certain conditions
	* @param string $directory the directory to create an array out of
	* @param boolean $appath should the full path of the file ba appended or omitted?
	* @param boolean $group should files be grouped?
	* @param boolean $empty include empty files?
	* @return mixed $ret_val;
	*/
	private function readDirectory($directory)
	{
		$ret_val = [];
		if(!$this->dirExists($directory))
			return $ret_val;
		$dir = new \DirectoryIterator($this->solveDirectory($directory));
		foreach($dir as $file)
		{
			if(!$file->isDot())
			{
				$this->currentDirectory = $file->getPath().DIRECTORY_SEPARATOR;
				switch($file->isDir())
				{ 
					case true:
					if($this->shouldSkip($file->getPath()))
					{
						continue;
					}
					$this->directories[] = $file->getPath();
					$data = $this->readDirectory($this->currentDirectory);
					if(!empty($data))
					{
						$ret_val = array_merge($ret_val, $data);
					}
					break;
					
					default:
					switch(!$this->empty && ($file->getSize() > 0))
					{
						case true:
						switch($this->imagesOnly)
						{
							case true:
							switch($this->isImage($file->getFilename))
							{
								case false:
								continue;
								break;
							}
							break;
						}
						foreach($this->match as $needle)
						{
							$add = ($needle != null) ? ($file->getExtension() == $needle) : true;
							if($add)
							{
								$indexmarker = ($this->parent == null) ? $this->currentDirectory : (($file->getPath() == $this->parent) ? $file->getPath() : str_ireplace($this->parent, '', $file->getPath()));
								$indexmarker = substr($indexmarker, 0, strlen($indexmarker) -1);
								
								$name = $this->appendExtension ? $file->getFilename() : $file->getBaseName('.'.$file->getExtension());
								if($this->appendPath)
									$name = $file->getPathname().$name;

								switch($this->group)
								{
									case true:
									$ret_val[$indexmarker][$name] = $name;
									break;
									
									default:
									$ret_val[$name] = $name;
									break;
								}
							}
						}
						break;
					}
					break;
				}
			}
		}
		return $ret_val;
	}
}
?>