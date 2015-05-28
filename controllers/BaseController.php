<?php

namespace nitm\controllers;

use Yii;
use yii\helpers\Html;
use yii\web\Controller;
use nitm\helpers\Session;
use nitm\helpers\Response;
use nitm\helpers\ArrayHelper;

class BaseController extends Controller
{
	use \nitm\traits\Configer, \nitm\traits\Controller, \nitm\traits\ControllerActions;
	
	public $model;
	public $metaTags = array();

	private $_cssFiles = array();
	private $_jsFiles = array();
	
	const ELEM_TYPE_PARAM = '__elemType';
	
	public function behaviors()
	{
		$behaviors = [
			'access' => [
				'class' => \yii\filters\AccessControl::className(),
				'rules' => [
					[
						'actions' => [
							'filter', 'add-parent', 'remove-parent'
						],
						'allow' => true,
						'roles' => ['@'],
					],
				],
			],
			'verbs' => [
				'class' => \yii\filters\VerbFilter::className(),
				'actions' => [
					'filter' => ['get', 'post']
				],
			],
		];
		return array_merge(parent::behaviors(), $behaviors);
	}

	public function init()
	{
		// get the default css and meta tags
		$this->initCss();
		$this->initAssets();
		$this->initMetaTags();
		$this->initJs();
		$registered = Session::isRegistered(Session::settings);
		switch(!$registered || !(Session::size(Session::settings)))
		{
			case true:
			$this->initConfig();
			break;
		}
		//$this->initConfig(@Yii::$app->controller->id);
		parent::init();
	}
	
	public static function assets()
	{
		return [
		];
	}
	
	/*
	 * Initialize any css needed for this controller
	 */
	public function initCss($_cssFiles = array())
	{
		//don't init on ajax requests so that we don't send duplicate files
		if(\Yii::$app->request->isAjax)
			return;
		$this->_cssFiles = is_array($this->_cssFiles) ? $this->_cssFiles : array($this->_cssFiles);
		$this->_cssFiles[] = $this->id;
		
		switch(!empty($this->_cssFiles))
		{
			case true:
			foreach($this->_cssFiles as $css)
			{
				$file = (is_array($css) ? $css['url'] : $css).'.css';
				$depends = isset($css['depends']) ? $css['depends'] : [];
				$options = isset($css['options']) ? $css['options'] : [];
				switch(1)
				{
					case strpos($file, DIRECTORY_SEPARATOR) !== false:
					case strpos($file, '@') !== false:
					$this->publishFile(\Yii::getAlias($file), $options);
					break;
					
					default:
					$file = Yii::$app->basePath.'/web/css/'.$file;
					switch(file_exists(\Yii::getAlias($file)))
					{
						case true:
						$this->view->registerCssFile($file, $depends, $options);
						break;
					}
					break;
				}
			}
			break;
		}
	}
	
	/*
	 * Initialize any javascript needed for this controller
	 * @param boolean $defaults
	 * @param boolean $footer
	 */
	public function initJs($defaults=true, $footer=false)
	{
		//don't init on ajax requests so that we don't send duplicate files
		if(\Yii::$app->request->isAjax)
			return;
		switch($defaults)
		{
			case true:
			$this->_jsFiles[] = array('src' => $this->id, 'position' => \yii\web\View::POS_END);
			break;
		}
		
		switch(!empty($this->_jsFiles))
		{
			case true:
			foreach($this->_jsFiles as $js)
			{
				switch(1)
				{
					case $js['src'][0] == ':':
					$js['src'] = substr($js['src'], 1, strlen($js['src']));
					break;
					
					case $js['src'][0] == '@':
					break;
					
					default:
					$js['src'] = '/js/'.$js['src'];
					break;
				}
				$js['src'] = $js['src'].'.js';
				switch(1)
				{
					case file_exists(\Yii::$app->basePath.'/web'.$js['src']):
					$js['src'] = Yii::$app->UrlManager->baseUrl.$js['src'];
					$this->view->registerJsFile($js['src'], ["position" => $js['position']]);
					break;
					
					case file_exists(\Yii::getAlias($js['src'])):
					$this->publishFile(\Yii::getAlias($js['src']), [
						'jsOptions' => ["position" => $js['position']]
					], 'js');
					break;
				}
			}
			break;
		}
	}
	
	protected function publishFile($path, $options=[], $type='css')
	{
		$f = pathinfo($path);
		$defaultOptions = [
			'sourcePath' => $f['dirname'],
		];
		$defaultOptions[$type] = [$f['basename']];
		$asset = new \yii\web\AssetBundle(array_merge($options, $defaultOptions));
		$asset->publish($this->view->getAssetManager());
		$this->view->assetBundles[$f['basename']] = $asset;
	}

	/*
	 * Add js files to this controller
	 * @param string $jscript
	 * @param boolean $footer
	 */
	public function addJs($jscript, $footer=true)
	{
		$jscript = array_filter(is_array($jscript) ? $jscript : explode(',', $jscript));
		if(!empty($jscript))
		{
			switch(is_array($jscript) && !empty($jscript))
			{
				case true:
				foreach($jscript as $script)
				{
					$this->_jsFiles[] = [
						'src' => $script,
						'position' => (($footer === true) ? \yii\web\View::POS_END : \yii\web\View::POS_HEAD)
					];
				}
				break;
			}
		}
	}

