<?php
use kartik\widgets\ActiveForm;
use kartik\builder\TabularForm;
use kartik\grid\GridView;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use nitm\helpers\Icon;
use nitm\models\imported\Element;

$form = ActiveForm::begin([
	'action' => '/import/elements/',
	'type' => ActiveForm::TYPE_HORIZONTAL,
	'enableAjaxValidation' => false,
	'enableClientValidation' => false,
	'validateOnSubmit' => false,
	'options' => [
		'id' => 'source-import-elements-form',
		'role' => "importElements"
	]

]);

$importSubmit = Html::tag('div', 
	Html::tag('div', 
		Html::submitButton('Import Page', [
			'class' => 'btn btn-primary',
			'onclick' => '$nitm.module("entity:import").importElements(event, "'.$form->options['id'].'");',

		])
		."&nbsp;&nbsp;".
		Html::a(($model->percentComplete() == 100 ? '100% complete!' : $model->percentComplete().'% done. Import Next Batch'), '#', [
			'role' => 'importBatch', 
			'class' => 'btn '.(($model->percentComplete() == 100) ? 'btn-success' : 'btn-info'),
			'onclick' => '$nitm.module("entity:import").importBatch(event);',
			'data-url' => '/import/import-batch/'.$model->getId()
		])
		."&nbsp;&nbsp;".
		Html::a(($model->percentComplete() == 100 ? '100% complete!' : $model->percentComplete().'% done. Import Remaining'), '#', [
			'role' => 'importAll', 
			'class' => 'btn '.(($model->percentComplete() == 100) ? 'btn-success' : 'btn-warning'),
			'onclick' => '$nitm.module("entity:import").importAll(event);',
			'data-url' => '/import/import-batch/'.$model->getId(),
			'data-tooltip' => 'THis is an intensit process. Please wiat for everything to complete'
		]), [
		'class' => 'col-md-12 col-lg-12'
	]),[
		'class' => 'row'
	]);
?>
<div class="full-height">
    <div id="alert"></div>
    <?php
    
    //We're dealing with data pulled from the DB. Tansform it
    if($dataProvider instanceof \yii\data\ActiveDataProvider)
        $dataProvider->setModels(array_map(function ($data) use($processor){
            $rawData = ArrayHelper::remove($data, 'raw_data');
            $data = $processor->transformFormAttributes(array_merge(Element::decode($rawData), $data));
            $data['_id'] = $data['id'];
            return $data;
        }, $dataProvider->getModels()));
    
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
            'pjax' => true,
            'floatHeader' => true,
            'options' => [
                'id' => 'elements-preview',
                'role' => 'previewImport'
            ],
            'tableOptions' => [
                'id' => 'elements-preview-data'
            ],
            /*'pager' => [
                'class' => \nitm\widgets\ias\ScrollPager::className(),
                'overflowContainer' => '#elements-preview-container',
                'container' => '#elements-preview-data',
                'item' => ".item",
                'negativeMargin' => 150,
                'delay' => 500,
            ],*/
            'panel'=>[
                'type'=>GridView::TYPE_DEFAULT,
                'after' => Html::tag('div', $importSubmit, [
                    'class' => 'text-right',
                    'style' => 'background-color: #222; padding: 6px; position:absolute; bottom: 0px; right: 20px; z-index: 1040']),
                'before' => false,
				'footer' => Html::script('$nitm.onModuleLoad("entity:import", function (module) {
		module.initElementImportForm();
		module.initElementImport();
	});')
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
                $id = is_null($id) ? @$array['_id'] : $id;
                return '/import/'.$action.'/'.$type.'/'.$id;
            },
            'options' => [
                'rowspan' => 3,
            ]
        ]
    ]);
    
    ActiveForm::end();
    ?>
</div>