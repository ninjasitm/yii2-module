<?php

namespace nitm\models\log;

use Yii;
use nitm\models\User;

/**
 * This is the model class for table "logs".
 *
 * @property integer $id
 * @property string $ip_addr
 * @property string $host
 * @property integer $user_id
 * @property string $action
 * @property string $db_name
 * @property string $table_name
 * @property string $message
 * @property string $prefix
 * @property string $category
 * @property integer $level
 * @property string $log_time
 *
 * @property User $user
 */
class DbEntry extends \nitm\models\log\Entry
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'logs';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['ip_addr', 'host', 'user_id', 'action', 'db_name', 'message', 'prefix', 'category', 'log_time'], 'required'],
            [['ip_addr', 'message', 'prefix', 'category'], 'string'],
            [['user_id', 'level'], 'integer'],
            [['log_time'], 'safe'],
            [['host'], 'string', 'max' => 128],
            [['action', 'db_name', 'table_name'], 'string', 'max' => 24],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::className(), 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'ip_addr' => Yii::t('app', 'Ip Addr'),
            'host' => Yii::t('app', 'Host'),
            'user_id' => Yii::t('app', 'User ID'),
            'action' => Yii::t('app', 'Action'),
            'db_name' => Yii::t('app', 'Db Name'),
            'table_name' => Yii::t('app', 'Table Name'),
            'message' => Yii::t('app', 'Message'),
            'prefix' => Yii::t('app', 'Prefix'),
            'category' => Yii::t('app', 'Category'),
            'level' => Yii::t('app', 'Level'),
            'log_time' => Yii::t('app', 'Log Time'),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::className(), ['id' => 'user_id']);
    }
}
