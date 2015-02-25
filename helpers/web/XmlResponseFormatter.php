<?php

namespace nitm\helpers\web;

/**
 * Support for XmlResponseFormatte Attributes
 * @author artur.fursa@binn.com.ua 
 * @profile https://github.com/arturf
 * @link https://github.com/yiisoft/yii2/issues/5996
 */
class XmlResponseFormatter extends \yii\web\XmlResponseFormatter
{	
	/**
     * @inheritdoc
     */
    protected function buildXml($element, $data)
    {
		\nitm\helpers\Xml::build($element, ['hierarchy' => $data]);
    }
}

?>