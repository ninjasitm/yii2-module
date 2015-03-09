<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model lab1\models\log\Entry */

$this->title = Yii::t('app', 'Update {modelClass}: ', [
    'modelClass' => 'Entry',
]) . ' ' . $model->_id;
$this->params['breadcrumbs'][] = ['label' => Yii::t('app', 'Entries'), 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->_id, 'url' => ['view', 'id' => (string)$model->_id]];
$this->params['breadcrumbs'][] = Yii::t('app', 'Update');
?>
<div class="entry-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
