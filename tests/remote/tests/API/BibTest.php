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

require_once 'APITests.inc.php';
require_once 'include/api.inc.php';

class BibTests extends APITests {
	private static $items;
	
	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();
		API::userClear(self::$config['userID']);
		
		// Create test data
		$xml = API::createItem("book", array(
			"title" => "Title",
			"creators" => array(
				array(
					"creatorType" => "author",
					"firstName" => "First",
					"lastName" => "Last"
				)
			)
		));
		$data = API::parseDataFromItemEntry($xml);
		self::$items[$data['key']] = array(
			"citation" => '<content xmlns:zapi="http://zotero.org/ns/api" zapi:type="citation" type="xhtml"><span xmlns="http://www.w3.org/1999/xhtml">Last, <i>Title</i>.</span></content>',
			"bib" => '<content xmlns:zapi="http://zotero.org/ns/api" zapi:type="bib" type="xhtml"><div xmlns="http://www.w3.org/1999/xhtml" class="csl-bib-body" style="line-height: 1.35; padding-left: 2em; text-indent:-2em;"><div class="csl-entry">Last, First. <i>Title</i>, n.d.</div></div></content>'
		);
		
		$xml = API::createItem("book", array(
			"title" => "Title 2",
			"creators" => array(
				array(
					"creatorType" => "author",
					"firstName" => "First",
					"lastName" => "Last"
				),
				array(
					"creatorType" => "editor",
					"firstName" => "Ed",
					"lastName" => "McEditor"
				)
			)
		));
		$data = API::parseDataFromItemEntry($xml);
		self::$items[$data['key']] = array(
			"citation" => '<content xmlns:zapi="http://zotero.org/ns/api" zapi:type="citation" type="xhtml"><span xmlns="http://www.w3.org/1999/xhtml">Last, <i>Title 2</i>.</span></content>',
			"bib" => '<content xmlns:zapi="http://zotero.org/ns/api" zapi:type="bib" type="xhtml"><div xmlns="http://www.w3.org/1999/xhtml" class="csl-bib-body" style="line-height: 1.35; padding-left: 2em; text-indent:-2em;"><div class="csl-entry">Last, First. <i>Title 2</i>. Edited by Ed McEditor, n.d.</div></div></content>'
		);
	}
	
	public static function tearDownAfterClass() {
		parent::tearDownAfterClass();
		API::userClear(self::$config['userID']);
	}
	
	
	public function testContentCitationSingle() {
		foreach (self::$items as $key => $expected) {
			$response = API::userGet(
				self::$config['userID'],
				"items/$key?key=" . self::$config['apiKey'] . "&content=citation"
			);
			$this->assert200($response);
			$content = API::getContentFromResponse($response);
			// Add zapi namespace
			$content = str_replace('<content ', '<content xmlns:zapi="http://zotero.org/ns/api" ', $content);
			$this->assertXmlStringEqualsXmlString($expected['citation'], $content);
		}
	}
	
	
	public function testContentCitationMulti() {
		$keys = array_keys(self::$items);
		$keyStr = implode(',', $keys);
		
		$response = API::userGet(
			self::$config['userID'],
			"items?key=" . self::$config['apiKey'] . "&itemKey=$keyStr&content=citation"
		);
		$this->assert200($response);
		$xml = API::getXMLFromResponse($response);
		$this->assertEquals(sizeOf($keys), (int) array_shift($xml->xpath('/atom:feed/zapi:totalResults')));
		
		$entries = $xml->xpath('//atom:entry');
		foreach ($entries as $entry) {
			$key = (string) $entry->children("http://zotero.org/ns/api")->key;
			$content = $entry->content->asXML();
			
			// Add zapi namespace
			$content = str_replace('<content ', '<content xmlns:zapi="http://zotero.org/ns/api" ', $content);
			$this->assertXmlStringEqualsXmlString(self::$items[$key]['citation'], $content);
		}
	}
	
	
	public function testContentBibSingle() {
		foreach (self::$items as $key => $expected) {
			$response = API::userGet(
				self::$config['userID'],
				"items/$key?key=" . self::$config['apiKey'] . "&content=bib"
			);
			$this->assert200($response);
			$content = API::getContentFromResponse($response);
			// Add zapi namespace
			$content = str_replace('<content ', '<content xmlns:zapi="http://zotero.org/ns/api" ', $content);
			$this->assertXmlStringEqualsXmlString($expected['bib'], $content);
		}
	}
	
	
	public function testContentBibMulti() {
		$keys = array_keys(self::$items);
		$keyStr = implode(',', $keys);
		
		$response = API::userGet(
			self::$config['userID'],
			"items?key=" . self::$config['apiKey'] . "&itemKey=$keyStr&content=bib"
		);
		$this->assert200($response);
		$xml = API::getXMLFromResponse($response);
		$this->assertEquals(sizeOf($keys), (int) array_shift($xml->xpath('/atom:feed/zapi:totalResults')));
		
		$entries = $xml->xpath('//atom:entry');
		foreach ($entries as $entry) {
			$key = (string) $entry->children("http://zotero.org/ns/api")->key;
			$content = $entry->content->asXML();
			
			// Add zapi namespace
			$content = str_replace('<content ', '<content xmlns:zapi="http://zotero.org/ns/api" ', $content);
			$this->assertXmlStringEqualsXmlString(self::$items[$key]['bib'], $content);
		}
	}
}
