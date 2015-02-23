<?php
namespace nitm\traits;

use nitm\helpers\Response;
use nitm\helpers\Icon;
use yii\helpers\Html;
use yii\web\jsExpression;
use nitm\models\ParentMap;

/**
 * Controller actions
 */
trait ControllerActions {
	
	public function afterAction($action, $result)
	{
		$result = parent::afterAction($action, $result);
		if(\Yii::$app->getModule('nitm')->enableLogger)
			$this->commitLog();
		return $result;
	}
	
	public function actionAddParent($type, $id)
	{
		$ret_val = false;
		$model = $this->model->findOne($type);
		if(is_a($model, $this->model->className())) {
			$model->parent_ids = [$id];
			$result = $model->addParentMap();
			if(in_array($type, $result))
				$ret_val = Html::tag('li', $model->name.
						Html::tag('span',
							Html::a("Remove ".Icon::show('remove'), 
								'/'.$this->model->isWhat()."/remove-parent/".$model->getId(), [
								'role' => 'parentListItem',
								'style' => 'color:white'
							]), [
							'class' => 'badge'
						]), [
						'class' => 'list-group-item'
					]);
		}
		
		$this->setResponseFormat('json');
		return $this->renderResponse($ret_val);
	}
	
	public function actionRemoveParent($type, $id)
	{
		$ret_val = false;
		$model = ParentMap::find()->where(['remote_id' => $type, 'parent_id' => $id])->one();
		if(is_object($model)) {
			$model->delete();
			$ret_val = true;
		}
		$this->setResponseFormat('json');
		return $this->renderResponse($ret_val);
	}
}