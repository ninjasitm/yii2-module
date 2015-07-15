<div class="col-md-12 col-lg-12" id="sections_container">
<?php
	if($model->config('load.current'))
	{
		foreach($model->config('current.config') as $section=>$values)
		{
?>
		<div class="list-group hidden" id="show_<?= $section ?>_div">
			<?= $this->render('../values/index',  [
				"model" => $model,
				"values" => $values,
				"parent" => $section
			]);?>
		</div>
<?php
		}
	}
?>
</div>