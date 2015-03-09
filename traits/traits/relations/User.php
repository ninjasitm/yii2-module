<?php
namespace nitm\traits\relations;

/**
 * Traits defined for expanding active relation scopes until yii2 resolves traits issue
 */

trait User {
	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getProfile()
	{
		return $this->hasOne(\Yii::$app->getModule('user')->manager->profileClass, ['user_id' => 'id']);
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
}
?>
