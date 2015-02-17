<?php

use yii\helpers\Html;
use yii\bootstrap\Tabs;
use kartik\widgets\ActiveForm;
use dosamigos\fileupload\FileUploadUI;
use dosamigos\fileupload\FileUpload;
use nitm\helpers\Icon;

/* @var $this yii\web\View */
$formOptions = array_merge($formOptions, [
	'action' => ($model->getIsNewRecord()) ? '/import/create' : '/import/update/'.$model->getId(),
	'type' => ActiveForm::TYPE_HORIZONTAL,
	'enableAjaxValidation' => true,
	'enableClientValidation' => true,
	'validateOnSubmit' => true,
	'options' => [
		'enctype' => 'multipart/form-data',
		'id' => 'source-import-form',
		'role' => $action."Import"
	]
]);

?>
<div class="row">
	<div class="col-lg-12 col-md-12 col-sm-12">
    
   	<?php $form = include(\Yii::getAlias("@nitm/views/layouts/form/header.php")); ?> 
    <?=
        $form->field($model, 'name', [
			'options' => [
				'placeholder' => 'Name this import',
			]
		]); 
    ?>
    <?=
        $form->field($model, 'data_type', [
			'options' => [
				'placeholder' => 'Select Data Type',
				'role' => 'selectDataType'
			]
		])->dropDownList(\Yii::$app->getModule('nitm')->importer->getTypes('name'))->label("Data Contains"); 
    ?>
    <?=
        $form->field($model, 'type', [
			'options' => [
				'placeholder' => 'Select Source Type',
				'role' => 'selectType'
			]
		])->dropDownList(\Yii::$app->getModule('nitm')->importer->getParsers('name'))->label("Data Format"); 
    ?>
    <div class="row">
    	<div class="col-md-12 col-lg-12">
            <div class="pull-right">
            <?php 
                echo Html::activeHiddenInput($model, 'source', [
                    'role' => 'sourceNameInput'
                ]);
                echo Html::submitButton($model->isNewRecord ? 'Preview' : 'Update', ['class' => 'btn btn-primary']);
                echo Html::resetButton('Reset', ['class' => 'btn btn-default']);
            ?>
            </div>
        </div>
    </div>
    <?php if($model->isNewRecord): ?>
    	<?= $this->render("source.php", ['form' => $form, 'model' => $model]); ?>
    <?php endif; ?>
	<?php
		ActiveForm::end(); 
	?>
    <?php if(!$model->isNewRecord): ?>
    	<br><br>
    	<?= $this->render("../preview.php", [
			'form' => $form, 
			'model' => $model, 
			'dataProvider' => $dataProvider
		]); ?>
    <?php endif; ?>
    </div>
</div>