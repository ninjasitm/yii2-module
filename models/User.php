<?php
namespace nitm\models;

use yii\db\ActiveRecord;
use yii\helpers\Security;
use yii\web\IdentityInterface;
use dektrium\user\models\Profile;
use nitm\helpers\Cache;

/**
 * Class User
 * @package nitm\models
 *
 * @property integer $id
 * @property string $username
 * @property string $password_hash
 * @property string $password_reset_token
 * @property string $token
 * @property string $email
 * @property string $auth_key
 * @property integer $role
 * @property integer $status
 * @property integer $create_time
 * @property integer $update_time
 * @property string $password write-only password
 */
class User extends \dektrium\user\models\User
{
	use \nitm\traits\Data, \nitm\traits\Query, \nitm\traits\relations\User;
	
	public $updateActivity;
	public $useFullnames;
	
	protected $useToken;
	
	private $_lastActivity = '__lastActivity';
	
	public function init()
	{
		if($this->updateActivity) $this->updateActivity();
	}

	/**
	 * Get the records for this provisioning template
	 * @param boolean $idsOnly
	 * @return mixed Users array
	 */
	public function getAll($idsOnly=true)
	{
		$ret_val = array();
		switch(Cache::cache()->exists('nitm-user-list'))
		{
			case false:
			$users = $this->find()->asArray()->all();
			switch($idsOnly)
			{
				case true:
				foreach($users as $user)
				{
					$ret_val[$user['id']] = $user['f_name'].' '.$user['l_name'];
				}
				break;
				
				default:
				$ret_val = $users;
				break;
			}
			Cache::cache()->set('nitm-user-list', urlencode($url), 3600);
			break;
			
			default:
			$ret_val = urldecode(Cache::cache()->get('nitm-user-list'));
			break;
		}
		return $ret_val;
	}
	
	/**
	 * Get the actvity counter
	 * @param boolean $update Should the activity be updated
	 * @return boolean
	 */
	public function lastActive($update=false)
	{
		$ret_val = strtotime('now');
		try {
			$sessionActivity = \Yii::$app->getSession()->get($this->_lastActivity);
			switch(is_null($sessionActivity))
			{
				case true:
				$user = \Yii::$app->user->identity;
				$ret_val = !$user->getId() ? strtotime('now') : $user->logged_in_at;
				break;
				
				default:
				$ret_val = $sessionActivity;
				break;
			}
			if($update) $this->updateActivity();
		} catch (\Exception $error) {}
		return date('Y-m-d G:i:s', $ret_val);
	}
	
	/**
	  * Update the user activity counter
	  */
	public function updateActivity()
	{
		return \Yii::$app->getSession()->set($this->_lastActivity, strtotime('now'));
	}
	
	/**
	 * Should we use token authentication for this user?
	 * @param boolean $use
	 */
	public function useToken($use=false)
	{
		$this->useToken = ($use === true) ? true : false;
	}
	
	public function avatarImg($options=[])
	{
		return \yii\helpers\Html::img($this->avatar(), $options);
	}
	
	/**
	 * Get the avatar
	 * @param mixed $options
	 * @return string
	 */
	public function avatar() 
	{
		switch(Cache::cache()->exists('user-avatar'.$this->getId()))
		{
			case false:
			switch($this->hasAttribute('avatar') && !empty($this->avatar))
			{
				case true:
				//Support for old NITM avatar/local avatar
				$url = $this->avatar;
				break;
				
				//Fallback to dektriuk\User gravatar info
				default:
				$profile = $this->profile instanceof Profile ? $this->profile : Profile::find()->where(['user_id' => $this->getId()])->one();
				switch(!is_null($profile))
				{
					case true:
					switch(1)
					{
						case !empty($profile->gravatar_id):
						$key = $profile->gravatar_id;
						break;
						
						case !empty($profile->gravatar_email):
						$key = $profile->gravatar_email;
						break;
						
						default:
						$key = $profile->public_email;
						break;
					}
					break;
					
					default:
					$key = \Yii::$app->user->identity->email;
					break;
				}
				$url = "https://gravatar.com/avatar/$key";
				break;
			}
			Cache::cache()->set('user-avatar'.$this->getId(), urlencode($url), 3600);
			break;
			
			default:
			$url = urldecode(Cache::cache()->get('user-avatar'.$this->getId()));
			break;
		}
		return $url;
	}
	
	/**
	 * Does this user have tokens?
	 * @param User $user object
	 * @return string
	 */
	public function hasApiTokens()
	{
		return security\User::hasApiTokens($this);
	}
	
	/**
	 * Get the fullname of a user
	 * @param boolean $withUsername
	 * @return string
	 */
	public function fullName($withUsername=false)
	{
		switch(is_object(\yii\helpers\ArrayHelper::getValue($this->getRelatedRecords(), 'profile', null)))
		{
			case true:
			$ret_val = $this->profile->name.($withUsername ? '('.$this->username.')' : '');
			break;
			
			default:
			$ret_val = '';
			break;
		}
		return $ret_val;
	}
}
