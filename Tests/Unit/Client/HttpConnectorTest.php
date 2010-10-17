<?php
namespace F3\CouchDB\Client;

/**
 * A rather functional test for the HTTP connector
 */
class HttpConnectorTest extends \F3\Testing\BaseTestCase {
	/**
	 * @var \F3\CouchDB\Client\HttpConnector
	 */
	protected $connector;

	/**
	 * Setup a CouchDB HTTP connector
	 */
	public function setUp() {
		$this->connector = new \F3\CouchDB\Client\HttpConnector('127.0.0.1', 5984);

		$this->connector->delete('/flow3_test');
		$this->connector->put('/flow3_test');
	}

	/**
	 * @test
	 */
	public function listDatabasesWorks() {
		$response = $this->connector->get('/_all_dbs');
		$this->assertTrue(is_array($response));
	}

	/**
	 * @test
	 */
	public function fetchMultipleDocumentsWorks() {
		$data = json_encode(array('name' => 'Foo'));
		$this->connector->put('/flow3_test/abc', array(), $data);
		$data = json_encode(array('name' => 'Bar'));
		$this->connector->put('/flow3_test/def', array(), $data);
		$data = json_encode(array('name' => 'Baz'));
		$this->connector->put('/flow3_test/ghi', array(), $data);

		$data = json_encode(array('keys' => array('abc', 'ghi')));
		$response = $this->connector->post('/flow3_test/_all_docs', array('include_docs' => TRUE), $data);
		$this->assertObjectHasAttribute('rows', $response);
		$this->assertEquals(2, count($response->rows));
		$row = $response->rows[1];
		$this->assertEquals('Baz', $row->doc->name);
		$this->assertEquals(3, $response->total_rows);
	}

}
?>