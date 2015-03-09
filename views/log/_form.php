<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model lab1\models\log\Entry */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="entry-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'message') ?>

    <?= $form->field($model, 'level') ?>

    <?= $form->field($model, 'internal_category') ?>

    <?= $form->field($model, 'category') ?>

    <?= $form->field($model, 'timestamp') ?>

    <?= $form->field($model, 'action') ?>

    <?= $form->field($model, 'db_name') ?>

    <?= $form->field($model, 'table_name') ?>

    <?= $form->field($model, 'user') ?>

    <?= $form->field($model, 'user_id') ?>

    <?= $form->field($model, 'ip_addr') ?>

    <?= $form->field($model, 'host') ?>

    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? Yii::t('app', 'Create') : Yii::t('app', 'Update'), ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
