<?php

namespace nitm\traits\relations;

use nitm\helpers\Cache;
use nitm\models\Profile as ProfileModel;
use yii\helpers\ArrayHelper;

/**
 * Traits defined for expanding active relation scopes until yii2 resolves traits issue
 */

trait User {
	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getProfile()
	{
		return $this->hasOne(\Yii::$app->getModule('user')->modelMap['Profile'], ['user_id' => 'id']);
	}
	
	
	/**
	 * Get the status value for a user
	 * @return string
     */
	public function status()
	{
		return \nitm\models\User::getStatus($this);
	}
	
	public function indicator($user)
	{
		return \nitm\models\User::getIndicator($user);
	}
	
	/**
     * Get the role value for a user
	 * @return string name of role
     */
	public function role()
	{
		return \nitm\models\User::getRole($this);
	}
	
	/**
	 *
	 */
	public function isAdmin()
	{
		return \nitm\models\User::getIsAdmin($this);
	}
	
	/**
	 * Does this user have tokens?
	 * @param User $user object
	 * @return string
	 */
	public function getApiTokens()
	{
		return $this->hasMany(\nitm\models\api\Token::className(), ['userid' => 'id'])->all();
	}
	
	public function url($fullName=false, $url=null, $options=[]) 
	{
		$url = is_null($url) ? 'user/profile/'.$this->getId() : $url;
		$urlOptions = array_merge([$url], $options);
		$text = ($fullName === false) ? $this->username : $this->fullname();
		$htmlOptions = [
			'href' => \Yii::$app->urlManager->createUrl($urlOptions), 
			'role' => 'userLink', 
			'id' => 'user'.uniqid()
		];
		return \yii\helpers\Html::tag('a', $text, $htmlOptions);
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
			switch($this->hasAttribute('avatar') && !empty($this->avatar) && file_exists($this->avatar))
			{
				case true:
				//Support for old NITM avatar/local avatar
				$url = $this->avatar;
				break;
				
				//Fallback to dektriuk\User gravatar info
				default:
				$profile = $this->profile instanceof ProfileModel ? $this->profile : ProfileModel::find()->where(['user_id' => $this->getId()])->one();
				$url = $this->getAvatar($this->email, $profile);
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
	
	public static function getAvatar($key, $profile=null)
	{
		if(is_array($key) || is_object($key)) {
			if(is_null($profile))
				$profile = $key['profile'];
			else
				$profile = ArrayHelper::toArray($profile);
			if(is_array($profile))
			{
				switch(1)
				{
					case !empty($profile['gravatar_id']):
					$key = $profile['gravatar_id'];
					break;
					
					case !empty($profile['gravatar_email']):
					$key = $profile['gravatar_email'];
					break;
					
					default:
					$key = $profile['public_email'];
					break;
				}
			}
		}
		return "https://gravatar.com/avatar/".md5($key);
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
			$ret_val = $this->username;
			break;
		}
		return $ret_val;
	}
	
	public function getSort()
	{
		$sort = [
			'username' => [
				'asc' => [$this->tableName().'.username' => SORT_ASC],
				'desc' => [$this->tableName().'.username' => SORT_DESC],
				'default' => SORT_DESC,
				'label' => 'Username'
			],
		];
		return array_merge(\nitm\models\Data::getSort(), $sort);
	}
}
?>
