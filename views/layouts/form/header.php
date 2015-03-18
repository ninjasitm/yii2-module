<?php
	use yii\helpers\Html;
	use kartik\widgets\ActiveForm;
	
	echo Html::tag('div', '', ['id' => 'alert']);
	
	$form = ActiveForm::begin(array_merge([
			"id" => $formOptions['options']['id'],
			"type" => ActiveForm::TYPE_HORIZONTAL,
			'fieldConfig' => [
				'inputOptions' => ['class' => 'form-control'],
				'labelOptions' => ['class' => 'col-lg-2 control-label'],
			],
			'enableAjaxValidation' => true,
		], array_diff_key($formOptions, [
			'container' => null
		])
	));
	return $form;
?>