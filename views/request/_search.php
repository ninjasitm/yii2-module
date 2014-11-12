<?php
use yii\helpers\Html;
use kartik\widgets\ActiveForm;
use kartik\widgets\Select2;
use yii\widgets\ActiveField;
?>
<br>
<?= $filterButton.$this->context->alertWidget().$this->context->legendWidget();?>
<br>
<?= $createButton; ?>
<h3 class="header text-left">FILTER USING THE FOLLOWING</h3>
<div id="filters">
<?php $form = ActiveForm::begin(['id' => 'filter',
	'method' => 'get',
	'type' => ActiveForm::TYPE_HORIZONTAL,
	'action' => \Yii::$app->urlManager->createUrl(['/search/filter', '__format' => 'json', '_type' => $model->isWhat()]),
	'options' => [
		'class' => 'form-horizontal',
		"role" => "filter",
		'data-id' => $isWhat
	],
	'fieldConfig' => [
		'inputOptions' => ['class' => 'form-control'],
		'template' => '{label}<div class="col-lg-9 col-md-9">{input}</div><div class="col-lg-12">{error}</div>',
		'labelOptions' => ['class' => 'col-lg-3 col-md-3 control-label'],
	],
]);
?>
	<?=
		Html::submitButton(
			Html::tag('span', '', ['class' => 'glyphicon glyphicon-filter']), 
			[
				'class' => 'btn btn-primary btn-lg',
				"title" => "Run this filer"
			]
		);
	?><br><br>
	
	<?=
		$form->field($model, 'filter[exclusive]', [
			'options' => [
				'data-toggle' => 'tooltip',
				'title' => "When set to Yes everything set below will be used to find results. Otherwise the search will find anything that matches at least one of the criteria you set."
			]
		])->widget(\kartik\widgets\SwitchInput::className(),
		[
			'pluginOptions' => [
				'size' => 'small',
				'onText' => 'Yes',
				'offText' => 'No'
			]
		])->label("Match All");
	?>
	
	<?=
		$form->field($model, 'q')->textInput()->label("Search");
	?>

	<?=
		$form->field($model, 'type_id')->widget(Select2::className(), [
			'options' => [
				'multiple' => true,
				'placeholder' => 'Select types...'
			],
			'data' => $model->primaryModel->getCategoryList($model->primaryModel->isWhat().'-categories'),
		])->label("Type");
	?>

	<?=
		$form->field($model, 'request_for_id')->widget(Select2::className(), [
			'options' => [
				'multiple' => true,
				'placeholder' => 'Select for...'
			],
			'data' => $model->primaryModel->getCategoryList($model->primaryModel->isWhat().'-for'),
		])->label("For");
	?>

	<?=
		$form->field($model, 'author_id')->widget(Select2::className(), [
			'options' => [
				'multiple' => true,
				'placeholder' => 'Select authors...'
			],
			'data' => $model->getFilter('author'),
		])->label("Author");
	?>

	<?=
		$form->field($model, 'closed')->checkboxList($model->getFilter('boolean'), ['inline' => true])->label("Closed");
	?>

	<?=
		$form->field($model, 'completed')->checkboxList($model->getFilter('boolean'), ['inline' => true])->label("Completed");
	?>

	<?=
		$form->field($model, 'filter[order]')->widget(Select2::className(), [
			'data' => $model->getFilter('order'),
		])->label("Order");
	?>

	<?=
		$form->field($model, 'filter[order_by]')->widget(Select2::className(), [
			'data' => $model->getFilter('order_by'),
		])->label("Order By");
	?>
	<?=
		Html::submitButton(
			Html::tag('span', '', ['class' => 'glyphicon glyphicon-filter']), 
			[
				'class' => 'btn btn-primary btn-lg',
				"title" => "Run this filer"
			]
		);
	?><br><br>
<?php ActiveForm::end(); ?>
</div>
<br>