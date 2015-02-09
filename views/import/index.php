<?php

use kartik\widgets\ActiveForm;

/* @var $this yii\web\View */
?>
<h1>Import New Data</h1>



<div class="col-lg-12 col-md-12 col-sm-12">
    <div class="pull-left">
    <?php 
        $form = ActiveForm::begin(['id' => 'show_section',
            'action' => '/configuration/get',
            'options' => ['class' => 'form-inline'],
            'enableAjaxValidation' => false,
            'enableClientValidation' => false,
            'validateOnSubmit' => false,
            'fieldConfig' => [
                'inputOptions' => ['class' => 'form-control pull-left']
            ],
        ]); ?>
    <?=
        $form->field($model, 'section'
                    )->dropDownList($model->config('current.sections'))->label("Select ".$model->config('current.type_text')." to edit:", ['class' => 'sr-only']); 
    ?>
    <?php 
        echo Html::activeHiddenInput($model, 'what', array('value' => 'section'));
        echo Html::activeHiddenInput($model, 'getValues', array('value' => true));
        echo Html::activeHiddenInput($model, 'container', array('value' => $model->config('current.container')));
        echo Html::submitButton('Change', [
                'class' => 'btn btn-primary',
                "data-loading-text" => "Loading..."
            ]
        );
        ActiveForm::end(); 
    ?>
    </div>
   </div>div>