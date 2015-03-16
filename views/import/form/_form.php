<?php

use yii\helpers\Html;
use yii\bootstrap\Tabs;
use kartik\widgets\ActiveForm;
use dosamigos\fileupload\FileUploadUI;
use dosamigos\fileupload\FileUpload;
use nitm\helpers\Icon;

/* @var $this yii\web\View */

$formOptions = array_replace_recursive($formOptions, [
	'action' => ($model->getIsNewRecord()) ? '/import/create' : '/import/update/'.$model->getId(),
	'type' => ActiveForm::TYPE_HORIZONTAL,
	'enableAjaxValidation' => true,
	'enableClientValidation' => true,
	'validateOnSubmit' => true,
	'options' => [
		'enctype' => 'multipart/form-data',
		'role' => $action."Import"
	]
]);

?>
<div class="row" >
    <?php if($model->isNewRecord): ?>
	<div class="col-lg-12 col-md-12 col-sm-12 <?= \Yii::$app->request->isAjax ? '' : 'absolute'?>">
    <?php else: ?>
	<div class="col-lg-4 col-md-5 col-sm-12  <?= \Yii::$app->request->isAjax ? '' : 'absolute'?>">
   	<?php endif; ?>
    <h4 class="text-warning">
        All data uploaded or sent through this form WILL OVERWRITE existing data. If the import you are trying to create already exists please use a different name.
    </h4>
		<?php $form = include(\Yii::getAlias("@nitm/views/layouts/form/header.php")); ?> 
        <?=
            $form->field($model, 'name', [
                'options' => [
                    'placeholder' => 'Name this import',
					'class' => !$model->isNewRecord ? 'disabled' : ''
                ]
            ]); 
        ?>
        <?=
            $form->field($model, 'data_type', [
                'options' => [
                    'placeholder' => 'Select Data Type',
                    'role' => 'selectDataType',
					'class' => !$model->isNewRecord ? 'disabled' : ''
                ]
            ])->dropDownList(\Yii::$app->getModule('nitm')->importer->getTypes('name'))->label("Data Contains"); 
        ?>
        <?=
            $form->field($model, 'type', [
                'options' => [
                    'placeholder' => 'Select Source Type',
                    'role' => 'selectType',
					'class' => !$model->isNewRecord ? 'disabled' : ''
                ]
            ])->dropDownList(\Yii::$app->getModule('nitm')->importer->getParsers('name'))->label("Data Format"); 
        ?>
		<?php 
            echo Html::activeHiddenInput($model, 'source', [
                'role' => 'sourceNameInput'
            ]);
        ?>
        <?php if(!\Yii::$app->request->isAjax): ?>
        <div class="row">
            <div class="col-md-12 col-lg-12">
                <div class="pull-right">
                <?php 
                    echo Html::submitButton($model->isNewRecord ? 'Preview' : 'Update', ['class' => 'btn btn-primary']);
                    echo Html::resetButton('Reset', ['class' => 'btn btn-default']);
                ?>
                </div>
            </div>
        </div>
       	<?php endif; ?>
        <?php if($model->isNewRecord): ?>
            <?= $this->render("source.php", ['form' => $form, 'model' => $model]); ?>
        <?php endif; ?>
        <?php
            ActiveForm::end(); 
        ?>
    </div>
    <?php if(!$model->isNewRecord): ?>
    <div class="col-md-offset-5 col-lg-offset-4 col-md-7 col-lg-8 col-sm-12 absolute full-height" id="elements-preview-ias-container">
    	<?= $this->render("../preview.php", [
			'form' => $form, 
			'model' => $model, 
			'dataProvider' => $dataProvider, 
			'processor' => $processor,
			'formOptions' => $formOptions
		]); ?>
    </div>
    <?php endif; ?>
</div>
<?php if(\Yii::$app->request->isAjax): ?>
<script type='text/javascript'>
$nitm.onModuleLoad('entity:import', function (module) {
	module.initForms('<?= $formOptions['container']['id']; ?>', 'entity:import');
});
</script>
<?php endif; ?>