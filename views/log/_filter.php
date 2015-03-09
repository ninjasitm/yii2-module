<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model lab1\models\log\EntrySearch */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="entry-search">

    <?= \yii\bootstrap\ButtonDropdown::widget([
			'label' => 'Show logs from',
			'dropdown' => [
				'items' => array_map(function ($name) use ($model){
					return ['label' => $model->properName($name), 'url' => '/log/index/'.$name];
				}, \Yii::$app->getModule('nitm')->logCollections)
			],
			'options' => ['class'=>'btn-primary']
		]); ?>

</div>
