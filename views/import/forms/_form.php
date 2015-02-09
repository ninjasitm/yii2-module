<?php

use yii\helpers\Html;
use kartik\widgets\ActiveForm;

/* @var $this yii\web\View */
?>
<div class="row">
	<div class="col-lg-12 col-md-12 col-sm-12">
    <?php 
        $form = ActiveForm::begin(['id' => 'preview-data',
            'action' => '/import',
			'type' => ActiveForm::TYPE_INLINE,
            'enableAjaxValidation' => false,
            'enableClientValidation' => false,
            'validateOnSubmit' => false,
        ]); ?>
    <?=
        $form->field($model, 'data_type', [
			'options' => [
				'placeholder' => 'Select Source'
			]
		])->dropDownList(\Yii::$app->getModule('nitm')->importer->getParsers('name'))->label("Select source type", ['class' => 'sr-only']); 
    ?>
    <?=
        $form->field($model, 'type', [
			'options' => [
				'placeholder' => 'Select Data Type'
			]
		])->dropDownList(\Yii::$app->getModule('nitm')->importer->getTypes('name'))->label("Select data location", ['class' => 'sr-only']); 
    ?>
    <?=
        $form->field($model, 'location', [
			'options' => [
				'placeholder' => 'Select Data Location'
			]
		])->dropDownList(\Yii::$app->getModule('nitm')->importer->getSources())->label("Select data location", ['class' => 'sr-only']); 
    ?>
    <?php 
        echo Html::activeHiddenInput($model, 'previewImport');
        echo Html::submitButton($model->previewImport ? 'Import' : 'Preview', ['class' => 'btn btn-primary']);
        echo Html::resetButton('Reset', ['class' => 'btn btn-default']);
		ActiveForm::end(); 
	?>
    </div>
<div>