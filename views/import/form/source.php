<?php

use yii\helpers\Html;
use yii\bootstrap\Tabs;
use kartik\widgets\ActiveForm;
use dosamigos\fileupload\FileUploadUI;
use dosamigos\fileupload\FileUpload;
use nitm\helpers\Icon;

?>
<div>
<?=
	Html::tag('label', "Importing From ", [
		'class' => 'control-label col-md-2 col-lg-2'
	])
	.Html::tag('h5', 'file', [
		'id' => 'import-location',
		'role' => 'sourceName',
		'class' => 'strong text-info col-lg-10 col-md-10'
	]);
?>
</div>
<div class="col-md-12 col-lg-12">
    <span role="fileUploadMessge">
    </span>
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
				'content' => Html::tag('div', 
					"<br>".FileUploadUI::widget([
						"formView" => "@nitm/views/import/form/fileupload",
						'model' => $model,
						'attribute' => 'raw_data[file]',
						'url' => ['preview'], // your url, this is just for demo purposes,
						'options' => [
							'accept' => 'text/*',
							'id' => 'source-import'
						],
						'clientOptions' => [
							'limitMultipleFileUploads' => 2,
							'maxFileSize' => 200000000
						],
						// Also, you can specify jQuery-File-Upload events
						// see: https://github.com/blueimp/jQuery-File-Upload/wiki/Options#processing-callback-options
						'clientEvents' => [
							'fileuploaddone' => 'function(e, data) {
								$nitm.module("entity:import").afterPreview(data.result, "entity:import", e.target, data.fileInput);
							}',
							'fileuploadfail' => 'function(e, data) {
								$([role="fileUploadMessage"]).html(data.message);
							}',
							'fileuploadadd' => 'function (e, data) {
								//Only submit if the form is validated properly
								var $activeForm = $("#'.$form->id.'").yiiActiveForm();
								$activeForm.yiiActiveForm("data").submitting = true; 
								$activeForm.yiiActiveForm("validate");
							}',
							'fileuploadsubmit' => 'function(e, data) {
								//Only submit if the form is validated properly
								var $activeForm = $("#'.$form->id.'").yiiActiveForm();
								
								data.context.find(":submit").prop("disabled", false);
								//Change the URL to the URL of the newly created import Source
								$(data.form).fileupload("option",
									"url",
									$activeForm.attr("action")
								);
								
								
								var validated = $activeForm.yiiActiveForm("data").validated;
								$activeForm.yiiActiveForm("data").validated = false;
								return validated && ($activeForm.data("id") != undefined);
							}'
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
					'role' => 'importSource',
					'data-source' => 'file'
				]
			],
			[
				'label' => 'Import From Text',
				'content' => Html::tag('div', "<br>".$form->field($model, 'raw_data[text]')->textarea([
					'placeholder' => "Paste raw data here in the form you chose above"
				])->label("Text"), [
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
					'id' => 'import-from-csv-link',
					'role' => 'importSource',
					'data-source' => 'text'
				]
			],
			[
				'label' => 'Import From URL',
				'content' => Html::tag('div', "<br>".$form->field($model, 'raw_data[url]')->textarea([
					'placeholder' => "Paste url to acquire data from"
				])->label("Url"), [
					'id' => 'import-from-url',
					'class' => 'col-md-12 col-lg-12'
				]),
				'options' => [
					'id' => 'import-from-url-container',
				],
				'headerOptions' => [
					'id' => 'import-from-url-tab'
				],
				'linkOptions' => [
					'id' => 'import-from-url-link',
					'role' => 'importSource',
					'data-source' => 'url'
				]
			],
		]
	]);
?>
<div role="previewImport" class="col-lg-12 col-md-12 col-sm-12">
</div>