<div class="col-md-12 col-lg-12" id="sections_container">
<?php
	if($model->config('load.current'))
	{
		$section = $model->section;
?>
	<div class="list-group hidden" id="show_<?= $section ?>_div">
		<?= $this->render('../values/index',  [
			"model" => $model,
			"values" => $model->config('current.config.'.$section),
			"parent" => $section
		]);?>
	</div>
<?php
	}
?>
</div>