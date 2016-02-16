<?php

/**
 * @package mhdevent/yii2-module
 *
 * Database traits for the Data model
 */
namespace nitm\traits;

use nitm\helpers\Cache as RealCache;
use yii\helpers\ArrayHelper;

trait DB {

	public $noDbInit = true;

    /**
     * @var array
     */
    protected static $active = [
        'driver' => 'mysql',
        'db' => [
            'name' => null
        ],
        'table' => [
            'name' => null
        ]
    ];

    /**
     * @var array
     */
    protected static $old = [
        'db' => [
            'name' => null
        ],
        'table' => [
            'name' => null
        ]
    ];

	protected $connection;

    /**
	 * Set the current database
	 * @param string $db
	 * @param string $table
	 * @param bolean force the connection
	 * @return boolean
	 */
	public function setDb($db='__default__', $table=null, $force=false)
	{
		$ret_val = false;
		switch($db)
		{
			case '__default__':
			Yii::$app->set('db2', static::getConnection($this->username, $this->password, $this->host));
			static::$active = array();
			break;

 			default:
			switch(!empty($db) && ($force || ($db != static::$active['db']['name'])))
			{
				case true:
				static::$active['db']['name'] = $db;
				switch(empty(static::$active['driver']))
				{
					case true:
					throw new \yii\base\ErrorException("Invalid driver and host parameters. Please call ".$this->className()."->changeLogin to change host and conneciton info");
					break;

					default:
					Yii::$app->set('db2', static::getConnection($this->username, $this->password, $this->host));
					break;
				}
				break;
			}
			break;
		}
		if(!empty($table))
		{
			$ret_val = static::setTable($table);
		}
		return $ret_val;
	}

	/**
	 * Returns the database connection used by this AR class.
	 * By default, the "db" application component is used as the  database connection.
	 * You may override this method if you want to use a different database connection.
	 * @return Connection the database connection used by this AR class.
	 */
	public static function getDb()
	{
		$ret_val = \Yii::$app->getDb();
		switch(\Yii::$app->has('db2'))
		{
			case true:
			switch(\Yii::$app->get('db2') instanceof \yii\db\Connection)
			{
				case true:
				$ret_val = \Yii::$app->get('db2');
				break;
			}
			break;

			default:
			$ret_val = \Yii::$app->get('db');
			break;
		}
		return $ret_val;
	}

	/*
	 * Temporarily change the database or table for operation
	 * @param string $db
	 * @param string $table
	 */
	public function changeDb($db, $table=null)
	{
		if(empty($this->user) || empty($this->host) || empty($this->password))
		{
			$this->changeLogin();
		}
		if((!empty($db)))
		{
			$this->old['db']['name'] = static::$active['db']['name'];
			static::$active['db']['name'] = $db;
			static::setDb(static::$active['db']['name'], null, true);
		}
		else
		{
			$this->old['db']['name'] = null;
		}
		if(!empty($table))
		{
			$this->old['table']['name'] = static::$active['table'];
			static::$active['table']['name'] = $table;
			static::setTable(static::$active['table']['name']);
		}
		else
		{
			$this->old['table']['name'] = null;
		}
	}

	/*
	 *Reset the database and table back
	 */
	public function revertDb()
	{
		if(!empty($this->old['db']['name']))
		{
			static::setDb($this->old['db']['name']);
		}
		if(!empty($this->old['table']['name']))
		{
			static::$active['table'] = $this->old['table'];
		}
		switch(empty(static::$active['table']['name']))
		{
			case true:
			static::setTable(static::$active['table']['name']);
			break;
		}
		$this->old['db'] = [];
		$this->old['table'] = [];
	}
    /**
	 * Change the database login information
	 * @param string $dbHost
	 * @param string $dbUser
	 * @param string $dbPass
	 */
	public function changeLogin($dbHost=NULL, $dbUser=NULL, $dbPass=NULL)
	{
		$this->host = ($dbHost != NULL) ? $dbHost : 'localhost';
		$this->username = ($dbUser != NULL) ? $dbUser : ArrayHelper::getValue(\Yii::$app->params, 'components.db.username');
		$this->password = ($dbPass != NULL) ? $dbPass : ArrayHelper::getValue(\Yii::$app->params, 'components.db.password');
	}

	/**
	 * set the current table
	 * @param string $table
	 * @return boolean
	 */
	public function setTable($table=null)
	{
		$ret_val = false;
		if(!empty($table))
		{
			switch($table)
			{
				case DB::NULL:
				case null:
				static::$active['table']['name'] = '';
				break;

				default:
				static::$active['table']['name'] = $table;
				$this->tableName = $table;
				break;
			}
			$ret_val = true;
		}
		return $ret_val;
	}

	/**
	 * Remove the second db component
	 */
	public function clearDb()
	{
		static::$connection = null;
		static::setDb();
	}

	/**
	 * Create the connection to the database
	 * @param string $username
	 * @param string $password
	 * @param string $host
	 * @return Connection
	 */
	 protected static function getConnection($username, $password, $host)
	 {
		 switch(static::$connection instanceof yii\db\Connection)
		 {
			 case false:
			 static::$connection = new \yii\db\Connection([
				'dsn' => static::$active['driver'].":host=".$host.";dbname=".static::$active['db']['name'],
				'username' => $username,
				'password' => $password,
				'emulatePrepare' => true,
				'charset' => 'utf8',
			]);
			static::$connection->open();
			break;
		 }
		return static::$connection;
	 }
}
