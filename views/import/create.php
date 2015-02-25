<?php

use yii\helpers\Html;
use nitm\models\imported\Source;

/**
 * @var yii\web\View $this
 * @var app\models\imported\Import $model
 */

$this->title = Yii::t('app', 'Create {modelClass}', [
  'modelClass' => 'Import Job',
]);
$this->params['breadcrumbs'][] = ['label' => Yii::t('app', 'Import'), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div id="<?= $formOptions['container']['id']; ?>" class="<?= $formOptions['container']['class']?> ">

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
		'processor' => $processor
    ]) ?>

</div>