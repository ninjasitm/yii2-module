<?php
namespace nitm\traits;

use nitm\helpers\Response;
use nitm\helpers\Icon;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;
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
			$result = $model->addParentMap();
			if(array_key_exists($id, $result)) {
				$parent = $result[$id];
				$model = $parent['parent_class']::findOne($id);
				if($model->hasAttribute('name'))
					$name = $model->name;
				else if($model->hasAttribute('title'))
					$name = $model->title;
				else
					$name = $parent['remote_type'].'-parent-'.$id;
					
				$ret_val = Html::tag('li', $name.
					Html::tag('span',
						Html::a("Remove ".Icon::show('remove'), 
							'/'.$this->model->isWhat()."/remove-parent/".$type.'/'.$id, [
							'role' => 'parentListItem',
							'style' => 'color:white'
						]), [
						'class' => 'badge'
					]), [
					'class' => 'list-group-item'
				]);
			}
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