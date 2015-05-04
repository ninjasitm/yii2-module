<?php

namespace nitm\helpers;

use yii\db\ActiveRecord;
use yii\base\Behavior;
use yii\helpers\ArrayHelper;
use DOMDocument;
use DOMElement;
use DOMComment;
use DOMAttr;
use DOMText;

/**
 * Xml Helper functions
 */
 
class Xml
{
	const FROM_DOM = 'dom';
	const FROM_SIMPLE_XML = 'simple';
	
	protected static $counter = 0;
	
	/*
	 * Function to convert betwen xml and html entities
	 * @param string $str = string to work on
	 * @param boolean reverse = whether to flip the switching between xml and html entities
	 */
	public static function convertEntities($str, $reverse=false)
	{
		$xml = array('&#34;','&#38;','&#38;','&#60;','&#62;','&#160;','&#161;','&#162;','&#163;','&#164;','&#165;','&#166;','&#167;','&#168;','&#169;','&#170;','&#171;','&#172;','&#173;','&#174;','&#175;','&#176;','&#177;','&#178;','&#179;','&#180;','&#181;','&#182;','&#183;','&#184;','&#185;','&#186;','&#187;','&#188;','&#189;','&#190;','&#191;','&#192;','&#193;','&#194;','&#195;','&#196;','&#197;','&#198;','&#199;','&#200;','&#201;','&#202;','&#203;','&#204;','&#205;','&#206;','&#207;','&#208;','&#209;','&#210;','&#211;','&#212;','&#213;','&#214;','&#215;','&#216;','&#217;','&#218;','&#219;','&#220;','&#221;','&#222;','&#223;','&#224;','&#225;','&#226;','&#227;','&#228;','&#229;','&#230;','&#231;','&#232;','&#233;','&#234;','&#235;','&#236;','&#237;','&#238;','&#239;','&#240;','&#241;','&#242;','&#243;','&#244;','&#245;','&#246;','&#247;','&#248;','&#249;','&#250;','&#251;','&#252;','&#253;','&#254;','&#255;');
		
		$html = array('&quot;','&amp;','&amp;','&lt;','&gt;','&nbsp;','&iexcl;','&cent;','&pound;','&curren;','&yen;','&brvbar;','&sect;','&uml;','&copy;','&ordf;','&laquo;','&not;','&shy;','&reg;','&macr;','&deg;','&plusmn;','&sup2;','&sup3;','&acute;','&micro;','&para;','&middot;','&cedil;','&sup1;','&ordm;','&raquo;','&frac14;','&frac12;','&frac34;','&iquest;','&Agrave;','&Aacute;','&Acirc;','&Atilde;','&Auml;','&Aring;','&AElig;','&Ccedil;','&Egrave;','&Eacute;','&Ecirc;','&Euml;','&Igrave;','&Iacute;','&Icirc;','&Iuml;','&ETH;','&Ntilde;','&Ograve;','&Oacute;','&Ocirc;','&Otilde;','&Ouml;','&times;','&Oslash;','&Ugrave;','&Uacute;','&Ucirc;','&Uuml;','&Yacute;','&THORN;','&szlig;','&agrave;','&aacute;','&acirc;','&atilde;','&auml;','&aring;','&aelig;','&ccedil;','&egrave;','&eacute;','&ecirc;','&euml;','&igrave;','&iacute;','&icirc;','&iuml;','&eth;','&ntilde;','&ograve;','&oacute;','&ocirc;','&otilde;','&ouml;','&divide;','&oslash;','&ugrave;','&uacute;','&ucirc;','&uuml;','&yacute;','&thorn;','&yuml;');
		$entities = ($reverse===true) ? array_combine($html, $xml) : array_combine($xml, $html);
		$str = str_replace(array_keys($entities), array_values($entities), $str);
		$str = str_ireplace(array_keys($entities), array_values($entities), $str);
		return $str;
	}
	
	public static function build(&$root, $options=[], &$scaffold=null)
	{
		return array_merge($options, self::buildXml($root, $options, $scaffold));
	}
	
	public static function buildHierarchy(array $hierarchy, $index=null)
	{
		$ret_val = [];
		foreach($hierarchy as $idx=>$elem)
		{
			$name = ArrayHelper::getValue($elem, 'name', (is_null($index) ? $idx : $index));
			if(!empty($name))
			{
				//echo "Going through $name @ $idx with index $index\n";
				if(!empty($elem['children']))
					$elem['children'] = self::buildHierarchy($elem['children']);
				
				if(!is_null($index))
					$ret_val[$index] = $elem;
				else
					$ret_val['_'.$name] = $elem;
			}
		}
		return $ret_val;
	} 
	
