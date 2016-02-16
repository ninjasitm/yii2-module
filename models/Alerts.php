<?php

namespace nitm\models;

use Yii;
use yii\db\ActiveRecord;
use yii\base\Event;

/**
 * This is the model class for table "alerts".
 *
 * @property integer $id
 * @property integer $remote_id
 * @property string $remote_type
 * @property string $remote_for
 * @property integer $user_id
 * @property string $action
 * @property integer $global
 * @property integer $disabled
 * @property string $created_at
 *
 * @property User $user
 */
class Alerts extends Entity
{
	public $requiredFor;
	public $initLocalConfigOnEmpty = true;
	public static $initClassConfigOnEmpty = true;

	protected $link = [
		'remote_type' => 'remote_type',
		'remote_id' => 'remote_id'
	];

	public function init()
	{
		parent::init();
		$this->initEvents();
	}

	protected function initEvents()
	{
		Event::on(ActiveRecord::className(), ActiveRecord::EVENT_BEFORE_VALIDATE, [$this, 'beforeValidateEvent']);
	}

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'alerts';
    }

    public function behaviors()
    {
		$behaviors = [
			'blamable' => [
				'class' => \yii\behaviors\BlameableBehavior::className(),
					'attributes' => [
						ActiveRecord::EVENT_BEFORE_INSERT => 'user_id',
					],
			]
		];
		return array_merge(parent::behaviors(), $behaviors);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser($link=[])
    {
        return $this->hasOne(\nitm\models\User::className(), ['id' => 'user_id'])
			->select(['id', 'username', 'email'])
			->where(static::$usersWhere)
			->andWhere(['disabled' => false])
			->with('profile');
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['action', 'remote_type'], 'required', 'on' => ['create', 'update']],
            [['remote_id', 'user_id', 'global', 'disabled'], 'integer'],
            [['created_at', 'remote_for'], 'safe'],
            [['action'], 'unique', 'targetAttribute' => ['remote_id', 'remote_type', 'user_id', 'action', 'priority', 'methods'], 'message' => 'This exact alert is already configured for you.', 'on' => ['create']],
			[['remote_for'], 'validateRemoteFor'],
			[['methods'], 'filter', 'filter' => [$this, 'filterMethods']],
			[['priority'], 'filter', 'filter' => [$this, 'filterPriority']]
        ];
    }

	public function scenarios()
	{
		$scenarios = [
			'create' => ['remote_id', 'remote_type', 'remote_for', 'action', 'priority', 'methods'],
			'update' => ['remote_type', 'remote_for', 'action', 'priority', 'methods']
		];
		return array_merge(parent::scenarios(), $scenarios);
	}

	public function filterMethods($value)
	{
		return \nitm\components\alerts\DispatcherData::filterMethods($value);
	}

	public function filterPriority($value)
	{
		switch($value)
		{
			case 'important':
			case 'critical':
			case 'normal':
			$ret_val = $value;
			break;

			default:
			$ret_val = 'any';
			break;
		}
		return $ret_val;
	}

	/**
	* This method is invoked before validation starts.
	*/
	public function beforeValidateEvent($event)
	{
		if($event->sender instanceof static)
		{
			$event->sender->user_id = \Yii::$app->user->getId();
			$event->sender->priority = $this->filterPriority($event->sender->priority);
		}
		return $event->isValid;
	}

	public function validateRemoteFor($attribute, $params)
	{
		$ret_val = '';
		switch(1)
		{
			case isset($this->requiredFor[$this->remote_type]):
			switch(1)
			{
				case in_array($this->$attribute, $this->requiredFor[$this->remote_type]):
				break;

				default:
				$ret_val = [];
				$ret_val['message'] = "Option requred for ".$this->remote_type;
				$ret_val['attribute'] = $this->getAttributeLabel($attribute);
				$ret_val = 'yii.validation.required(value, messages, '.json_encode($ret_val).');';
				break;
			}
			break;
		}
		return $ret_val;
	}

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'remote_id' => Yii::t('app', 'Remote ID'),
            'remote_type' => Yii::t('app', 'Remote Type'),
            'user_id' => Yii::t('app', 'User ID'),
            'action' => Yii::t('app', 'Action'),
            'global' => Yii::t('app', 'Global'),
            'disabled' => Yii::t('app', 'Disabled'),
            'created_at' => Yii::t('app', 'Created At'),
        ];
    }
}
