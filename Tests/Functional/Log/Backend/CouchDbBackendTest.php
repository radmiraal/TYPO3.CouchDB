<?php
namespace TYPO3\CouchDB\Tests\Functional\Log\Backend;

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
 * A CouchDB log backend functional test.
 *
 * Make sure to configure a test database for the Testing context in
 * Configuration/Testing/Settings.yaml.
 *
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class CouchDbBackendTest extends \TYPO3\FLOW3\Tests\FunctionalTestCase {

	/**
	 * @var string
	 */
	protected $databaseName = 'flow3_testing';

	/**
	 * @var string
	 */
	protected $dataSourceName = 'http://127.0.0.1:5984';

	/**
	 * @var \TYPO3\CouchDB\Client
	 */
	protected $client;

	/**
	 * @var \TYPO3\CouchDB\Log\Backend\CouchDbBackend
	 */
	protected $backend;

	/**
	 * @return void
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function setUp() {
		parent::setUp();
		$this->client = $this->objectManager->create('TYPO3\CouchDB\Client', $this->dataSourceName);
		$this->client->setDatabaseName($this->databaseName);
		if ($this->client->databaseExists($this->databaseName)) {
			$this->client->deleteDatabase($this->databaseName);
		}
		$this->backend = $this->objectManager->create('TYPO3\CouchDB\Log\Backend\CouchDbBackend', array('databaseName' => $this->databaseName, 'dataSourceName' => $this->dataSourceName));
		$this->backend->open();
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function appendOneMessageAndRead() {
		$this->backend->append('Foo');
		$logs = $this->backend->read();
		$this->assertEquals(1, count($logs));
		$this->assertEquals('Foo', $logs[0]['message']);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function appendOneMessageAndFilterRead() {
		$this->backend->append('Foo', LOG_INFO);
		$logs = $this->backend->read(0, 100, LOG_WARNING);
		$this->assertEquals(0, count($logs));
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function readWithoutDatabase() {
		$logs = $this->backend->read();
		$this->assertEquals(0, count($logs));
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function viewGetsCreatedIfDatabaseExists() {
		$this->client->createDatabase($this->databaseName);

		$this->backend->append('Test entry', LOG_WARNING);

		$logs = $this->backend->read(0, 100, LOG_WARNING);
		$this->assertEquals(1, count($logs));
	}

}
?>