	public static function buildXml(&$parent, $options=[], &$scaffold=null)
	{
		$container = ArrayHelper::getValue($options, 'container');
		$properties = ArrayHelper::getValue($options, 'properties', []);
		$hierarchy = ArrayHelper::getValue($options, 'hierarchy');
		$encoding = ArrayHelper::getValue($options, 'encoding', 'utf-8');
		
		$ret_val = [];
		//first of all are we dealing with a dom document?
		if($parent instanceof DOMElement)
		{
			//if so go through all the elements in the array and create the proper hierarchy
			self::$counter++;
			foreach($hierarchy as $idx=>$elem)
			{ 
				$name = ArrayHelper::getValue($elem, 'name', $idx);
				switch(empty($name))
				{
					case false:
					try {
						$object = new DOMElement(htmlentities(trim($name), ENT_XML1), ArrayHelper::remove($elem, 'value', null));
					} catch (\DOMException $e) { 
						if(defined('YII_DEBUG'))
							throw($e);
						else
							continue;
					}
					//Append this element to the parent
					try {
						$parent->appendChild($object);
					} catch (\DOMException $e) {
						if(defined('YII_DEBUG'))
							throw($e);
						else
							continue;
					}
					switch(empty($elem['children']))
					{
						case false:
						//Create the proper hierarchy for these new elements
						$childOptions = array_merge($options, [
							'hierarchy' => $elem['children'],
							'properties' => []
						]);
						static::buildXml($object, $childOptions, ArrayHelper::getValue((array)$scaffold, $name, $scaffold));
						break;
						
						default:
						$properties = (is_array($properties) && (count($properties) >= 1)) ? $properties : (is_array($elem) ? array_keys($elem) : []);
						//go through the parameters array and set value accordingly
						foreach(array_keys($elem) as $type)
						{
							if(isset($elem[$type]))
							{
								switch($type)
								{
									case 'value':
									$object->nodeValue = $elem[$type];
									break;
									
									case 'text':
									//set the values for this text.property
									$object->appendChild(new DOMText($elem['text']));
									break;
									
									case 'comment':
									//set the values for this text.property
									$object->appendChild(new DOMTComment($elem['comment']));
									break;
									
									case 'attributes':
									//set the values for this attribute.property
									foreach($elem['attributes'] as $attr=>$val)
									{
										$object->appendChild(new DOMAttr(htmlentities(trim($attr), ENT_COMPAT, $encoding), $val));
									}
									break;
								}
								//echo "Created element of type: $type\n<br>";
							}
						}
						break;
					}
					break;
				}
			}
		}
		return $ret_val;
	}
	
   
    /**
     * Convert document to an array
     *
     * @param DOMElement $node XML document's node to convert
     * @return array
     */
	public static function toArray($root, $from='dom')
	{
		switch($from)
		{
			case static::FROM_SIMPLE_XML:
			return static::toArrayFromXimpleXml($root);
			break;
			
			default:
			return static::toArrayFromDom($root);
			break;
		}
	} 
	
	public static function toArrayFromDom($root)
	{
		$result = [];
		if ($root->hasAttributes())
		{
			$attrs = $root->attributes;
			foreach ($attrs as $i => $attr)
				$result['attributes'][$attr->name] = $attr->value;
		}
		
		$children = $root->childNodes;
		if(!$children)
			return $result;
		if ($children && $children->length == 1)
		{
			$child = $children->item(0);
			if ($child->nodeType == XML_TEXT_NODE)
			{
				$result['value'] = $child->nodeValue;
				if (count($result) == 1)
					return $result['value'];
				else
					return $result;
			}
		}
	
		$group = array();
		for($i = 0; $i < $children->length; $i++)
		{
			$child = $children->item($i);
			if((strlen(preg_replace('/[^a-zA-Z0-9]/', '', $child->nodeValue)) == 0) && ($child->nodeName == '#text'))
			{
				$nodeChildren = self::toArrayFromDom($child);
				if(count($nodeChildren) >= 1)
					$result = array_merge($nodeChildren, self::toArrayFromDom($child));
			} else if(!isset($result[$i]) && ($child->hasChildNodes())) {
				if(is_array($nodeChildren = self::toArrayFromDom($child)))
					$result[$i] = [
						'name' => $child->nodeName,
						'children' => $nodeChildren
					];
				else 
					$result[$i] = [
						'name' => $child->nodeName,
						'value' => $nodeChildren
					];
			} else {
				$result[$i] = [
					'name' => $child->nodeName,
					'value' => $child->nodeValue
				];
			}
			if($child->hasAttributes()) {
				foreach($child->attributes as $attribute)
					$result[$i]['attributes'][$attribute->nodeName] = $attribute->nodeValue;
			}
		}
		return $result;
	}

	/*
	 * Function to iterate over a SimpleXML object
	 * @param SimpleXML $simplexml = SimpleXML object
	 */
	public static function toArrayFromSimpleXML($simplexml)
	{
		$ret_val = false;
		switch(!($simplexml->children()))
		{
			case true:
			$ret_val = (string)$simplexml;
			break;
		
			default:
			$ret_val = [];
			foreach($simplexml->children() as $child)
			{
				$name = $child->getName();
				switch($name)
				{
					case 'br':
					continue;
					
					default:
					if(count($simplexml->$name)==1)
					{
						$ret_val[$name] = self::toArraySimpleXML($child);
					}
					else
					{
						$ret_val[][$name] = self::toArraySimpleXML($child);
					}
				}
			}
			break;
		}
		return $ret_val;
	}
}
?>
