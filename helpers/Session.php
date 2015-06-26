<?php

namespace nitm\helpers;
use yii\base\Model;

//class that sets up and retrieves, deletes and handles modifying of contact data
class Session extends Model
{
	//setup public data
	public static $useCache;

	//setup protected data
	protected static $session = null;
	protected static $sessionName = "helper.";
	protected static $noQualifier = ['active', 'settings','securer','helper','batch', 'fields', 'configer', 'comparer'];
	protected static $qualifier = ['adder','deleter','updater','general'];
	protected static $batchQualifier = ['batch','deleter'];

	//setup private data
	private $id;
	private static $csdm;				//Current Session Data Member
	private static $compare = false;
	private static $lock = 'locked_csdm';

	//define constant data
	
	//you can use a qualifier for these
	const batch = 'batch';
	const securer = 'securer';
	const helper = 'helper';
	const settings = 'settings';
	const fields = 'fields';
	const configer = 'configer';
	const comparer = 'comparer';
	const current = 'active';
	
	//you don't have to use a qualifier for these but the csdm will be used
	const adder = 'adder';
	const deleter = 'deleter';
	const updater = 'updater';
	const general = 'general';
	
	//private class constants
	const variables = 'helper-variables';
	const reg_vars = "reg-vars";
	const name = "name";
	const object = 'oHelper';
	const csdm_var = 'csdm';
	
	public function __construct($dm=null, $db=null, $table=null, $compare=false, $driver=null)
	{
		static::touchSession();
		self::$compare = $compare;
		static::initSession($dm);
		if($compare == true)
			self::register(self::comparer);
	}
	
	protected static function initSession($dm=null)
	{
		$_SERVER['SERVER_NAME'] = (isset($_SERVER['SERVER_NAME'])) ? $_SERVER['SERVER_NAME'] : @$_SESSION['SERVER_NAME'];
		if(!isset($_SESSION[static::sessionName()]))
		{
			$_SESSION[static::sessionName()] = [];
			if(!isset($_SESSION[static::sessionName()][self::variables]))
			{
				$_SESSION[static::sessionName()][self::variables] = [];
			}
			if(!isset($_SESSION[static::sessionName()][self::variables][self::reg_vars]))
			{
				$_SESSION[static::sessionName()][self::variables][self::reg_vars] = [];
				$_SESSION[static::sessionName()][self::variables][self::reg_vars][self::object] = null;
			}
			if(!is_null($dm) && !isset($_SESSION[static::sessionName()][self::variables][self::csdm_var])) {
				$_SESSION[static::sessionName()][self::variables][self::csdm_var] = 
				$_SESSION[static::sessionName()][self::variables][self::$lock] = $dm;
			}
		}
	}
	
	protected static function touchSession()
	{
		if(session_status() != PHP_SESSION_ACTIVE)
			if(\Yii::$app->getSession())
				\Yii::$app->getSession()->open();
			else
				@session_start();
		static::initSession(static::settings);
	}
	
	public function behaviors()
	{
		$behaviors = [
		];
		return array_merge(parent::behaviors(), $behaviors);
	}
	
	public function init()
	{
		static::touchSession();
		self::$method = $_REQUEST;
	}
	
	public static function sessionName()
	{
		static::$session = (empty(static::$session)) ?  preg_replace('/[^\da-z]/i', '-', static::$sessionName.$_SERVER['SERVER_NAME']) : static::$session;
		return static::$session;
	}
	
	public static final function setCsdm($dm, $compare=false)
	{
		static::touchSession();
		self::$compare = $compare;
		$_SESSION[static::sessionName()][self::variables][self::csdm_var] = $dm;
		$_SESSION[static::sessionName()][self::variables][self::$lock] = $dm;
		self::register($dm);
		return true;
	}
	
	public static final function getCsdm()
	{
		static::touchSession();
		return $_SESSION[static::sessionName()][self::variables][self::csdm_var];
	}
	
	public static final function app($path, $data, $compare=false)
	{
		return self::set($path, $data, $compare, true);
	}
	
