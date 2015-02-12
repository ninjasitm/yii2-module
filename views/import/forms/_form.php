<?php

use yii\helpers\Html;
use yii\bootstrap\Tabs;
use kartik\widgets\ActiveForm;
use dosamigos\fileupload\FileUpload;

/* @var $this yii\web\View */
?>
<div class="row">
	<div class="col-lg-12 col-md-12 col-sm-12">
    <?php 
        $form = ActiveForm::begin(['id' => 'preview-data',
            'action' => '/import/view',
			'type' => ActiveForm::TYPE_HORIZONTAL,
            'enableAjaxValidation' => false,
            'enableClientValidation' => false,
            'validateOnSubmit' => false,
        ]); ?>
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
    <div>
    <?=
		Html::tag('label', "Importing From ", [
			'class' => 'control-label col-md-2 col-lg-2'
		])
		.Html::tag('h5', 'file', [
			'id' => 'import-location',
			'role' => 'indicateLocation',
			'class' => 'strong text-info col-lg-10 col-md-10'
		]);
    ?>
    </div>
    <div class="pull-right">
    <?php 
        echo Html::activeHiddenInput($model, 'location');
        echo Html::submitButton($model->previewImport ? 'Import' : 'Preview', ['class' => 'btn btn-primary']);
        echo Html::resetButton('Reset', ['class' => 'btn btn-default']);
	?>
    </div>
	<?=
		Tabs::widget([
			'options' => [
				'id' => 'importer-location'.uniqid(),
				'role' => 'selectLocation'
			],
			'encodeLabels' => false,
			'items' => [
				[
					'label' => 'Import From File',
					'content' =>Html::tag('div', "<br>".FileUpload::widget([
						'model' => $model,
						'attribute' => 'raw_data[file]',
						'url' => ['view', 'id' => $model->getId()], // your url, this is just for demo purposes,
						'options' => ['accept' => 'text/*'],
						'clientOptions' => [
							'maxFileSize' => 2000000
						],
						// Also, you can specify jQuery-File-Upload events
						// see: https://github.com/blueimp/jQuery-File-Upload/wiki/Options#processing-callback-options
						'clientEvents' => [
							'fileuploaddone' => 'function(e, data) {
													console.log(e);
													console.log(data);
												}',
							'fileuploadfail' => 'function(e, data) {
													console.log(e);
													console.log(data);
												}',
						],
					]), [
						'id' => 'import-from-file',
						'class' => 'col-md-12 col-lg-12'
					]),
					'options' => [
						'id' => 'import-from-file-container',
					],
					'headerOptions' => [
						'id' => 'import-from-file-tab'
					],
					'linkOptions' => [
						'id' => 'import-from-file-link',
					]
				],
				[
					'label' => 'Import From Text',
					'content' => Html::tag('div', "<br>".$form->field($model, 'raw_data[text]')->textarea([
						'placeholder' => "Paste raw data here in the form you chose above"
					]), [
						'id' => 'import-from-csv',
						'class' => 'col-md-12 col-lg-12'
					]),
					'options' => [
						'id' => 'import-from-csv-container',
					],
					'headerOptions' => [
						'id' => 'import-from-csv-tab'
					],
					'linkOptions' => [
						'id' => 'import-from-csv-link'
					]
				],
			]
		]);
	?>
	<?php
		ActiveForm::end(); 
	?>
    </div>
<div>