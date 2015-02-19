<?php

use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var lab1\models\Country $model
 */

$this->title = Yii::t('app', 'Create {modelClass}', [
  'modelClass' => 'Country',
]);
$this->params['breadcrumbs'][] = ['label' => Yii::t('app', 'Countries'), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
	<?= yii\widgets\Breadcrumbs::widget([
		'links' => $this->params['breadcrumbs']
	]); ?>

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
