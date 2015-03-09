<?php 
	\kartik\widgets\ActiveForm::end();
?>
<?php if(\Yii::$app->request->isAjax): ?>
<?php
	if(!isset($entityType))
	{
		$base = 'entity';
		$type = "entity".(isset($type) ? ':'.$type : '');
	} else {
		$type = $entityType;
		$base = array_shift(explode(':', $entityType));
	}
?>
<script type='text/javascript'>
$nitm.onModuleLoad('<?=$type?>', function () {
	$nitm.module('<?=$base?>').initForms('<?= $formOptions['options']['id']; ?>-container', '<?=$type?>');
	$nitm.module('<?=$base?>').initMetaActions('#<?= $formOptions['options']['id']; ?>-container', '<?=$type?>');
	$nitm.module('tools').initVisibility('#<?= $formOptions['options']['id']; ?>-container');
});
</script>
<?php endif; ?>