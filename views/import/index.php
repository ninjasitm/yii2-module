<?php

use yii\helpers\Html;
use kartik\widgets\ActiveForm;
use kartik\grid\GridView;
use nitm\helpers\Icon;

/* @var $this yii\web\View */
?>
<h1>Import New Data</h1>

<div class="row">
    <div class="col-lg-12 col-md-12 col-sm-12">
    	<?= 
			\nitm\widgets\modal\Modal::widget([
				'size' => 'large',
				"header" => Html::tag('h1', "Import New Data"),
				'toggleButton' => [
					'tag' => 'a',
					'label' => "New Import ".Icon::forAction('plus'), 
					'href' => \Yii::$app->urlManager->createUrl(['/import/form/create', '__format' => 'modal']),
					'title' => \Yii::t('yii', "Add a new ".$model->properName()),
					'role' => 'dynamicAction createAction disabledOnClose',
					'class' => 'btn btn-success btn-lg'
				],
			]);
		?>
    </div>
</div>

<?= GridView::widget([
	'export' => false,
	'pjax' => false,
	'striped' => false,
	'responsive' => true, 
	'floatHeader'=> false,
	'options' => [
		'id' => $isWhat
	],
	//'filterModel' => $searchModel,
	//'filterUrl' => '/'.$searchModel->primaryModel->isWhat().'/index',
	'dataProvider' => $dataProvider,
	'columns' => [
		[
			'sortLinkOptions' => [
				'data-pjax' => 1
			],
			'attribute' => 'id',
			'format' => 'html',
			'value' => function ($model) {
				$ret_val = "";
				if($model->hasNewActivity)
					$ret_val .= \nitm\widgets\activityIndicator\ActivityIndicator::widget();
				$ret_val .= Html::tag('h4', $model->getId());
				return $ret_val;
			},
		],
		[
			'label' => '% Imported',
			'format' => 'html',
			'value' => function ($model) {
				return Html::tag('h4', Html::tag('span', $model->percentComplete(), ['role' => 'percentComplete'])."%"."<br>".Html::tag('small', $model->count."/".$model->total));
			}
		],
		'name',
		[
			'sortLinkOptions' => [
				'data-pjax' => 1
			],
			'format'  => 'raw',
			'attribute' => 'type',
			'label' => 'Type',
			'value' => function ($model) {
				return $model->url('type', $model->type);
			}
		],
		[
			'sortLinkOptions' => [
				'data-pjax' => 1
			],
			'format'  => 'raw',
			'attribute' => 'data_type',
			'label' => 'Data Type',
			'value' => function ($model) {
				return $model->url('data_type', $model->properName($model->data_type));
			}
		],
		//'closed:boolean',
		//'completed:boolean',
		// 'author',
		// 'edited',
		// 'editor',
		// 'edits',
		// 'request:ntext',
		// 'type:ntext',
		// 'request_for:ntext',
		// 'status',
		// 'completed',
		// 'completed_on',
		// 'closed',
		// 'closed_on',
		// 'rating',
		// 'rated_on',
		[
			'sortLinkOptions' => [
				'data-pjax' => 1
			],
			'attribute' => 'author_id',
			'label' => 'Author',
			'format' => 'raw',
			'value' => function ($model, $index, $widget) {
				return $model->author()->url(\Yii::$app->getModule('nitm')->useFullnames, \Yii::$app->request->url, [$model->formname().'[author]' => $model->author_id]);
			},
		],
		[
			'sortLinkOptions' => [
				'data-pjax' => 1
			],
			'attribute' => 'created_at',
			'format' => 'datetime',
			'contentOptions' => [
				'class' => 'visible-lg visible-md'
			],
			'headerOptions' => [
				'class' => 'visible-lg visible-md'
			]
		],

		[
			'class' => 'yii\grid\ActionColumn',
			'buttons' => [
				'form/update' => function ($url, $model) {
					return \nitm\widgets\modal\Modal::widget([
						'size' => 'x-large',
						'toggleButton' => [
							'tag' => 'a',
							'label' => Icon::forAction('view'), 
							'href' => \Yii::$app->urlManager->createUrl([$url, '__format' => 'modal']),
							'title' => Yii::t('yii', 'View '),
							'class' => 'fa-2x',
							'role' => 'dynamicAction updateAction disabledOnClose',
						],
						'contentOptions' => [
							"class" => "modal-full"
						],
						'dialogOptions' => [
							"class" => "modal-full"
						]
					]);
				},
				'replies' => function($url, $model) {
					return $this->context->replyCountWidget([
						"model" => $model->replyModel(),
						'fullDetails' => false,
					]);
				}
			],
			'template' => "{form/update} {replies}",
			'urlCreator' => function($action, $model, $key, $index) {
				return '/import/'.$action.'/'.$model->getId();
			},
			'options' => [
				'rowspan' => 3,
			]
		],
	],
	'tableOptions' => [
		'class' => 'table',
	],
	'rowOptions' => function ($model, $key, $index, $grid)
	{
		return [
			"class" => 'item '.\nitm\helpers\Statuses::getIndicator($model->getStatus()),
			"style" => "border-top:solid medium #CCC",
			'id' => 'request'.$model->getId(),
			'role' => 'statusIndicator'.$model->getId(),
		];
	},
	'afterRow' => function ($model, $key, $index, $grid){
			
		$statusInfo = \nitm\widgets\metadata\StatusInfo::widget([
			'items' => [
				[
					'blamable' => $model->completedBy(),
					'date' => $model->completed_at,
					'value' => $model->completed,
					'label' => [
						'true' => "Completed ",
						'false' => "Not completed"
					]
				],
			],
		]);
		
		$metaInfo = Html::tag('div', 
			Html::tag('div', 
				implode('<br>', [$statusInfo])
			),[
				'class' => 'wrapper'
			]
		);
		return Html::tag('tr', 
			Html::tag(
				'td', 
				$metaInfo, 
				[
					'colspan' => 9, 
					'rowspan' => 1,
				]
			),
			[
				"class" => 'item '.\nitm\helpers\Statuses::getIndicator($model->getStatus()),
				'role' => 'statusIndicator'.$model->getId(),
			]
		);
	},
	'pager' => [
		'class' => \nitm\widgets\ias\ScrollPager::className(),
		'overflowContainer' => '#'.$isWhat.'-ias-container',
		'container' => '#'.$isWhat,
		'item' => ".item",
		'negativeMargin' => 150,
		'delay' => 500,
	]
]); ?>