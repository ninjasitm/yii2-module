<?php
namespace nitm\models;

use yii\db\ActiveRecord;
use yii\helpers\Security;
use yii\web\IdentityInterface;
use dektrium\user\models\Profile as UserProfile;
use nitm\helpers\Cache;

/**
 * Class Profile
 * @package nitm\models
 */
class Profile extends UserProfile
{
	use \nitm\traits\Data, \nitm\traits\Query, \nitm\traits\relations\Profile;
}