	public static function set($path, $data, $compare=false, $append=false)
	{
		if(is_array($path) && (sizeof($data) < sizeof($path)))
			return false;

		$csdm = ($compare === true) ? self::comparer : @static::getCsdm();
		$path = (is_null($path)) ? $csdm : $path;
		self::$compare = $compare;
		static::touchSession();
		return ArrayHelper::setValue($_SESSION, static::getPath($path), $data, $append);
	}
	
	//set batch ID's for misc use
	public function setBatch($cID)
	{
		self::setCsdm(self::batch);
		return self::set(self::batch, $cID, true);
	}
	//end section
	
	public function appBatch($cID, $path=false, $clear=false)
	{
		$csdm = ($path === false) ? self::batch : $path;
		$path = ($path === false) ? $csdm : $path;
		self::setCsdm($csdm);
		if($clear === true)
			self::delete($path);
		self::app($path, $cID, true);
		return $cID;
	}

	//void section
	public final function voidBatch($cID, $path=false)
	{
		$ret_val = $cID;
		if($path === false)
			$path = self::batch;
		if(!self::isRegistered($path))
			$ret_val = false;
		else if(($key = @array_search($cID, self::get($path))) !== false)
				self::delete("$path.$key");
		return $ret_val;
	}
	
	public static function del($path)
	{
		return static::delete($path);
	}

	public static final function delete($path)
	{
		$value = self::get($path);
		$ret_val = self::unregister($path);
		return ["item"=> $path , "value" => $value, "ret_val" => $ret_val];
	}
	
	public static final function pop($array, $index) 
	{
		if(is_array($array)) {
			unset ($array[$index]);
			array_unshift($array, array_shift($array));
			return $array;
		}
		return false;
	}
	
	public static final function clear($path)
	{
		static::touchSession();
		switch($path)
		{
			case in_array($path, self::$noQualifier) === true:
			case in_array($path, self::$qualifier) === true:
			$_SESSION[static::sessionName()][$path] = [];
			break;
			
			case null:
			$_SESSION[static::sessionName()] = [];
			break;
			
			default:
			$_SESSION[static::sessionName()][self::getCsdm()] = [];
			break;
		}
		return true;
	}

	public static final function size($item, $sizeOnly=true)
	{
		if(!self::isRegistered($item))
			return 0;
		else {
			$size = count(static::getValue($item));
			$ret_val = $sizeOnly ? $size : ['value' => self::get($item), 'size' => $size, 'idx' => $item];
			return $ret_val = (!$ret_val) ? 0 : $ret_val;
		}
	}
	
	public static function exists($path)
	{
		return static::isRegistered($path);
	}
	
	/*
	 * Using dot notation see if this path exists
	 * @param string $path
	 * @return bool
	 */
	public static final function isRegistered($path)
	{
		$ret_val = false;
		$hierarchy = static::resolvePath($path);
		static::touchSession();
		$ret_val = ArrayHelper::exists($_SESSION, static::getPath($path), false);
		return $ret_val;
	}
	
	public static function getPath($path) 
	{
		$hierarchy = is_array($path) ? $path : static::resolvePath($path);
		
		if(static::getCsdm() != $hierarchy[0])
			if(!in_array($hierarchy[0], self::$noQualifier) && !in_array($hierarchy[0], self::$qualifier))
				array_unshift($hierarchy, static::getCsdm());
		
		array_unshift($hierarchy, static::sessionName());
		return implode('.', $hierarchy);
	}
	
	protected static function resolvePath($path)
	{
		switch($path)
		{
			case in_array($path, self::$noQualifier) === true:
			case in_array($path, self::$qualifier) === true:
			$ret_val = [$path];
			break;
			
			default:
			$ret_val = explode(".", $path);
			switch($ret_val[0])
			{	
				case in_array($ret_val[0], self::$qualifier) === true:
				case in_array($ret_val[0], self::$noQualifier) === true:
				case ($ret_val[0] == @static::getCsdm()):
				break;
				
				default:
				array_unshift($ret_val, @static::getCsdm());
				break;
			}
			break;
		}
		return $ret_val;
	}
	