	/*
	 * Add css files to this controller
	 * @param string $css
	 * @param boolean $footer
	 */
	public function addCss($css)
	{
		$css = array_filter(is_array($css) ? $css : explode(',', $css));
		if(!empty($css))
		{
			switch(is_array($css) && !empty($css))
			{
				case true:
				foreach($css as $file)
				{
					$file = is_array($file) ? $file : ['url' => $file];
					$this->_cssFiles[] = $file;
				}
				break;
			}
		}
	}
	
	/*
	 * Initialize any meta tags needed for this controller
	 * @param mixed $metaTags
	 */
	public function initMetaTags($metaTags = array())
	{
		// meta tags
		$metaTags = is_array($metaTags) ? $metaTags : array($metaTags);
		$this->metaTags = array_filter(array_merge($this->metaTags, $metaTags));
		if(!empty($this->metaTags))
		{
			foreach(array_keys($this->metaTags) as $metaTag)
			{
				switch(empty($metaTag))
				{
					case true:
					Yii::$app->view->registerMetaTag($this->metaTags[$metaTag],$metaTag);
					break;
				}
			}
		} 
        
    }
	
	/**
	 * load the main navigation variables
	 * @param string from
	 * @return mixed $ret_val
	 */
	public static function loadNav($from="navigation")
	{
		$ret_val = $priorities = [];
		$navigation = Session::getVal($from);
		if(is_array($navigation))
		{
			foreach($navigation as $group=>$val)
			{
				@list($name, $property, $property_name) = explode("_", $group);
				switch(@$val['item_disabled'] == 1)
				{
					//handle sublinks here. only one level deep
					case false:
					switch($property)
					{
						case "sub":
						$priority = isset($val['priority']) ? $val['priority'] : sizeof($ret_val);
						@$ret_val[$priorities[$name]."_".$name][$property][$property_name] = $val;
						break;
						
						//this is a mainlink
						default:
						$priority = isset($val['priority']) ? $val['priority'] : sizeof($ret_val);
						$ret_val[$priority."_".$name] = @(!is_array($ret_val[$name])) ? array() : $ret_val[$priority."_".$name];
						$priorities[$name] = $priority;
						$ret_val[$priority."_".$name] = $val;
						break;
					}
					break;
				}
			}
		}
		ksort($ret_val);
		return $ret_val;
	}
	
	/*
	 * Get the HTML encoded navigation information
	 * @param mixed $navigation
	 * @param mixed $encapsulate Surround the elements in this tag
	 * @return mixed $ret_val
	 */
	public static function getNavHtml($navigation=null, $encapsulate=null)
	{
		$ret_val = array();
		$navigation = !is_array($navigation) ? static::loadNav('settings.navigation') : $navigation;
		$top = ($navigation === null) ? true : false;
		foreach($navigation as $idx=>$options)
		{
			if(isset($item['adminOnly']) && !\Yii::$app->user->identity->isAdmin())
			continue;
			
			$submenu = null;
			switch(isset($options['sub']) && is_array($options['sub']))
			{
				case true:
				$options['sub'] = static::getNavHtml($options['sub']);
				$submenu = $options['sub'];
				break;
			} 
			if(is_array($options))
			{
				$label = ArrayHelper::remove($options, 'name', 'no-name');
				$url = ArrayHelper::remove($options, 'data-url', ArrayHelper::remove($options, 'href', '#'));
				$class = ArrayHelper::remove($options, 'class', '');
				$labelClass = ArrayHelper::remove($options, 'label-class', '');
				$item = array_merge($options, [
					'label' => Html::tag('span', $label, [
						'class' => $labelClass
					]),
					'items' => $submenu,
					'url' => $url,
					"options" => [
						"class" => $class, 
						"encode" => false
					],
					'linkOptions' => $options
				]);
				$ret_val[$idx] = $item;
				if(!empty($encapsulate)){
					$ret_val[$idx]['label'] = Html::tag($encapsulate, $ret_val[$idx]['label'], [
						"class" => $class, 
						"encode" => false
					]);
					$ret_val[$idx]['options']['class'] = null;
				}
			}
		}
		return $ret_val;
	}
	
	/**
	 * Get the class indicator value for the users status
	 * @param Edit $token
	 * @return string $css class
	 */
	public function getStatusIndicator($item)
	{
		$item = is_null($item) ? $this : $item;
		$ret_val = 'default';
		switch(is_object($item))
		{
			case true:
			switch(1)
			{
				case $item->hasProperty('disabled');
				case '':
				$ret_val = 'default';
				break;
				
				case 'Public':
				$ret_val = 'info';
				break;
			}
			break;
		}
		$indicator = \nitm\helpers\Statuses::getIndicator($ret_val);
		return $indicator;
	}
	
	/**
	 * Get the javascript requested for this ajax view
	 */
	public function getJavascriptForView(\yii\web\View $view=null)
	{
		$view = is_null($view) ? $this->getView() : $view;
		$ret_val = '';
		if(@is_array($view->js))
		{
			$aman = $view->getAssetManager();
			array_walk($aman->bundles, function ($bundle) use($aman, &$ret_val){
				 foreach($bundle->js as $file)
				 {
					 //Only load the validtion scripts
					 switch($file)
					 {
						case 'yii.validation.js':
						case 'yii.activeForm.js':
						$aman->publish($bundle->sourcePath);
						$ret_val .= Html::jsFile($aman->getPublishedUrl($bundle->sourcePath)."/".$file)."\n";
						break;
					 }
				 }
			});			
			$ret_val .= Html::script(
				array_walk($view->js[static::POS_READY], function ($v) {
					return $v;
				})
			);

		}
		return $ret_val;
	}
}

?>
