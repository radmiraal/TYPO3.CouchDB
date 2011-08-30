<?php
declare(ENCODING = 'utf-8');
namespace TYPO3\CouchDB\Tests\Functional;

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
 * CouchDB generic views functional test.
 *
 * Make sure to configure a test database for the Testing context in
 * Configuration/Testing/Settings.yaml.
 *
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class GenericViewTest extends \TYPO3\FLOW3\Tests\FunctionalTestCase {

	/**
	 * @var \TYPO3\CouchDB\Client
	 */
	protected $client;

	/**
	 * @return void
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function setUp() {
		parent::setUp();

		$configurationManager = $this->objectManager->get('TYPO3\FLOW3\Configuration\ConfigurationManager');
		$backendOptions = $this->objectManager->getSettingsByPath(array('TYPO3', 'FLOW3', 'persistence', 'backendOptions'));

		$this->client = new \TYPO3\CouchDB\Client($backendOptions['dataSourceName']);
		if (isset($backendOptions['database']) && $backendOptions['database'] !== '') {
			$this->client->setDatabaseName($backendOptions['database']);
		}
	}

	/**
	 * @return void
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function tearDown() {
		parent::tearDown();

		$this->client->deleteDatabase();
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function synchronizeCreatesDesignDocument() {
		$design = new Fixtures\Design\CompanyDesign();
		$design->setClient($this->client);

		$design->synchronize();

		$document = $this->client->getDocument('_design/company');
		$this->assertNotNull($document);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function callingViewInitializesDesignDocument() {
		$design = new Fixtures\Design\CompanyDesign();
		$design->setClient($this->client);

		$design->totalPurchasesAmount('12345678');

		$document = $this->client->getDocument('_design/company');
		$this->assertNotNull($document);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function callingViewReturnsResult() {
		$design = new Fixtures\Design\CompanyDesign();
		$design->setClient($this->client);

		$design->synchronize();

		for ($i = 0; $i < 10; $i++) {
			$this->client->createDocument(array(
				'Type' => 'purchase',
				'Customer' => '12345678',
				'Amount' => 13.95 * ($i + 1)
			));
		}

		$result = $design->totalPurchasesAmount('12345678');
		$this->assertEquals(767.25, $result);
	}

}
?>