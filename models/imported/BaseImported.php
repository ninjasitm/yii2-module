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
		if(is_null($data))
			$this->raw_data = is_array($this->raw_data) ? json_encode(\yii\helpers\ArrayHelper::toArray($this->raw_data)) : $this->raw_data;
		else
			return json_encode(\yii\helpers\ArrayHelper::toArray($data));
	}
	
	public function decode($data=null)
	{
		if(is_null($data))
			$this->raw_data = is_string($this->raw_data) ? json_decode($this->raw_data, true) : $this->raw_data;
		else 
			return json_decode($data, true);
	}
	
	public function getSignature($data=null)
	{
		$data = is_null($data) ? (is_array($this->raw_data) ? json_encode($this->raw_data) : $this->raw_data) : $data;
		return md5(serialize($data));
	}
	
	public static function has()
	{
		return [
			'author',
		];
	}
}
