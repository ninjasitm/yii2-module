<?php

namespace nitm\models;

use Yii;
use nitm\helpers\Cache;

/**
 * This is the model class for table "categories".
 *
 * @property integer $id
 * @property integer $parent_ids
 * @property string $name
 * @property string $slug
 * @property string $html_icon
 * @property string $created
 * @property string $updated
 */
class Category extends Entity
{
	public $typeId;
	public $bindToType = true;
	public $bindTypes;

	use \nitm\traits\relations\Category, \nitm\traits\Nitm, \nitm\filemanager\traits\Relations;

	public function init()
	{
		parent::init();
		if($this->bindToType)
			$this->setBindType(true);
	}

	public function setBindType($set=false)
	{
		if($set === true)
			$this->queryOptions['type_id'] = static::find()->select('id')->where(['slug' => (isset($this->bindTypes)  ? $this->bindTypes : static::isWhat())]);
	}

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'categories';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name', 'slug'], 'required'],
            [['created', 'updated', 'html_icon'], 'safe'],
            [['name', 'slug'], 'string', 'max' => 255],
            [['type_id', 'name', 'slug'], 'unique', 'targetAttribute' => ['type_id', 'name', 'slug'], 'message' => 'This category already exists for the given Type'],
			[['type_id'], 'filter', 'filter' => [$this, 'setType']],
			[['parent_ids'], 'filter', 'filter' => [$this, 'setParentIds']]
		];
    }

	public function scenarios()
	{
		return array_merge(parent::scenarios(), [
			'create' => ['metadata', 'type_id', 'parent_ids', 'name', 'slug', 'html_icon'],
			'update' => ['metadata','type_id', 'parent_ids', 'name', 'slug', 'html_icon'],
		]);
	}

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'parent_ids' => Yii::t('app', 'Parents'),
            'name' => Yii::t('app', 'Name'),
            'slug' => Yii::t('app', 'Slug'),
            'created' => Yii::t('app', 'Created'),
            'updated' => Yii::t('app', 'Updated'),
        ];
    }

	public function behaviors()
	{
		$behaviors = [
			'timestamp' => [
				'class' => \yii\behaviors\TimestampBehavior::className(),
					'attributes' => [
						\yii\db\ActiveRecord::EVENT_BEFORE_INSERT => 'created_at',
						\yii\db\ActiveRecord::EVENT_BEFORE_UPDATE => 'updated_at',
					],
					'value' => new \yii\db\Expression('NOW()')
				],
			'author' => [
				'class' => \yii\behaviors\BlameableBehavior::className(),
					'attributes' => [
						\yii\db\ActiveRecord::EVENT_BEFORE_INSERT => 'author_id',
						\yii\db\ActiveRecord::EVENT_BEFORE_UPDATE => 'editor_id',
					],
				],
		];
		return array_merge(parent::behaviors(), $behaviors);
	}

	public function title()
	{
		return $this->name;
	}

	/**
	 * @param string action
	 * @param mixed $constrain
	 * @return array
	 */
	public static function getCategories($constrain=null)
	{
		$ret_val = [];
		$where = is_array($constrain) ? $constrain : ['type_id' => 1];
		return Category::find()->where($where)->orderBy('slug');
	}

	/**
	 * @param string action
	 * @param mixed $constrain
	 * @return array
	 */
	public static function getNav($action=null, $constrain=null, $useGlobalController=false)
	{
		$cacheKey = 'categories-for-'.static::isWhat();
		if(Cache::exists($cacheKey))
			$categories = Cache::get($cacheKey);
		else {
			$categories = static::getCategories($action, $constrain)->all();
			Cache::set($cacheKey, $categories);
		}
		switch(sizeof($categories) >= 1)
		{
			case true:
			foreach($categories as $category)
			{
				if($useGlobalController)
					$url = (is_null($action) ? \Yii::$app->controller->id."/$action/" : ltrim($action, "/"))."/".$category['slug'];
				else {
					$url = (is_null($action) ? \Yii::$app->controller->id."/$action/" : '/'.$category['slug']."/".ltrim($action, "/"));
				}
				switch($category['id'])
				{
					case 1:
					$uncategorized = [
						'url' => $url,
						'label' => $category['name'],
						'icon' => 'plus',
						'id' => $category['id']
					];
					break;

					default:
					$ret_val[$category['slug']] = [
						'url' => $url,
						'label' => $category['name'],
						'icon' => 'plus',
						'id' => $category['id']
					];
					break;
				}
			}
			if(isset($uncategorized) && is_array($uncategorized)) {
				array_unshift($ret_val, $uncategorized);
			}
			break;

			default:
			$ret_val = [
				[
					'url' => \Yii::$app->controller->id.(is_null($action) ? '/' : ltrim($action, "/"))."/category/",
					'label' => "Category",
					'icon' => 'plus'
				]
			];
			break;
		}
		unset($ret_val[0]);
		if($useGlobalController)
			$url = (is_null($action) ? \Yii::$app->controller->id."/$action/" : ltrim($action, "/"))."/category";
		else
			$url = (is_null($action) ? \Yii::$app->controller->id."/$action/" : "/category/".ltrim($action, "/"));
		array_unshift($ret_val, [
			'url' => $url,
			'label' => "Category",
			'icon' => 'plus'
		]);
		return $ret_val;
	}

	public function setType()
	{
		switch($this->isNewRecord)
		{
			case true:
			$type = static::find()->select('id')->where(['slug' => static::isWhat()])->one();
			return $type instanceof Category ? $type->id : null;
			break;

			default:
			return $this->type_id;
			break;
		}
	}

	public function setParentIds($ids) {
		$ids = is_array($ids) ? $ids : [$ids];
		return is_array(array_filter($ids)) ? implode(',', $ids) : null;
	}

	public function getListViaClass($className=null)
	{
		if(!is_null($className) && class_exists($class = ((strpos($className, 'Category') !== false) ? $className : $className.'Category')))
			$type = (new $class)->isWhat();
		else if(isset($this) && get_class($this) == __CLASS__) {
			$class = __CLASS__;
			$type = $this->isWhat();
		} else {
			$class = __CLASS__;
			$type = $class::isWhat();
		}
		$key = 'list-'.md5($class);
		if(Cache::cache()->exists($key))
			return Cache::cache()->get($key);
		else {
			$ret_val = parent::getList(null, ' ', [
				'orderBy' => ['name' => SORT_ASC],
				'where' => [
					'type_id' => new \yii\db\Expression("(SELECT id FROM ".$class::tableName()." WHERE slug='".$type."' LIMIT 1)")
				]
			]);
			Cache::cache()->set($key, $ret_val);
			return $ret_val;
		}
	}

	/**
	 * Adds the parents for this model
	 * ParentMap are specieid in the parent_ids attribute
	 * Parent object belong to the same table
	 */
	public function addParentMap($parents=[])
	{
		$parents = [];
		$ids = array_filter(is_array($this->parent_ids) ? $this->parent_ids : explode(',', $this->parent_ids));
		$parentModels = static::find()->where(['id' => $ids])->asArray()->indexBy('id')->all();
		foreach((array)$parentModels as $parent)
		{
			if(is_array($parent))
			{
				$parents[] = [
					'remote_type' => $this->slug,
					'parent_id' => $parent['id'],
					'parent_type' => $parent['slug'],
					'parent_class' => static::className(),
					'parent_table' => $this->tableName()
				];
			}
		}
		return parent::addParentMap($parents);
	}

}
