<?
/*
    ***** BEGIN LICENSE BLOCK *****
    
    This file is part of the Zotero Data Server.
    
    Copyright © 2012 Center for History and New Media
                     George Mason University, Fairfax, Virginia, USA
                     http://zotero.org
    
    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.
    
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.
    
    You should have received a copy of the GNU Affero General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
    
    ***** END LICENSE BLOCK *****
*/

require_once 'include/http.inc.php';

class API {
	private static $config;
	private static $nsZAPI;
	
	private static function loadConfig() {
		require 'include/config.inc.php';
		foreach ($config as $k => $v) {
			self::$config[$k] = $v;
		}
		self::$nsZAPI = 'http://zotero.org/ns/api';
	}
	
	
	//
	// Item modification methods
	//
	public function getItemTemplate($itemType) {
		$response = API::get("items/new?itemType=$itemType");
		return json_decode($response->getBody());
	}
	
	
	public function createItem($itemType, $data=array(), $context=null) {
		self::loadConfig();
		
		$json = self::getItemTemplate($itemType);
		
		if ($data) {
			foreach ($data as $field => $val) {
				$json->$field = $val;
			}
		}
		
		$response = API::userPost(
			self::$config['userID'],
			"items?key=" . self::$config['apiKey'],
			json_encode(array(
				"items" => array($json)
			)),
			array("Content-Type: application/json")
		);
		if ($context) {
			$context->assert200($response);
		}
		return API::getXMLFromResponse($response);
	}
	
	
	/**
	 * POST a JSON item object to the main test user's account
	 * and return the response
	 */
	public static function postItem($json) {
		return API::postItems(array($json));
	}
	
	
	/**
	 * POST a JSON items object to the main test user's account
	 * and return the response
	 */
	public static function postItems($json) {
		return API::userPost(
			self::$config['userID'],
			"items?key=" . self::$config['apiKey'],
			json_encode(array(
				"items" => $json
			)),
			array("Content-Type: application/json")
		);
	}
	
	
	public function groupCreateItem($groupID, $itemType, $context=null) {
		self::loadConfig();
		
		$response = API::get("items/new?itemType=$itemType");
		$json = json_decode($response->getBody());
		
		$response = API::groupPost(
			$groupID,
			"items?key=" . self::$config['apiKey'],
			json_encode(array(
				"items" => array($json)
			)),
			array("Content-Type: application/json")
		);
		if ($context) {
			$context->assert200($response);
		}
		return API::getXMLFromResponse($response);
	}
	
	
	public function createAttachmentItem($linkMode, $parentKey=false, $context=false) {
		self::loadConfig();
		
		$response = API::get("items/new?itemType=attachment&linkMode=$linkMode");
		$json = json_decode($response->getBody());
		
		if ($parentKey) {
			$url = "items/$parentKey/children";
		}
		else {
			$url = "items";
		}
		
		$response = API::userPost(
			self::$config['userID'],
			$url . "?key=" . self::$config['apiKey'],
			json_encode(array(
				"items" => array($json)
			)),
			array("Content-Type: application/json")
		);
		if ($context) {
			$context->assert200($response);
		}
		$xml = API::getXMLFromResponse($response);
		$data = API::parseDataFromAtomEntry($xml);
		if ($context) {
			$json = json_decode($data['content']);
			$context->assertEquals($linkMode, $json->linkMode);
		}
		return $xml;
	}
	
	
	public function groupCreateAttachmentItem($groupID, $linkMode, $parentKey=false, $context=false) {
		self::loadConfig();
		
		$response = API::get("items/new?itemType=attachment&linkMode=$linkMode");
		$json = json_decode($response->getBody());
		
		if ($parentKey) {
			$url = "items/$parentKey/children";
		}
		else {
			$url = "items";
		}
		
		$response = API::groupPost(
			$groupID,
			$url . "?key=" . self::$config['apiKey'],
			json_encode(array(
				"items" => array($json)
			)),
			array("Content-Type: application/json")
		);
		if ($context) {
			$context->assert200($response);
		}
		$xml = API::getXMLFromResponse($response);
		$data = API::parseDataFromAtomEntry($xml);
		if ($context) {
			$json = json_decode($data['content']);
			$context->assertEquals($linkMode, $json->linkMode);
		}
		return $xml;
	}
	
	
	public function createNoteItem($text="", $parentKey=false, $context=false) {
		self::loadConfig();
		
		$response = API::get("items/new?itemType=note");
		$json = json_decode($response->getBody());
		$json->note = $text;
		
		if ($parentKey) {
			$url = "items/$parentKey/children";
		}
		else {
			$url = "items";
		}
		
		$response = API::userPost(
			self::$config['userID'],
			$url . "?key=" . self::$config['apiKey'],
			json_encode(array(
				"items" => array($json)
			)),
			array("Content-Type: application/json")
		);
		if ($context) {
			$context->assert200($response);
		}
		$xml = API::getXMLFromResponse($response);
		$data = API::parseDataFromAtomEntry($xml);
		if ($context) {
			$json = json_decode($data['content']);
			$context->assertEquals($text, $json->note);
		}
		return $xml;
	}
	
	
	public function createCollection($name, $parent=false, $context=null) {
		self::loadConfig();
		
		$json = array(
			"collections" => array(
				array(
					'name' => $name,
					'parent' => $parent
				)
			)
		);
		
		$response = API::userPost(
			self::$config['userID'],
			"collections?key=" . self::$config['apiKey'],
			json_encode($json),
			array("Content-Type: application/json")
		);
		if ($context) {
			$context->assert200($response);
		}
		return API::getXMLFromResponse($response);
	}
	
	
	//
	// HTTP methods
	//
	public static function get($url, $headers=array(), $auth=false) {
		self::loadConfig();
		$url = self::$config['apiURLPrefix'] . $url;
		$response = HTTP::get($url, $headers, $auth);
		if (self::$config['verbose']) {
			echo "\n\n" . $response->getBody() . "\n";
		}
		return $response;
	}
	
