<?php
declare(ENCODING = 'utf-8');
namespace F3\CouchDB\Tests\Unit;

/*                                                                        *
 * This script belongs to the FLOW3 package "CouchDB".                    *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License as published by the *
 * Free Software Foundation, either version 3 of the License, or (at your *
 * option) any later version.                                             *
 *                                                                        *
 * This script is distributed in the hope that it will be useful, but     *
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
 * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Lesser       *
 * General Public License for more details.                               *
 *                                                                        *
 * You should have received a copy of the GNU Lesser General Public       *
 * License along with the script.                                         *
 * If not, see http://www.gnu.org/licenses/lgpl.html                      *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

/**
 * A rather functional test for the CouchDB client.
 *
 * Needs a running CouchDB on http://127.0.0.1:5984.
 *
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class ClientTest extends \F3\Testing\BaseTestCase {

	/**
	 * @var \F3\CouchDB\Client
	 */
	protected $client;

	/**
	 * @var string
	 */
	protected $databaseName = 'flow3_testing';

	/**
	 * Setup a CouchDB HTTP connector
	 */
	public function setUp() {
		$connector = new \F3\CouchDB\Client\HttpConnector('127.0.0.1', 5984);
		$mockObjectManager = $this->getMock('F3\FLOW3\Object\ObjectManagerInterface');
		$mockObjectManager->expects($this->once())->method('create')->with('F3\CouchDB\Client\HttpConnector', '127.0.0.1', '5984', NULL, NULL, array())->will($this->returnValue($connector));
		$this->client = $this->getAccessibleMock('F3\CouchDB\Client', array('dummy'), array('http://127.0.0.1:5984'));
		$this->client->_set('objectManager', $mockObjectManager);
		$this->client->initializeObject();

		if ($this->client->databaseExists($this->databaseName)) {
			$this->client->deleteDatabase($this->databaseName);
		}
		$this->client->createDatabase($this->databaseName);
		$this->client->setDatabaseName($this->databaseName);
	}

	/**
	 * Remove the test database
	 */
	public function tearDown() {
		if ($this->client->databaseExists($this->databaseName)) {
			$this->client->deleteDatabase($this->databaseName);
		}
	}

	/**
	 * @test
	 */
	public function listDatabasesWorks() {
		$response = $this->client->listDatabases();
		$this->assertContains($this->databaseName, $response);
	}

	/**
	 * @test
	 */
	public function databasesInformationWorks() {
		$response = $this->client->databaseInformation($this->databaseName);
		$this->assertEquals($this->databaseName, $response->db_name);
	}

	/**
	 * @test
	 */
	public function createDocumentWithIdWorks() {
		$response = $this->client->createDocument(array(
			'name' => 'Foo'
		), 'abc');
		$this->assertTrue($response->isSuccess());
	}

	/**
	 * @test
	 */
	public function createDocumentWithoutIdWorks() {
		$response = $this->client->createDocument(array(
			'name' => 'Bar'
		));
		$this->assertTrue($response->isSuccess());
		$this->assertNotNull($response->getId());
	}

	/**
	 * @test
	 */
	public function createDocumentWithIdInDocumentWorks() {
		$response = $this->client->createDocument(array(
			'_id' => 'bar',
			'name' => 'Bar'
		));
		$this->assertTrue($response->isSuccess());

		$response = $this->client->getDocument('bar');
		$this->assertEquals('Bar', $response->name);
	}

	/**
	 * @test
	 */
	public function updateDocumentWorks() {
		$response = $this->client->createDocument(array(
			'name' => 'Foo'
		), 'abc');
		$response = $this->client->updateDocument(array(
			'name' => 'Bar',
			'_rev' => $response->getRevision()
		), 'abc');
		$this->assertTrue($response->isSuccess());

		$response = $this->client->getDocument('abc');
		$this->assertEquals('Bar', $response->name);
	}

	/**
	 * @test
	 */
	public function deleteDocumentWorks() {
		$response = $this->client->createDocument(array(
			'name' => 'Foo'
		), 'abc');
		$response = $this->client->deleteDocument('abc', $response->getRevision());
		$this->assertTrue($response);

		try {
			$response = $this->client->getDocument('abc');
			$this->fail('Deleted document should not be found');
		} catch(\F3\CouchDB\Client\NotFoundException $e) {

		}
	}

	/**
	 * @test
	 */
	public function listDocumentsWithParameters() {
		$this->client->createDocument(array(
			'name' => 'Foo'
		), 'abc');

		$response = $this->client->listDocuments(array('include_docs' => TRUE));
		$this->assertEquals(1, count($response->rows));
	}

	/**
	 * @test
	 */
	public function getDocumentsWithParameters() {
		$this->client->createDocument(array(
			'name' => 'Foo'
		), 'abc');
		$this->client->createDocument(array(
			'name' => 'Bar'
		), 'def');
		$this->client->createDocument(array(
			'name' => 'Baz'
		), 'ghi');

		$response = $this->client->getDocuments(array('abc', 'ghi'), array('include_docs' => TRUE));
		$this->assertEquals(2, count($response->rows));
	}

	/**
	 * @test
	 */
	public function getDocumentWorks() {
		$this->client->createDocument(array(
			'name' => 'Foo'
		), 'abc');

		$response = $this->client->getDocument('abc');
		$this->assertEquals('Foo', $response->name);
	}

	/**
	 * @test
	 */
	public function createAndGetDesignDocumentWorks() {
		$this->client->createDocument(array('language' => 'javascript'), '_design/test');

		$response = $this->client->getDocument('_design/test');

		$this->assertEquals('javascript', $response->language);
	}

	/**
	 * @test
	 */
	public function queryViewWithParametersWorks() {
		$this->client->createDocument(array(
			'name' => 'Foo'
		));
		$this->client->createDocument(array(
			'name' => 'Bar'
		));

		$this->client->createDocument(array(
			'language' => 'javascript',
			'views' => array(
				'byName' => array(
					'map' => 'function(doc) { if (doc.name) { emit(doc.name, null); } }'
				)
			)
		), '_design/test');

		$response = $this->client->queryView('test', 'byName', array('key' => 'Foo', 'include_docs' => TRUE));
		$this->assertEquals(1, count($response->rows));

		$row = $response->rows[0];
		$this->assertEquals('Foo', $row->doc->name);
	}

	/**
	 * @test
	 */
	public function queryViewWithMultipleKeysWorks() {
		$this->client->createDocument(array(
			'name' => 'Foo'
		));
		$this->client->createDocument(array(
			'name' => 'Bar'
		));
		$this->client->createDocument(array(
			'name' => 'Baz'
		));

		$this->client->createDocument(array(
			'language' => 'javascript',
			'views' => array(
				'byName' => array(
					'map' => 'function(doc) { if (doc.name) { emit(doc.name, null); } }'
				)
			)
		), '_design/test');

		$response = $this->client->queryView('test', 'byName', array('keys' => array('Foo', 'Baz'), 'include_docs' => TRUE));
		$this->assertEquals(2, count($response->rows));

		$row = $response->rows[0];
		$this->assertEquals('Foo', $row->doc->name);
		$row = $response->rows[1];
		$this->assertEquals('Baz', $row->doc->name);
	}
}
?>