<?php

namespace nitm\traits\relations;

use dektrium\user\models\Profile;
use nitm\helpers\Cache;

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
		return \nitm\models\security\User::getStatus($this);
	}
	
	public function indicator($user)
	{
		return \nitm\models\security\User::getIndicator($user);
	}
	
	/**
     * Get the role value for a user
	 * @return string name of role
     */
	public function role()
	{
		return \nitm\models\security\User::getRole($this);
	}
	
	/**
	 *
	 */
	public function isAdmin()
	{
		return \nitm\models\security\User::getIsAdmin($this);
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
?>
