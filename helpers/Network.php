<?php

namespace nitm\helpers;

use yii\db\ActiveRecord;
use yii\base\Behavior;
use nitm\models\DB;

if(!@isset($_SESSION))
{
	$_SESSION = array();
	$_SESSION['SERVER_NAME'] = empty($_SESSION['SERVER_NAME']) ? "_cli.mhdevnet.net" : $_SESSION['SERVER_NAME'];
}

//class that sets up and retrieves, deletes and handles modifying of contact data
class Network extends Behavior
{
	public $ip;
	public $host;
	public $coords;
	const IP_REGEX = '/^(([1-9]?[0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5]).){3}([1-9]?[0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$/';
	const DOMAIN_REGEX = '/^(([a-zA-Z]|[a-zA-Z][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z]|[A-Za-z][A-Za-z0-9\-]*[A-Za-z0-9])$/';
	const URL_REGEX = '/[-a-zA-Z0-9@:%_\+.~#?&\/\/=]{2,256}\.[a-z]{2,4}\b(\/[-a-zA-Z0-9@:%_\+.~#?&\/\/=]*)?/i';
	const IP_URL_REGEX = '/[-a-zA-Z0-9@:%_\+.~#?&\/\/=]{2,256}(([1-9]?[0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5]).){3}([1-9]?[0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$/i';

	public function behaviors()
	{
		$behaviors = [
		];
		return array_merge(parent::behaviors(), $behaviors);
	}

	/*
		function to get hostname instead of gethostbyaddr
		$param string $ip = ip address to lookup
		@return string hostname
	*/
	public static function getHost($ip)
	{
		//Make sure the input is not going to do anything unexpected
		//IPs must be in the form x.x.x.x with each x as a number
		$testar = explode('.',$ip);
		if (count($testar)!=4)
		{
			return $ip;
		}
		for ($i=0;$i<4;++$i)
		{
			if (!is_numeric($testar[$i]))
			{
				return $ip;
			}
		}
		$host = `host -W 1 $ip`;
		$host = ($host) ? end(explode(' ', $host)) : $ip;
		$host = (strpos($host, "SERVFAIL") === false) ? $host : $ip;
		return $host;
	}

	//xml request/curl related functions
	/**
	* Send a GET requst using cURL
	* @param string $url to request
	* @param array $get values to send
	* @param array $options for cURL
	* @return string
	*/
	public static function getCurlXml($url, $get=[], $options=[])
	{
		$ret_val = '';
		$response = static::getCurlData($url, $get, $options);
		$xml = simplexml_load_string(XML::convertEntities($response, true));
		if($xml)
		{
			$ret_val = XML::extractXml($xml);
		}
		else
		{
			pr(libxml_get_last_error());
		}
		return $ret_val;
	}

	//xml request/curl related functions
	/**
	* Send a GET requst using cURL
	* @param string $url to request
	* @param array $get values to send
	* @param array $options for cURL
	* @return string
	*/
	public static function getCurlData($url, $get=[], $options=[])
	{
		$ret_val = false;
		$defaults = [
			CURLOPT_URL => $url. (strpos($url, '?') === FALSE ? '?' : ''). http_build_query($get),
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_TIMEOUT => 4
		];

		$ch = curl_init();
		curl_setopt_array($ch, ($options + $defaults));
		$response = (!$result = curl_exec($ch)) ? $ret_val : preg_replace('/\<br \/>/', '', $result);
		switch($response)
		{
			case false:
			trigger_error(curl_error($ch));
			print_r(curl_error($ch));
			print_r(curl_errno($ch));
			break;

			default:
			$ret_val = $response;
			break;
		}
		return $ret_val;
	}

	/**
	 * Get the size of a URL using the HEAD method
	 * @param  string $url The URL we're checking
	 * @return int      Size of URL
	 */
	public static function getInfo($url, $return=[]) {
		$data = static::getCurlData($url, [], [
			CURLOPT_NOBODY => true,
			CURLOPT_HEADER => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT']
		]);
		$result = [];
		if($data) {
			$contentLength = "unknown";
			$status = "unknown";

			preg_match( "/^HTTP\/[21]\.[01] (\d\d\d)/", $data, $matches);
			$status = (int)$matches[1];

			if($status == 200 || ($status > 300 && $status <= 308)) {
				foreach([
					'length' => "/Content-Length: (\d+)/",
					'type' => "/Content-Type: ([\w\/]+)/"
				] as $type=>$regex) {
					preg_match($regex, $data, $matches);
					$value = $matches[1];
					$result[$type] = $value;
				}
			}
		}
		return $result;
	}

	public static function isValidHost($url)
	{
		return (bool) (preg_match(self::IP_REGEX, $url) || preg_match(self::DOMAIN_REGEX, $url) || preg_match(self::URL_REGEX, $url) || preg_match(self::IP_URL_REGEX, $url));
	}

	public static function isValidIp($url)
	{
		return (bool) preg_match(self::IP_REGEX, $url);
	}

	public static function isValidDomain($url)
	{
		return (bool) preg_match(self::DOMAIN_REGEX, $url);
	}

	public static function isValidUrl($url)
	{
		return (bool) preg_match(self::URL_REGEX, $url);
	}

	public static function isValidIpUrl($url)
	{
		return (bool) preg_match(self::IP_URL_REGEX, $url);
	}


}
?>
