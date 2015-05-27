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
    protected function buildXml($element, $data, $dom=null)
    {
		\nitm\helpers\Xml::build($element, ['hierarchy' => $data], $dom, true);
    }
	    /**
     * Formats the specified response.
     * @param Response $response the response to be formatted.
     */
    public function format($response)
    {
        $charset = $this->encoding === null ? $response->charset : $this->encoding;
        if (stripos($this->contentType, 'charset') === false) {
            $this->contentType .= '; charset=' . $charset;
        }
        $response->getHeaders()->set('Content-Type', $this->contentType);
        if ($response->data !== null) {
            $dom = new \DOMDocument($this->version, $charset);
            $root = new \DOMElement($this->rootTag);
            $dom->appendChild($root);
            $this->buildXml($root, $response->data, $dom);
            $response->content = $dom->saveXML();
        }
    }
}

?>