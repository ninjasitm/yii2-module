<?php

namespace nitm\models\search;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * BaseElasticSearch provides the basic search functionality based on the class it extends from.
 */
class BaseElasticSearch extends \nitm\search\BaseElasticSearch
{	
	use \lab1\traits\Relations, \nitm\traits\Cache, \lab1\traits\Lab1, \nitm\traits\Nitm;
	
	public static $namespace = "\\nitm\\models\\";
}