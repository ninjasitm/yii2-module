<?php 
	kartik\widgets\ActiveForm::end();
	$base = 'entity';
	$type = "entity".(isset($type) ? ':'.$type : '');
?>
<script type='text/javascript'>
$nitm.onModuleLoad('<?=$type?>', function () {
	$nitm.module('<?=$base?>').initForms('<?= $formOptions['options']['id']; ?>-container', '<?=$type?>');
	$nitm.module('<?=$base?>').initMetaActions('#<?= $formOptions['options']['id']; ?>-container', '<?=$type?>');
	<?php if(\Yii::$app->request->isAjax): ?>
	$nitm.module('tools').initVisibility('#<?= $formOptions['options']['id']; ?>-container');
	<?php endif; ?>
});
</script>