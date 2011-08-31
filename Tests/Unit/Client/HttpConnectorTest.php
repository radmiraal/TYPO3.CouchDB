<?php
namespace TYPO3\CouchDB\Tests\Unit\Client;

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
 * A rather functional test for the HTTP connector
 *
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class HttpConnectorTest extends \TYPO3\FLOW3\Tests\UnitTestCase {

	/**
	 * @var \TYPO3\CouchDB\Client\HttpConnector
	 */
	protected $connector;

	/**
	 * Setup a CouchDB HTTP connector
	 */
	public function setUp() {
		$this->connector = new \TYPO3\CouchDB\Client\HttpConnector('127.0.0.1', 5984);
		$this->connector->put('/flow3_test');
	}

	/**
	 * Remove flow3_test database
	 */
	public function tearDown() {
		$this->connector->delete('/flow3_test');
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