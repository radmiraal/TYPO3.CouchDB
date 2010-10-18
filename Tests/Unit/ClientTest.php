<?php
namespace F3\CouchDB;

/**
 * A rather functional test for the CouchDB client
 */
class ClientTest extends \F3\Testing\BaseTestCase {
	/**
	 * @var \F3\CouchDB\Client
	 */
	protected $client;

	/**
	 * Setup a CouchDB HTTP connector
	 */
	public function setUp() {
		$this->client = new \F3\CouchDB\Client('http://127.0.0.1:5984');

		$this->client->deleteDatabase('flow3_test');
		$this->client->createDatabase('flow3_test');
		$this->client->setDatabase('flow3_test');
	}

	/**
	 * @test
	 */
	public function listDatabasesWorks() {
		$response = $this->client->listDatabases();
		$this->assertContains('flow3_test', $response);
	}

	/**
	 * @test
	 */
	public function databasesInformationWorks() {
		$response = $this->client->databaseInformation('flow3_test');
		$this->assertEquals('flow3_test', $response->db_name);
	}

	/**
	 * @test
	 */
	public function createDocumentWithIdWorks() {
		$response = $this->client->createDocument('abc', array(
			'name' => 'Foo'
		));
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
		$response = $this->client->createDocument('abc', array(
			'name' => 'Foo'
		));
		$response = $this->client->updateDocument('abc', array(
			'name' => 'Bar',
			'_rev' => $response->getRevision()
		));
		$this->assertTrue($response->isSuccess());

		$response = $this->client->getDocument('abc');
		$this->assertEquals('Bar', $response->name);
	}

	/**
	 * @test
	 */
	public function deleteDocumentWorks() {
		$response = $this->client->createDocument('abc', array(
			'name' => 'Foo'
		));
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
		$this->client->createDocument('abc', array(
			'name' => 'Foo'
		));

		$response = $this->client->listDocuments(array('include_docs' => TRUE));
		$this->assertEquals(1, count($response->rows));
	}

	/**
	 * @test
	 */
	public function getDocumentsWithParameters() {
		$this->client->createDocument('abc', array(
			'name' => 'Foo'
		));
		$this->client->createDocument('def', array(
			'name' => 'Bar'
		));
		$this->client->createDocument('ghi', array(
			'name' => 'Baz'
		));

		$response = $this->client->getDocuments(array('abc', 'ghi'), array('include_docs' => TRUE));
		$this->assertEquals(2, count($response->rows));
	}

	/**
	 * @test
	 */
	public function getDocumentWorks() {
		$this->client->createDocument('abc', array(
			'name' => 'Foo'
		));

		$response = $this->client->getDocument('abc');
		$this->assertEquals('Foo', $response->name);
	}

	/**
	 * @test
	 */
	public function createAndGetDesignDocumentWorks() {
		$this->client->createDocument('_design/test', array('language' => 'javascript'));

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

		$this->client->createDocument('_design/test', array(
			'language' => 'javascript',
			'views' => array(
				'byName' => array(
					'map' => 'function(doc) { if (doc.name) { emit(doc.name, null); } }'
				)
			)
		));

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

		$this->client->createDocument('_design/test', array(
			'language' => 'javascript',
			'views' => array(
				'byName' => array(
					'map' => 'function(doc) { if (doc.name) { emit(doc.name, null); } }'
				)
			)
		));

		$response = $this->client->queryView('test', 'byName', array('keys' => array('Foo', 'Baz'), 'include_docs' => TRUE));
		$this->assertEquals(2, count($response->rows));

		$row = $response->rows[0];
		$this->assertEquals('Foo', $row->doc->name);
		$row = $response->rows[1];
		$this->assertEquals('Baz', $row->doc->name);
	}
}
?>