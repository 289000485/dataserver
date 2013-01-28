<?
/*
    ***** BEGIN LICENSE BLOCK *****
    
    This file is part of the Zotero Data Server.
    
    Copyright © 2010 Center for History and New Media
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

class Zotero_Collections extends Zotero_DataObjects {
	public static $maxLength = 255;
	
	protected static $ZDO_object = 'collection';
	
	protected static $primaryFields = array(
		'id' => 'collectionID',
		'libraryID' => '',
		'key' => '',
		'name' => 'collectionName',
		'dateAdded' => '',
		'dateModified' => '',
		'parent' => 'parentCollectionID',
		'version' => ''
	);
	
	
	public static function search($libraryID, $onlyTopLevel=false, $params) {
		$results = array('results' => array(), 'total' => 0);
		
		$shardID = Zotero_Shards::getByLibraryID($libraryID);
		
		$sql = "SELECT SQL_CALC_FOUND_ROWS DISTINCT ";
		if ($params['format'] == 'keys') {
			$sql .= "`key`";
		}
		else if ($params['format'] == 'versions') {
			$sql .= "`key`, version";
		}
		else {
			$sql .= "collectionID";
		}
		$sql .= " FROM collections WHERE libraryID=? ";
		$sqlParams = array($libraryID);
		
		if ($onlyTopLevel) {
			$sql .= "AND parentCollectionID IS NULL ";
		}
		
		// Pass a list of collectionIDs, for when the initial search is done via SQL
		$collectionIDs = !empty($params['collectionIDs'])
			? $params['collectionIDs'] : array();
		$collectionKeys = !empty($params['collectionKey'])
			? explode(',', $params['collectionKey']): array();
		
		if ($collectionIDs) {
			$sql .= "AND collectionID IN ("
					. implode(', ', array_fill(0, sizeOf($collectionIDs), '?'))
					. ") ";
			$sqlParams = array_merge($sqlParams, $collectionIDs);
		}
		
		if ($collectionKeys) {
			$sql .= "AND `key` IN ("
					. implode(', ', array_fill(0, sizeOf($collectionKeys), '?'))
					. ") ";
			$sqlParams = array_merge($sqlParams, $collectionKeys);
		}
		
		if (!empty($params['newer'])) {
			$sql .= "AND version > ? ";
			$sqlParams[] = $params['newer'];
		}
		
		if (!empty($params['order'])) {
			switch ($params['order']) {
			case 'title':
				$orderSQL = 'collectionName';
				break;
			
			case 'collectionKeyList':
				$orderSQL = "FIELD(`key`,"
						. implode(',', array_fill(0, sizeOf($collectionKeys), '?')) . ")";
				$sqlParams = array_merge($sqlParams, $collectionKeys);
				break;
			
			default:
				$orderSQL = $params['order'];
			}
			
			$sql .= "ORDER BY $orderSQL";
			if (!empty($params['sort'])) {
				$sql .= " {$params['sort']}";
			}
			$sql .= ", ";
		}
		$sql .= "version " . (!empty($params['sort']) ? $params['sort'] : "ASC")
			. ", collectionID " . (!empty($params['sort']) ? $params['sort'] : "ASC") . " ";
		
		if (!empty($params['limit'])) {
			$sql .= "LIMIT ?, ?";
			$sqlParams[] = $params['start'] ? $params['start'] : 0;
			$sqlParams[] = $params['limit'];
		}
		
		if ($params['format'] == 'versions') {
			$rows = Zotero_DB::query($sql, $sqlParams, $shardID);
		}
		// keys and ids
		else {
			$rows = Zotero_DB::columnQuery($sql, $sqlParams, $shardID);
		}
		
		if ($rows) {
			$results['total'] = Zotero_DB::valueQuery("SELECT FOUND_ROWS()", false, $shardID);
			
			if ($params['format'] == 'keys') {
				$results['results'] = $rows;
			}
			else if ($params['format'] == 'versions') {
				foreach ($rows as $row) {
					$results['results'][$row['key']] = $row['version'];
				}
			}
			else {
				$collections = array();
				foreach ($rows as $id) {
					$collections[] = self::get($libraryID, $id);
				}
				$results['results'] = $collections;
			}
		}
		
		return $results;
	}
	
	
	public static function getLongDataValueFromXML(DOMDocument $doc) {
		$xpath = new DOMXPath($doc);
		$attr = $xpath->evaluate('//collections/collection[string-length(@name) > ' . self::$maxLength . ']/@name');
		return $attr->length ? $attr->item(0)->value : false;
	}
	
	
	/**
	 * Converts a DOMElement item to a Zotero_Collection object
	 *
	 * @param	DOMElement		$xml		Collection data as DOMElement
	 * @return	Zotero_Collection			Zotero collection object
	 */
	public static function convertXMLToCollection(DOMElement $xml) {
		$libraryID = (int) $xml->getAttribute('libraryID');
		$col = self::getByLibraryAndKey($libraryID, $xml->getAttribute('key'));
		if (!$col) {
			$col = new Zotero_Collection;
			$col->libraryID = $libraryID;
			$col->key = $xml->getAttribute('key');
		}
		$col->name = $xml->getAttribute('name');
		$parentKey = $xml->getAttribute('parent');
		if ($parentKey) {
			$col->parentKey = $parentKey;
		}
		else {
			$col->parent = false;
		}
		$col->dateAdded = $xml->getAttribute('dateAdded');
		$col->dateModified = $xml->getAttribute('dateModified');
		
		// TODO: move from SyncController?
		
		return $col;
	}
	
	
	/**
	 * Converts a Zotero_Collection object to a SimpleXMLElement item
	 *
	 * @param	object				$item		Zotero_Collection object
	 * @return	SimpleXMLElement					Collection data as SimpleXML element
	 */
	public static function convertCollectionToXML(Zotero_Collection $collection) {
		$xml = new SimpleXMLElement('<collection/>');
		$xml['libraryID'] = $collection->libraryID;
		$xml['key'] = $collection->key;
		$xml['name'] = $collection->name;
		$xml['dateAdded'] = $collection->dateAdded;
		$xml['dateModified'] = $collection->dateModified;
		if ($collection->parent) {
			$parentCol = self::get($collection->libraryID, $collection->parent);
			$xml['parent'] = $parentCol->key;
		}
		
		$children = $collection->getChildren();
		if ($children) {
			$keys = array();
			foreach($children as $child) {
				if ($child['type'] == 'item') {
					$keys[] = $child['key'];
				}
			}
			
			if ($keys) {
				$xml->items = implode(' ', $keys);
			}
		}
		
		return $xml;
	}
	
	
	/**
	 * Converts a Zotero_Collection object to a SimpleXMLElement Atom object
	 *
	 * @param Zotero_Collection  $collection  Zotero_Collection object
	 * @param array  $queryParams
	 * @return SimpleXMLElement  Collection data as SimpleXML element
	 */
	public static function convertCollectionToAtom(Zotero_Collection $collection, $queryParams) {
		// TEMP: multi-format support
		if (!empty($queryParams['content'])) {
			$content = $queryParams['content'];
		}
		else {
			$content = array('none');
		}
		$content = $content[0];
		
		$xml = new SimpleXMLElement(
			'<entry xmlns="' . Zotero_Atom::$nsAtom
			. '" xmlns:zapi="' . Zotero_Atom::$nsZoteroAPI . '"/>'
		);
		
		$title = $collection->name ? $collection->name : '[Untitled]';
		$xml->title = $title;
		
		$author = $xml->addChild('author');
		// TODO: group item creator
		$author->name = Zotero_Libraries::getName($collection->libraryID);
		$author->uri = Zotero_URI::getLibraryURI($collection->libraryID);
		
		$xml->id = Zotero_URI::getCollectionURI($collection);
		
		$xml->published = Zotero_Date::sqlToISO8601($collection->dateAdded);
		$xml->updated = Zotero_Date::sqlToISO8601($collection->dateModified);
		
		$link = $xml->addChild("link");
		$link['rel'] = "self";
		$link['type'] = "application/atom+xml";
		$link['href'] = Zotero_Atom::getCollectionURI($collection);
		
		$parent = $collection->parent;
		if ($parent) {
			$parentCol = self::get($collection->libraryID, $parent);
			$link = $xml->addChild("link");
			$link['rel'] = "up";
			$link['type'] = "application/atom+xml";
			$link['href'] = Zotero_Atom::getCollectionURI($parentCol);
		}
		
		$link = $xml->addChild('link');
		$link['rel'] = 'alternate';
		$link['type'] = 'text/html';
		$link['href'] = Zotero_URI::getCollectionURI($collection);
		
		$xml->addChild('zapi:key', $collection->key, Zotero_Atom::$nsZoteroAPI);
		$xml->addChild('zapi:version', $collection->version, Zotero_Atom::$nsZoteroAPI);
		
		$collections = $collection->getChildCollections();
		$xml->addChild(
			'zapi:numCollections',
			sizeOf($collections),
			Zotero_Atom::$nsZoteroAPI
		);
		$xml->addChild(
			'zapi:numItems',
			$collection->numItems(),
			Zotero_Atom::$nsZoteroAPI
		);
		
		if ($content == 'json') {
			$xml->content['type'] = 'application/json';
			// Deprecated
			$xml->content->addAttribute(
				'zapi:etag',
				$collection->etag,
				Zotero_Atom::$nsZoteroAPI
			);
			// Deprecated
			$xml->content['etag'] = $collection->etag;
			$xml->content = $collection->toJSON();
		}
		
		return $xml;
	}
	
	
	/**
	 * @param Zotero_Collection $collection The collection object to update;
	 *                                      this should be either an existing
	 *                                      collection or a new collection
	 *                                      with a library assigned.
	 * @param object $json Collection data to write
	 * @param boolean [$requireVersion=0] See Zotero_API::checkJSONObjectVersion()
	 */
	public static function updateFromJSON(Zotero_Collection $collection,
	                                      $json,
	                                      $requireVersion=0) {
		Zotero_API::processJSONObjectKey($collection, $json);
		Zotero_API::checkJSONObjectVersion($collection, $json, $requireVersion);
		self::validateJSONCollection($json);
		
		$collection->name = $json->name;
		if (isset($json->parent)) {
			$collection->parentKey = $json->parent;
		}
		else {
			$collection->parent = false;
		}
		$collection->save();
	}
	
	
	private static function validateJSONCollection($json, $requireVersion=false) {
		if (!is_object($json)) {
			throw new Exception('$json must be a decoded JSON object');
		}
		
		$requiredProps = array('name');
		
		foreach ($requiredProps as $prop) {
			if (!isset($json->$prop)) {
				throw new Exception("'$prop' property not provided", Z_ERROR_INVALID_INPUT);
			}
		}
		
		foreach ($json as $key=>$val) {
			switch ($key) {
				// Handled by Zotero_API::checkJSONObjectVersion()
				case 'collectionKey':
				case 'collectionVersion':
					break;
				
				case 'name':
					if (!is_string($val)) {
						throw new Exception("'name' must be a string", Z_ERROR_INVALID_INPUT);
					}
					
					if ($val === "") {
						throw new Exception("Collection name cannot be empty", Z_ERROR_INVALID_INPUT);
					}
					
					if (mb_strlen($val) > 255) {
						throw new Exception("Collection name cannot be longer than 255 characters", Z_ERROR_INVALID_INPUT);
					}
					break;
					
				case 'parent':
					if (!is_string($val) && !empty($val)) {
						throw new Exception("'parent' must be a collection key or FALSE (" . gettype($val) . ")", Z_ERROR_INVALID_INPUT);
					}
					break;
				
				default:
					throw new Exception("Invalid property '$key'", Z_ERROR_INVALID_INPUT);
			}
		}
	}
}
?>