	public static function userGet($userID, $suffix, $headers=array(), $auth=false) {
		return self::get("users/$userID/$suffix", $headers, $auth);
	}
	
	public static function groupGet($groupID, $suffix, $headers=array(), $auth=false) {
		return self::get("groups/$groupID/$suffix", $headers, $auth);
	}
	
	public static function post($url, $data, $headers=array(), $auth=false) {
		self::loadConfig();
		$url = self::$config['apiURLPrefix'] . $url;
		$response = HTTP::post($url, $data, $headers, $auth);
		return $response;
	}
	
	public static function userPost($userID, $suffix, $data, $headers=array(), $auth=false) {
		return self::post("users/$userID/$suffix", $data, $headers, $auth);
	}
	
	public static function groupPost($groupID, $suffix, $data, $headers=array(), $auth=false) {
		return self::post("groups/$groupID/$suffix", $data, $headers, $auth);
	}
	
	public static function put($url, $data, $headers=array(), $auth=false) {
		self::loadConfig();
		$url = self::$config['apiURLPrefix'] . $url;
		$response = HTTP::put($url, $data, $headers, $auth);
		return $response;
	}
	
	public static function userPut($userID, $suffix, $data, $headers=array(), $auth=false) {
		return self::put("users/$userID/$suffix", $data, $headers, $auth);
	}
	
	public static function groupPut($groupID, $suffix, $data, $headers=array(), $auth=false) {
		return self::put("groups/$groupID/$suffix", $data, $headers, $auth);
	}
	
	public static function patch($url, $data, $headers=array(), $auth=false) {
		self::loadConfig();
		$url = self::$config['apiURLPrefix'] . $url;
		$response = HTTP::patch($url, $data, $headers, $auth);
		return $response;
	}
	
	public static function userPatch($userID, $suffix, $data, $headers=array()) {
		return self::patch("users/$userID/$suffix", $data, $headers);
	}
	
	public static function head($url, $headers=array(), $auth=false) {
		self::loadConfig();
		$url = self::$config['apiURLPrefix'] . $url;
		$response = HTTP::head($url, $headers, $auth);
		return $response;
	}
	
