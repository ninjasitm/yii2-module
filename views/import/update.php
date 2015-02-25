<?php

use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var pickledup\models\Action $model
 */

$this->title = Yii::t('app', 'Update {modelClass}: {name}', [
  'modelClass' => $model->properName(),
  'name' => $model->name
]);
$this->params['breadcrumbs'][] = ['label' => Yii::t('app', 'Back'), 'url' => [parse_url(\Yii::$app->request->getReferrer(), PHP_URL_QUERY)]];
$this->params['breadcrumbs'][] = ['label' => Yii::t('app', 'Actions'), 'url' => ['/list/'.$model->isWhat()."/"]];
$this->params['breadcrumbs'][] = ['label' => $this->title];

?>
<div id="<?= $formOptions['container']['id']; ?>" class="<?= $formOptions['container']['class'] ?>">
	<?php if(!\Yii::$app->request->isAjax): ?>
	<?= \yii\widgets\Breadcrumbs::widget(['links' => $this->params['breadcrumbs']]); ?>
	<h2><?= Html::encode($this->title) ?></h2>
	<?php endif; ?>

    <?= $this->render('form/_form', [
        'model' => $model,
		'formOptions' => $formOptions,
		'scenario' => $scenario,
		'action' => $action,
		'type' => $type,
		'dataProvider' => $dataProvider,
		'processor' => $processor
    ]) ?>
</div>
