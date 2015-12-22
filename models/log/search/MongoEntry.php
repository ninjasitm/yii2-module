<?php

namespace nitm\models\log\search;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * EntrySearch represents the model behind the search form about `\lab1\models\log\Entry`.
 */
class MongoEntry extends \nitm\search\BaseMongo
{
	public static $namespace = '\nitm\models\log';
	public static $collectionName = 'nitm-log';
	public $engine = 'mongo';
}
