<?php
use yii\helpers\Html;
use kartik\widgets\ActiveForm;

$model->setScenario('createValue');
?>
<div class="well" id="create_value_container">
	<div class="wrapper">
		<?php $form = ActiveForm::begin([
			'action' => '/configuration/create',
			"type" => ActiveForm::TYPE_VERTICAL,
			'options' => [
				'id' => "create_new_value_$section",
				'role' => 'createNewValue'
			],
			'validateOnSubmit' => true,
			'enableAjaxValidation' => true
		]); ?>
		<div class="row">
			<div class="col-sm-12 col-md-4">
				<br>
		        <?=
					$form->field($model, 'name')->textInput(['placeholder' => 'Setting name...'])->label("Name", ['class' => 'sr-only']);
				?>
			</div>
			<div class="col-sm-12 col-md-6">
				<br>
				<?=
		            $form->field($model, 'value')->textarea(['placeholder' => 'Setting value...'])->label("Value", ['class' => 'sr-only']);
		        ?>
			</div>
	        <?php
				echo Html::activeHiddenInput($model, 'container', ['value' => $container]);
				echo Html::activeHiddenInput($model, 'section', ['value' => $section]);
				echo Html::activeHiddenInput($model, 'what', ['value' => 'value']);
			?>
			<div class="col-sm-12 col-md-2">
				<br>
				<?=
					Html::submitButton('Add Key/Value', [
						'class' => 'btn btn-primary',
						'title' => "Add value to $section",
						"data-loading-text" => "Adding...",
					]);
		        ?>
			</div>
	        <?php ActiveForm::end(); ?>
		</div>
	</div>
</div>

<script language='javascript' type="text/javascript">
$nitm.onModuleLoad('configuration', function () {
	$nitm.module('configuration').initCreating("create_value_container");
});
</script>
