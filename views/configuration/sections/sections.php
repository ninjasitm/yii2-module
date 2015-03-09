<div class="col-md-12 col-lg-12" id="sections_container">
<?php
	switch ($model->config('load.current'))
	{
		case true;
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
		break;
	}
?>
</div>