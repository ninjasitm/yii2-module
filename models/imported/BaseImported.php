<?php

namespace nitm\models\imported;

use Yii;

/**
 * BaseImported class provides basic functions common to importer functionality
 */
class BaseImported extends \nitm\models\Entity
{
	use \nitm\traits\Relations, \nitm\widgets\traits\Relations; 

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['author_id'], 'integer'],
			[['raw_data'], 'filter', 'filter' => [$this, 'encode'], 'on' => ['create']],
			[['signature'], 'filter', 'filter' => [$this, 'getSignature'], 'on' => ['create']],
        ];
    }
	
	public function encode($data=null)
	{
		$data = is_null($data) ? $this->raw_data : $data;
		return json_encode(\yii\helpers\ArrayHelper::toArray($data));
	}
	
	public function getSignature($data=null)
	{
		$data = is_null($data) ? (is_array($this->raw_data) ? json_encode($this->raw_data) : $this->raw_data) : $data;
		return md5($data);
	}
	
	public static function has()
	{
		return [
			'author',
		];
	}
}
