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
				"content" => $this->render("forms/_form", ["model" => $model]),
				'toggleButton' => [
					'tag' => 'a',
					'label' => "New Import ".Icon::forAction('plus'), 
					'title' => Yii::t('yii', 'Create New Import Job '),
					'class' => 'fa-2x',
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
				$ret_val .= Html::tag('h1', $model->getId());
				return $ret_val;
			},
			'contentOptions' => function ($model) {
				return [
					'rowspan' => 2,
					'role' => 'voteIndicator'.$model->getId(),
					'style' => "vertical-align: middle; background-color:rgba(255,51,0,".$model->voteModel()->rating()['ratio'].")"
				];
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
				return $model->url('type', $model->properName($model->type));
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
				return $model->url('data_type', $model->properName(type));
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
							'label' => Icon::forAction('update'), 
							'href' => \Yii::$app->urlManager->createUrl([$url, '__format' => 'modal']),
							'title' => Yii::t('yii', 'Edit '),
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
				'close' => function ($url, $model) {
					return Html::a(Icon::forAction('close', 'closed', $model), \Yii::$app->urlManager->createUrl([$url]), [
						'title' => Yii::t('yii', ($model->closed ? 'Open' : 'Close').' '.$model->title),
						'role' => 'metaAction closeAction',
						'class' => 'fa-2x',
						'data-parent' => 'tr',
						'data-pjax' => '0',
					]);
				},
				'complete' => function ($url, $model) {
					return Html::a(Icon::forAction('complete', 'completed', $model), \Yii::$app->urlManager->createUrl([$url]), [
						'title' => Yii::t('yii', ($model->completed ? 'Incomplete' : 'Complete').' '.$model->title),
						'role' => 'metaAction resolveAction disabledOnClose',
						'class' => 'fa-2x',
						'data-parent' => 'tr',
						'data-pjax' => '0',
					]);
				}
			],
			'template' => "{form/update} {complete} {close}",
			'urlCreator' => function($action, $model, $key, $index) {
				return '/'.$model->isWhat().'/'.$action.'/'.$model->getId();
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
		$replies = $this->context->replyCountWidget([
			"model" => $model->replyModel(),
			'fullDetails' => false,
		]);
		$revisions = $this->context->revisionsCountWidget([
			'model' => $model->revisionModel(),
			"parentId" => $model->getId(), 
			"parentType" => $model->isWhat(),
			'fullDetails' => false ,
		]);
		$issues = $this->context->issueCountWidget([
			'model' => $model->issueModel(),
			'enableComments' => true,
			"parentId" => $model->getId(), 
			"parentType" => $model->isWhat(),
			'fullDetails' => false,
		]);
		$follow = \nitm\widgets\alerts\Follow::widget([
			'model' => $model->followModel(),
			'buttonOptions' => [
				'size' => 'normal'
			]
		]);
		$title = Html::tag(
			'h4', 
			$model->title
		);
		
		$activityInfo = Html::tag('div',
			Html::tag('div', $replies, ['class' => 'col-md-3 col-lg-3 text-center']).
			Html::tag('div', $revisions, ['class' => 'col-md-3 col-lg-3 text-center']).
			Html::tag('div', $issues, ['class' => 'col-md-3 col-lg-3 text-center']).
			Html::tag('div', $follow, ['class' => 'col-md-3 col-lg-3 text-center']),
			[
				'class' => 'row'
			]
		);
		$links = Html::tag('div', \nitm\widgets\metadata\ShortLink::widget([
			'label' => 'View',
			'url' => \Yii::$app->urlManager->createAbsoluteUrl([$model->isWhat().'/view/'.$model->getId()]),
			'header' => $model->title,
			'type' => 'modal',
			'size' => 'large'
		]));
		$links .= Html::tag('div', \nitm\widgets\metadata\ShortLink::widget([
			'label' => 'Update',
			'url' => \Yii::$app->urlManager->createAbsoluteUrl([$model->isWhat().'/form/update/'.$model->getId()]),
			'header' => $model->title,
			'type' => 'modal',
			'size' => 'x-large',
			'modalOptions' => [
				'dialogOptions' => [
					'class' => 'modal-full'
				]
			]
		]));
			
		$statusInfo = \lab1\widgets\StatusInfo::widget([
			'items' => [
				[
					'blamable' => $model->editor(),
					'date' => $model->updated_at,
					'value' => $model->edits,
					'label' => [
						'true' => "Updated ",
						'false' => "No updates"
					]
				],
				[
					'blamable' => $model->completedBy(),
					'date' => $model->completed_at,
					'value' => $model->completed,
					'label' => [
						'true' => "Completed ",
						'false' => "Not completed"
					]
				],
				[
					'blamable' => $model->closedBy(),
					'date' => $model->closed_at,
					'value' => $model->closed,
					'label' => [
						'true' => "Closed ",
						'false' => "Not closed"
					]
				],
			],
		]);
		
		$metaInfo = Html::tag('div', 
			Html::tag('div', 
				implode('<br>', [$title, $statusInfo, $activityInfo, $links])
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