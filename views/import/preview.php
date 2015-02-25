<?php
use kartik\widgets\ActiveForm;
use kartik\builder\TabularForm;
use kartik\grid\GridView;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use nitm\helpers\Icon;

$importSubmit = Html::tag('div', 
	Html::tag('div', 
		Html::submitButton('Import', ['class' => 'btn btn-primary pull-right']), [
		'class' => 'col-md-12 col-lg-12'
	]),[
		'class' => 'row'
	]);
?>
<div id="elements_preview_form_container">

<?php
$form = ActiveForm::begin([
	'action' => '/import/batch/'.$model->getId(),
	'type' => ActiveForm::TYPE_HORIZONTAL,
	'enableAjaxValidation' => true,
	'enableClientValidation' => true,
	'validateOnSubmit' => true,
	'options' => [
		'id' => 'source-import-elements-form',
		'role' => "importElements"
	]

]);
echo TabularForm::widget([
    // your data provider
    'dataProvider' => $dataProvider,
 
    // formName is mandatory for non active forms
    // you can get all attributes in your controller 
    // using $_POST['kvTabForm'],
	'form' => $form,
    'formName' => $model->formName().'[elements]',
    
    // set defaults for rendering your attributes
    'attributeDefaults'=>[
        'type' => TabularForm::INPUT_RAW,
    ],
    
    // configure attributes to display
    'attributes' => $this->context->getProcessor()->formAttributes(),
    // configure other gridview settings
    'gridSettings'=>[
        'panel'=>[
            'heading'=>'<h3 class="header"></i> Preview Import Data</h3>',
            'type'=>GridView::TYPE_DEFAULT,
            'before'=> $importSubmit,
            'footer'=>false,            
			'after'=>  $importSubmit
        ],
		'rowOptions' => function ($model) {
			return [
				"class" => 'item '.\nitm\helpers\Statuses::getIndicator(ArrayHelper::getValue($model, 'is_imported', false) ? 'success' : 'default'),
				"style" => "border-top:solid medium #CCC",
				'id' => 'element'.$model['_id'],
				'role' => 'statusIndicator'.$model['_id'],
			];
		}
    ],
	'actionColumn' => [
		'buttons' => [
			'element' => function ($url, $model) {
				if(!$model['is_imported'])
					return \yii\helpers\Html::a(Icon::forAction('upload'), \Yii::$app->urlManager->createUrl([$url, '__format' => 'json']), [
						'title' => Yii::t('yii', 'Import'),
						'class' => 'fa-2x',
						'role' => 'importElement'
					]);
				else
					return Icon::show('thumbs-up', ['class' => 'text-success fa-2x']);
			},
		],
		'template' => "{element}",
		'urlCreator' => function($action, $array, $key, $index) use($model) {
			$id = ArrayHelper::getValue($array, 'id', null);
			$type = is_null($id) ? $model->getId() : 'element';
			$id = is_null($id) ? $array['_id'] : $id;
			return '/import/'.$action.'/'.$type.'/'.$id;
		},
		'options' => [
			'rowspan' => 3,
		]
	],
]);

ActiveForm::end();
?>
</div>
<script type='text/javascript'>
$nitm.onModuleLoad('entity:import', function (module) {
	module.initElementImportForm('elements_preview_form_container', 'entity:import');
	module.initElementImport('elements_preview_form_container', 'entity:import');
});
</script>