	public static function userHead($userID, $suffix, $headers=array(), $auth=false) {
		return self::head("users/$userID/$suffix", $headers, $auth);
	}
	
	public static function delete($url, $headers=array(), $auth=false) {
		self::loadConfig();
		$url = self::$config['apiURLPrefix'] . $url;
		$response = HTTP::delete($url, $headers, $auth);
		return $response;
	}
	
	public static function userDelete($userID, $suffix, $headers=array(), $auth=false) {
		return self::delete("users/$userID/$suffix", $headers, $auth);
	}
	
	public static function groupDelete($groupID, $suffix, $headers=array(), $auth=false) {
		return self::delete("groups/$groupID/$suffix", $headers, $auth);
	}
	
	
	public static function userClear($userID) {
		self::loadConfig();
		return self::userPost(
			$userID,
			"clear",
			"",
			array(),
			array(
				"username" => self::$config['rootUsername'],
				"password" => self::$config['rootPassword']
			)
		);
	}
	
	public static function groupClear($groupID) {
		self::loadConfig();
		return self::groupPost(
			$groupID,
			"clear",
			"",
			array(),
			array(
				"username" => self::$config['rootUsername'],
				"password" => self::$config['rootPassword']
			)
		);
	}
	
	public static function getXMLFromResponse($response) {
		try {
			$xml = new SimpleXMLElement($response->getBody());
		}
		catch (Exception $e) {
			var_dump($response->getBody());
			throw $e;
		}
		$xml->registerXPathNamespace('atom', 'http://www.w3.org/2005/Atom');
		$xml->registerXPathNamespace('zapi', 'http://zotero.org/ns/api');
		return $xml;
	}
	
	
	public static function parseDataFromAtomEntry($entryXML) {
		$key = (string) array_shift($entryXML->xpath('//atom:entry/zapi:key'));
		$version = (string) array_shift($entryXML->xpath('//atom:entry/zapi:version'));
		$etag = (string) array_shift($entryXML->xpath('//atom:entry/atom:content/@zapi:etag'));
		$content = array_shift($entryXML->xpath('//atom:entry/atom:content'));
		if (!$content) {
			throw new Exception("Atom response does not contain <content>");
		}
		
		// If 'content' contains XML, serialize all subnodes
		if ($content->count()) {
			$content = $content->asXML();
		}
		// Otherwise just get string content
		else {
			$content = (string) $content;
		}
		
		return array(
			"key" => $key,
			"etag" => $etag,
			"version" => $version,
			"content" => $content
		);
	}
	
	
	public static function getContentFromResponse($response) {
		$xml = self::getXMLFromResponse($response);
		$data = self::parseDataFromAtomEntry($xml);
		return $data['content'];
	}
	
	
	public static function setKeyOption($userID, $key, $option, $val) {
		self::loadConfig();
		$response = API::get(
			"users/$userID/keys/$key",
			array(),
			array(
				"username" => self::$config['rootUsername'],
				"password" => self::$config['rootPassword']
			)
		);
		if ($response->getStatus() != 200) {
			var_dump($response->getBody());
			throw new Exception("GET returned " . $response->getStatus());
		}
		
		$xml = new SimpleXMLElement($response->getBody());
		foreach ($xml->access as $access) {
			switch ($option) {
			case 'libraryNotes':
				if (isset($access['library'])) {
					$current = (int) $access['notes'];
					if ($current != $val) {
						$access['notes'] = (int) $val;
						$response = API::put(
							"users/" . self::$config['userID'] . "/keys/" . self::$config['apiKey'],
							$xml->asXML(),
							array(),
							array(
								"username" => self::$config['rootUsername'],
								"password" => self::$config['rootPassword']
							)
						);
						if ($response->getStatus() != 200) {
							var_dump($response->getBody());
							throw new Exception("PUT returned " . $response->getStatus());
						}
						break;
					}
				}
			}
		}
	}
	
	
	public static function getPluralObjectType($objectType) {
		if ($objectType == 'search') {
			return $objectType . "es";
		}
		return $objectType . "s";
	}
}
