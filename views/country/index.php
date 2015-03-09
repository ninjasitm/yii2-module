<?php

use yii\helpers\Html;
use yii\grid\GridView;
use \yii\widgets\Pjax;

/**
 * @var yii\web\View $this
 * @var yii\data\ActiveDataProvider $dataProvider
 * @var lab1\models\search\Country $searchModel
 */

$this->title = Yii::t('app', 'Countries');
$this->params['breadcrumbs'][] = $this->title;
?>
<?php Pjax::begin(); ?>
<div class="country-index">
	<?= yii\widgets\Breadcrumbs::widget([
		'links' => $this->params['breadcrumbs']
	]); ?>

    <h1><?= Html::encode($this->title) ?></h1>
    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <p>
        <?= Html::a(Yii::t('app', 'Create {modelClass}', [
  'modelClass' => 'Country',
]), ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            'id',
            'name',
            'code',
            'seq',

            ['class' => 'yii\grid\ActionColumn'],
        ],
		'options' => [
			'id' => 'countries'
		],
		'rowOptions' => function ($model, $key, $index, $grid)
		{
			return [
				"class" => 'item'
			];
		},
		'pager' => [
			'class' => \nitm\widgets\ias\ScrollPager::className(),
			'overflowContainer' => '.content',
			'container' => '#countries',
			'item' => ".item",
			'negativeMargin' => 150,
			'delay' => 500,
		]
    ]); ?>

</div>
<?php Pjax::end(); ?>
