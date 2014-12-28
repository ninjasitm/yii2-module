<?php

namespace nitm\helpers;

use yii\db\ActiveRecord;
use yii\base\Behavior;

//class that sets up and retrieves, deletes and handles modifying of contact data
class XML extends Behavior
{ 

	/*
	 * Function to iterate over a SimpleXML object
	 * @param SimpleXML $simplexml = SimpleXML object
	 */
	public static function extract($simplexml)
	{
		$ret_val = false;
		switch(!($simplexml->children()))
		{
			case true:
			$ret_val = (string)$simplexml;
			break;
		
			default:
			$ret_val = array();
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
						$ret_val[$name] = $this->extract_simple_xml($child);
					}
					else
					{
						$ret_val[][$name] = $this->extract_simple_xml($child);
					}
				}
			}
			break;
		}
		return $ret_val;
	}
	
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
	
	public function createHierarchy(&$parent, $hierarchy)
	{
		$ret_val = false;
		//first of all are we dealing with a dom document?
		switch($parent instanceof DOMElement)
		{
			case true:
			//if so go through all the elements in the array and create the proper hierarchy
			static::$counter++;
			foreach($hierarchy as $idx=>$elem)
			{ 
				$name = $elem['name'];
				switch(empty($elem['name']))
				{
					case false:
					try {
						$object = static::$document->createElement(htmlentities(trim($name), ENT_XML1));
					} catch (\DOMException $e) { 
						continue;
					}
					//Append this element to the parent
					$parent->appendChild($object);
					static::$hierarchy[static::$container][static::$counter][$parent->nodeName][] = $elem;
					switch(empty($elem['children']))
					{
						case false:
						//Create the proper hierarchy for these new elements
						static::createXmlHierarchy($object, $elem['children']);
						break;
						
						default:
						$properties = static::$properties;
						//go through the parameters array and set value accordingly
						foreach($properties as $type)
						{
							if(isset($elem[$type]))
							{
								switch($type)
								{
									case 'text':
									//set the values for this text.property
									$t = static::$document->createTextNode($elem['text']);
									$object->appendChild($t);
									break;
									
									case 'attributes':
									//set the values for this attribute.property
									foreach($elem['attributes'] as $attr=>$val)
									{
										$a = static::$document->createAttribute(@htmlentities(trim($attr), ENT_COMPAT, static::$document->encoding));
										$a->value = $val;
										$object->appendChild($a);
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
			break;
		}
	} 
	
   
    /**
     * Convert document to an array
     *
     * @param DOMElement $node XML document's node to convert
     * @return array
     */
	public function toArray($root)
	{
		$result = array();
		if ($root->hasAttributes())
		{
			$attrs = $root->attributes;
			foreach ($attrs as $i => $attr)
				$result['attributes'][$attr->name] = $attr->value;
		}
	
		$children = $root->childNodes;
		if ($children->length == 1)
		{
			$child = $children->item(0);
			if ($child->nodeType == XML_TEXT_NODE)
			{
				$result['_value'] = $child->nodeValue;
				if (count($result) == 1)
					return $result['_value'];
				else
					return $result;
			}
		}
	
		$group = array();
		for($i = 0; $i < $children->length; $i++)
		{
			$child = $children->item($i);
			if (!isset($result[$child->nodeName]))
				$result[$child->nodeName] = $this->_toArray($child);
			else
			{
				if (!isset($group[$child->nodeName]))
				{
					$tmp = $result[$child->nodeName];
					$result[$child->nodeName] = array($tmp);
					$group[$child->nodeName] = 1;
				}
	
				$result[$child->nodeName][] = $this->_toArray($child);
			}
		}
		return $result;
	} 
}
?>