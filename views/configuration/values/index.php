<?php
use yii\helpers\Html;
?>
<?php switch(sizeof($values) >= 1) :
	case true: ?>
	<?php switch(is_array($values)) :
		case true: ?>
		<div class="list-group" id='value-list'>
		<?php
			foreach($values as $section=>$value)
			{
				if(!is_array($value))
					continue;
				echo $this->render('value',  [
					"model" => $model,
					"section" => $section,
					"data" => $value,
					"surround" => @$surround
				]);
			}
		?>
		</div>
		<?php break;?>
	<?php endswitch;?>
	<?php break;?>
<?php endswitch;?>

<?=
	//render the footer for this section
	$this->render("footer", [
		"model" => $model,
		"section" => $parent,
		"container" => $model->config('current.container')
	]);
?>