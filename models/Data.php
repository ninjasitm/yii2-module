<?php

/**
 * Base Data getter/operation class
 * @package mhdevnet/yii2-module
 */

namespace nitm\models;

use Yii;
use yii\base\Event;
use yii\db\ActiveRecord;
use yii\db\ActiveQuery;
use nitm\helpers\ArrayHelper;
use ReflectionClass;

class Data extends ActiveRecord implements \nitm\interfaces\DataInterface
{
	use \nitm\traits\Configer,
	\nitm\traits\Query,
	\nitm\traits\Relations,
	\nitm\traits\Cache,
	\nitm\traits\Data,
	\nitm\traits\DB,
	\nitm\traits\Parents,
	\nitm\traits\Log,
	\nitm\traits\Alerts;

	//public members
	public $initLocalConfig = true;
	public $initLocalConfigOnEmpty = false;

	public static $initClassConfig = true;
	public static $initClassConfigOnEmpty = false;

	protected static $supported;

	//Event
	const AFTER_ADD_PARENT_MAP = 'afterAddParentMap';

	//private members

	public function init()
	{
		if(!$this->noDbInit)
			parent::init();
		$nitm = \Yii::$app->getModule('nitm');
		if(((bool)$this->initLocalConfig || (bool)static::$initClassConfig) && $nitm->hasComponent('config') && !$nitm->config->exists($this->isWhat(true), $this->initLocalConfigOnEmpty || static::$initClassConfigOnEmpty)) {
			$this->initConfig($this->isWhat(true));
		}
	}

	public function rules()
	{
		return [
			[['filter'], 'required', 'on' => ['filtering']],
			[['unique'], 'safe']
		];
	}

	public function scenarios()
	{
		return [
			'default' => [],
			'filter' => ['filter'],
			'create' => ['author_id'],
			'update' => ['editor_id'],
			'deleted' => ['deleted']
		];
	}

	public function attributes()
	{
		return array_merge(parent::attributes(), [
			'_count', '_newCount'
		]);
	}

	public function behaviors()
	{
		$behaviors = [
		];
		$has = is_array(static::has()) ? static::has() : [];
		foreach($has as $name=>$dataProvider)
		{
			$name = is_numeric($name) ? $dataProvider : $name;
			switch($this->hasProperty($name) || $this->hasAttribute($name))
			{
				case true:
				switch($name)
				{
					case 'updates':
					case 'edits':
					$behaviors[$name] = [
						'class' => \yii\behaviors\AttributeBehavior::className(),
						'attributes' => [
							ActiveRecord::EVENT_BEFORE_UPDATE => [$name],
						],
						'value' => function ($event) use($name) {
							switch($event->sender->hasProperty($name))
							{
								case true:
								return $event->sender->edits++;
								break;
							}
						},
					];
					break;

					case 'author':
					case 'editor':
					//Setup author/editor
					$behaviors["blamable"] = [
					'class' => \yii\behaviors\BlameableBehavior::className(),
						'attributes' => [
							ActiveRecord::EVENT_BEFORE_INSERT => 'author_id',
						],
					];
					switch($this->hasProperty('editor_id') || $this->hasAttribute('editor_id'))
					{
						case true:
						$behaviors['blamable']['attributes'][ActiveRecord::EVENT_BEFORE_UPDATE] = 'editor_id';
						break;
					}
					break;

					case 'updated_at':
					case 'created_at':
					//Setup timestamping
					$behaviors['timestamp'] = [
						'class' => \yii\behaviors\TimestampBehavior::className(),
						'attributes' => [
							ActiveRecord::EVENT_BEFORE_INSERT => 'created_at',
						],
						'value' => new \yii\db\Expression('NOW()')
					];
					switch($this->hasProperty('updated_at') || $this->hasAttribute('updated_at'))
					{
						case true:
						$behaviors['timestamp']['attributes'][ActiveRecord::EVENT_BEFORE_UPDATE] = 'updated_at';
						break;
					}
					break;

					default:
					//setup special attribute behavior
					switch(is_array($dataProvider))
					{
						case true:
						$behaviors[$name] = $dataProvider;
						break;
					}
					break;
				}
				break;
			}
		}
		return array_merge(parent::behaviors(), $behaviors);
	}

	public function afterSave($insert, $attributes)
	{
		/**
		 * If this has parents specified then check and add them accordingly
		 */
		if(!empty($this->parent_ids))
			$this->addParentMap($this->parent_ids);
		return parent::afterSave($insert, $attributes);
	}

	public static function tableName()
	{
		return static::$tableName;
	}

	/*
	 * Check to see if somethign is supported
	 * @param mixed $what
	 */
	public function isSupported($what)
	{
		$thisSupports = [$what => false];
		switch(is_array(static::$supported))
		{
			case true:
			$thisSupports = static::$supported;
			break;

			default:
			$thisSupports = @$this->setting('supported');
			break;
		}
		return (isset($thisSupports[$what]) &&  ($thisSupports[$what] == true));
	}
}
?>
