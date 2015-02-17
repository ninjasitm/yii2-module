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
					'role' => 'importSource',
					'data-source' => 'file'
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
					'id' => 'import-from-csv-link',
					'role' => 'importSource',
					'data-source' => 'text'
				]
			],
		]
	]);
?>
<div role="previewImport" class="col-lg-12 col-md-12 col-sm-12">
</div>