<?php

use yii\helpers\Html;
use kartik\grid\GridView;

/* @var $this yii\web\View */
/* @var $searchModel lab1\models\log\EntrySearch */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = Yii::t('app', 'Log Entries From: '.$model->properName($model->collectionName()));
$this->params['breadcrumbs'][] = $this->title;
?>


<div id='<?= $isWhat; ?>-ias-container' class="col-md-12 col-lg-12 absolute full-height collapsable">

	<div class="row">
		<div class="col-md-10 col-lg-10">
    		<h1><?= Html::encode($this->title) ?></h1>
		</div>
		<div class="col-md-2 col-lg-2 collapsable">
    		<?= $this->render('_filter', ['model' => $searchModel]); ?>
		</div>
	</div>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
		'striped' => false,
		'bordered' => false,
        'columns' => [
            [
				'label' => 'created',
				'attribute' => 'timestamp',
				'format' => 'datetime'
			],
           	[
				'label' => 'Method',
				'attribute' => 'request_method',
				'format' => 'html',
		   	 	'value' => function ($model) {
					return Html::tag('strong', $model->request_method);
				}
			],
           	[
				'label' => 'Action',
				'attribute' => 'action',
				'format' => 'html',
		   	 	'value' => function ($model) {
					return Html::tag('strong', $model->properName($model->action));
				}
			],
            'level',
            'category',
           	[
				'label' => 'Content Type',
				'attribute' => 'table_name',
				'format' => 'html',
		   	 	'value' => function ($model) {
					return Html::tag('strong', $model->properName($model->table_name));
				}
			],
            'user',
           	[
				'label' => 'User Agent',
				'attribute' => 'user_agent',
				'format' => 'html',
		   	 	'value' => function ($model) {
					return Html::tag('strong', $model->getAttribute('user_agent'));
				}
			],
            [
				'label' => 'Remote',
				'attribute' => 'host',
				'value' => function ($model) {
					return $model->ip_addr.'/'.$model->host;
				}
			]
        ],
		'rowOptions' => function ($model, $key, $index, $grid) {
			return [
				"class" => 'item '.\nitm\helpers\Statuses::getIndicator($model->getStatus()),
				'style' => 'cursor:pointer',
				'role' => 'iasItem statusIndicator'.$model->getId(),
				'id' => $model->isWhat().'row'.$model->getId(),
				'data-id' => $model->isWhat().'message'.$model->getId(),
				'onclick' => '(function(event) {
					var $elem = $("#'.$model->isWhat().'row'.$model->getId().'");
					var $target = $("#'.$model->isWhat().'message'.$model->getId().'");
					//Do this before showing the message
					if($target.css("display") != "none") {
						$elem.css("box-shadow", "").removeClass("bg-primary").addClass($elem.data("old-class"));
						$target.css("box-shadow", "").removeClass("bg-primary").addClass($target.data("old-class"));
					}
					else {
						$elem.data("old-class", $elem.attr("class")).attr("class", "");
						$target.data("old-class", $elem.attr("class")).attr("class", "");
						$elem.css("box-shadow", "0 -4px 10px -8px #000").addClass("bg-primary");
						$target.css("box-shadow", "0 4px 10px -8px #000").addClass("bg-primary");
					}
					$target.slideToggle("fast");
				})(event)'
			];
		},
		'afterRow' => function ($model, $key, $index, $grid) {
			return Html::tag('tr',
				Html::tag('td',
					Html::tag('pre', $model->message, ['class' => 'pre-scrollable']), 
					[
					'colspan' => 10
				]),
			[
				'id' => $model->isWhat().'message'.$model->getId(),
				"class" => 'item '.\nitm\helpers\Statuses::getIndicator($model->getStatus()),
				'style' => 'display:none; border:none'
			]);
		}
    ]); ?>

</div>