	/**
	 * An alias for get
	 */
	public static final function getVal($path)
	{
		return self::get($path, false);
	}
	
	/*
	 * Get a value
	 * @param string|int $path
	 */
	public static final function get($path, $asArray = false)
	{
		self::$qualifier = array('adder','deleter','updater','general');
		$val = static::getValue($path);
		$ret_val = (($asArray === false)) ? $val : ['idx' => $path, 'value' => $val];
		return $ret_val;
	}
	
	private static function getValue($string)
	{
		static::touchSession();
		//echo "Getting ".json_encode($string)." ".static::getCsdm()." ".static::getPath($string)."\n";
		return ArrayHelper::getValue($_SESSION, static::getPath($string), null);
	}
	
	/*
	 * Destroy the session
	 */
	public static final function destroy()
	{
		static::touchSession();
		unset($_SESSION[static::sessionName()]);
	}
	
	/*---------------------
		Protected Functions
	---------------------*/
	
	protected static function inSession($fields, $data, $array=false, $strict=false)
	{
		static::touchSession();
		if($data == null)
			return false;
		$ret_val = false;
		switch($fields)
		{
			case in_array($fields, self::$batchQualifier) === true:
			foreach($_SESSION[static::sessionName()][$fields] as $idx=>$val)
			{
				if($data == $val)
				{
					$ret_val = true;
					break;
				}
			}
			break;
			
			default:
			if(self::isRegistered($fields))
			{
				if(($search = self::reference($fields)) !== false)
				{
					if(!is_array($search) && ($search == $data))
						$ret_val = true;
					else if(is_array($search))
					{
						foreach($search as $idx=>$val)
						{
							if($data == $val)
							{
								$ret_val = true;
								break;
							}
						}
					}
				}
			}
			break;
		}
		return $ret_val;
	}

	/*---------------------
		Private Functions
	---------------------*/
	
	/*
	 * Using dot notation register this path
	 * @param string $path
	 */
	private static function register($path)
	{
		if(is_null($path))
			return false;
		
		if(!self::isRegistered($path))
		{
			switch($path)
			{
				case in_array($path, self::$noQualifier) === true:
				case in_array($path, self::$qualifier) === true:
				case in_array($path, self::$batchQualifier) === true:
				$_SESSION[static::sessionName()][$path] = [];
				break;
				
				default:
				ArrayHelper::setValue($_SESSION, static::getPath($path), '');
				break;
			}
			return true;
		}
		return false;
	}
	
	/*
	 * Using dot notation unregister this path
	 * @param string $path
	 * @return bool
	 */
	private static final function unregister($path)
	{
		$ret_val = false;
		if(self::isRegistered($path) !== false)
			return ArrayHelper::remove($_SESSION, static::getPath($path));
			
	}
	
	/*
	 * Using dot notation get a reference to this path
	 * @param string $path
	 * @return objet
	 */
	private static function &reference($key)
	{
		$ret_val = false;
		if(!empty($key))
		{
			static::touchSession();
			switch($key)
			{
				case in_array($key, self::$qualifier) === true:
				case null:
				$csdm = static::getCsdm();
				$ret_val = $_SESSION[static::sessionName()][$csdm];
				break;
				
				case in_array($key, self::$noQualifier) === true:
				$csdm = $key;
				$ret_val = $_SESSION[static::sessionName()][$key];
				break; 
				
				default:
				$hierarchy = explode(".", $key);
				switch($hierarchy[0])
				{
					case in_array($hierarchy[0], self::$noQualifier) === true:
					$csdm = $hierarchy[0];
					array_shift($hierarchy);
					break; 
					
					default:
					$csdm = static::getCsdm();
					if($hierarchy[0] == $csdm)
						array_shift($hierarchy);
					break;
				}
				if($csdm) {
					$ret_val = $_SESSION[static::sessionName()][$csdm];
					foreach($hierarchy as $k)
					{
						if($k)
							if(isset($ret_val[$k]))
								$ret_val = $ret_val[$k];
							else {
								$ret_val = false;
								break;
							}
						else
							$ret_val = false;
					}
				}
				break;
			}
		}
		return $ret_val;
	}
}
?>
