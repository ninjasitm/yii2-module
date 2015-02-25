<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model lab1\models\log\EntrySearch */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="entry-search">

    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
    ]); ?>

    <?= $form->field($model, '_id') ?>

    <?= $form->field($model, 'message') ?>

    <?= $form->field($model, 'level') ?>

    <?= $form->field($model, 'internal_category') ?>

    <?= $form->field($model, 'category') ?>

    <?php // echo $form->field($model, 'timestamp') ?>

    <?php // echo $form->field($model, 'action') ?>

    <?php // echo $form->field($model, 'db_name') ?>

    <?php // echo $form->field($model, 'table_name') ?>

    <?php // echo $form->field($model, 'user') ?>

    <?php // echo $form->field($model, 'user_id') ?>

    <?php // echo $form->field($model, 'ip_addr') ?>

    <?php // echo $form->field($model, 'host') ?>

    <div class="form-group">
        <?= Html::submitButton(Yii::t('app', 'Search'), ['class' => 'btn btn-primary']) ?>
        <?= Html::resetButton(Yii::t('app', 'Reset'), ['class' => 'btn btn-default']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
