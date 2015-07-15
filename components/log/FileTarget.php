<?php
/**
 * @link http://www.ninjasitm.com/
 * @copyright Copyright (c) 2014 NinjasITM INC
 * @license http://www.ninjasitm.com/license/
 */

namespace nitm\components\log;

use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\FileHelper;

/**
 * FileTarget records log messages in a file.
 *
 * The log file is specified via [[logFile]]. If the size of the log file exceeds
 * [[maxFileSize]] (in kilo-bytes), a rotation will be performed, which renames
 * the current log file by suffixing the file name with '.1'. All existing log
 * files are moved backwards by one place, i.e., '.2' to '.3', '.1' to '.2', and so on.
 * The property [[maxLogFiles]] specifies how many history files to keep.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class FileTarget extends \yii\log\FileTarget
{
	public $collectionName = 'nitmlog';
	public $filename; 									// default log file name
	public $fullpath;
	
	//protected data
	protected $msgClose = "End of Log Transaction";
	protected $msgNew = "Begining New Log";
	protected $msgApp = "Adding New Log Information";
	
	//private data
	private $handle;						// this will hold the resource for the open file
	private $logDir = "@runtime/logs/logger"; 
	
	const L_FERM = -10001;	//code to write a default close message
	const L_SHIN = -10002;	//code to write a default new data message
	const L_CONC = -10003;	//code to write default append message
	const ERR_NOFNAME = "Error : Empty File (Enter filename as first argument to constructor)";	//code to indicate empty filename
	const ERR_BADDIR = "Error : Log directory (%s) doesn't exist";	//code to indicate invalid directory
	const ERR_DIRCREATE = "Error : Unable to create sub diretory (%s)";	//code to indicate invalid directory
	const SEP = DIRECTORY_SEPARATOR;
	
	public function read()
	{
		// open file
		// call file opening function
		$this->fullpath = $this->logDir.$this->filename;
		$this->handle = fopen($this->fullpath,'rb');
		if(!is_resource($this->handle))
		{
			$errmsg = "Log error: Unable to open file: {$this->fullpath}\n
			<br>Suggestion: Check file path and folder permissions.\n
			<br>script exiting(3)\n\n";
			echo $errmsg;
			$this->endLog();
			exit(3);
		}
		$contents = fread($this->handle);
		$this->endLog();
		return $contents;
	}
	
	public function __destruct()
	{
		unset($this->handle, $this);
	}
  
	// end open
	public function write($logtext="", $notime=false, $tag=false)
	{
		// call file opening function
		if(!$this->handle)
		{
            		$this->open();
		}
		$text = ($notime == false) ? date("D-M-d-Y [H:i:s]")." - $logtext\n" : $logtext."\n";
		// write log file heading
		// now add defined log content
		switch($this->ext)
		{
				case ".txt":
				$text = strip_tags($text);	
				break;
				
				case ".html":
				case ".xml":
				switch($tag == false)
				{
					case true:
					case 1:
					break;
					
					case false:
					case 0:
					$o = preg_replace("/($tag)/", "<\\1>", $tag);
					$c = preg_replace("/($tag)/", "</\\1>", $tag);
					$logtext = $o.$logtext.$c;
					break;
					
					default:
					break;
				}
				break;
				
				default: 
				$text = strip_tags($text);
				break;
		}
		if(Session::getVal("settings.globals.allow_log") == true)
		{
			if(fwrite($this->handle, wordwrap($text, 512, "\n\n")) === false)
			{
				// write faled issue error
				$errmsg = "Log error: Unable to write to file: {$this->fullpath}\n
				<br>Suggestion: Check file path and folder permissions.\n
				<br>script exiting(4)\n\n";
				echo $errmsg;
				$this->__destruct();
				exit(4);
			}
		}
		return true;
	}
	
	public function changeFile($filename, $dir=null)
	{
		if($filename)
		{
			$this->filename = $filename."-".date("Y-m-d")."-log".$this->ext;  //set filename
			$this->logDir = ($dir == null) ? $this->logDir : $dir;
		}
		else
		{
			echo self::ERR_NOFNAME."\r\n";
		}
	}
	
	public function changeDir($dir)
	{
		if(is_dir($dir))
		{
			$this->logDir = ($dir == null) ? $this->logDir : $dir;
		}
	}
	
	public function changeExt($ext)
	{
		if($ext)
		{
			$this->ext = ($ext == null) ? $this->ext : $ext;
		}
	}
	
	private function open()
	{
		// open file
		$this->fullpath = $this->logDir.$this->filename;
		if(file_exists($this->fullpath))
		{
			$this->handle = fopen($this->fullpath,'a+');
			if(!$this->handle)
			{
				return false;
			}
			$msg = $this->msgApp;
		}
		else
		{
			$this->handle = fopen($this->fullpath,'w+b');
			if(!$this->handle)
			{
				return false;
			}
			$msg = $this->msgNew;
		}
		if(!is_resource($this->handle))
		{
			$errmsg = "Log error: Unable to open file: {$this->fullpath}\n
			<br>Suggestion: Check file path and folder permissions.\n
			<br>script exiting(2)\n\n";
			echo $errmsg;
			$this->endLog();
			exit(3);
		}
		@chmod($this->fullpath, 0775);
		$this->write("\n".str_repeat("-", 100), true); 
		$this->write(date("[M-d-Y H:i:s] ").$msg." user: ".$this->currentUser->username, 1);
		$this->write("\n".str_repeat("-", 100), true);	
		return $this->fullpath;
	}
	// end open
	
	public function endLog()
	{
		if(is_resource($this->handle))
		{
			$this->write("\n".str_repeat("-", 100), true); 
			$this->write(date("[M-d-Y H:i:s] ").$this->msgClose, 1);
			$this->write("\n".str_repeat("-", 100), true);
			fclose($this->handle);
			$this->handle = false;
		}
	}
	//-- end endLog
	
	public function prepareFile($filename, $dir=null, $ext=null)
	{
		if($filename)
		{
			$this->ext = ($ext == null) ? $this->ext : $ext;
			$this->filename = $filename."-".date("Y-m-d")."-log".$this->ext;  //set filen name
			$dir = ($dir == null) ? null : (($dir[strlen($dir) -1] == self::SEP) ? $dir : $dir.self::SEP);
			$this->logDir = ($dir == null) ? $this->logDir.$filename.self::SEP : $dir.$filename.self::SEP;
			if(!is_dir($this->logDir))
			{
				if(!mkdir($this->logDir, 0775, true))
				{
					die(sprintf(self::ERR_DIRCREATE, $this->logDir)."\r\n");
				}
			}
			/*else
			{	
				die(sprintf(self::ERR_BADDIR, $this->logDir)."\r\n");
			}*/
		}
		else
		{
			//echo self::ERR_NOFNAME."\r\n";
		}
// 		parent::__construct();
// 		echo "<h1>currentUser == $this->currentUser sccess string == ".parent::securer.".username</h1>";
// 		exit;
	}
}
