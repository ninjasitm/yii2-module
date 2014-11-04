<?php

use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var yii\data\ActiveDataProvider $dataProvider
 * @var frontend\models\search\Refunds $searchModel
 */

$this->title = 'Requests';
$this->params['breadcrumbs'][] = $this->title;
?>

<div id='<?= $isWhat; ?>-ias-container' class="col-md-8 col-lg-8 absolute full-height collapsable">
	<h1><?= Html::encode($this->title) ?></h1>
	<?php \yii\widgets\Pjax::begin([
		'options' => [
			'id' => 'requests-index',
		],
		'linkSelector' => "[data-pjax='1']",
		'timeout' => 5000
	]); ?>
	<?php
		echo $this->render("data", [
				'dataProvider' => $dataProvider,
				'searchModel' => $searchModel,
				'primaryModel' => $model
			]
		); 
	?>
	<?php \yii\widgets\Pjax::end(); ?>
</div>
<div class="col-md-4 col-lg-4 col-md-offset-8 col-lg-offset-8 absolute collapsable full-height filter shadow" id="<?= $isWhat; ?>-filter" style='width: 33%;'>
	<?php
		echo $this->render('_search', [
			"data" => [], 
			'model' => $searchModel,
			'createButton' => $createButton,
			'filterButton' => $filterCloseButton
		]); 
	?>
</div>