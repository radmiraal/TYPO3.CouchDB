<?php
namespace TYPO3\CouchDB\Tests\Functional\Persistence\Backend;

/*                                                                        *
 * This script belongs to the Flow package "CouchDB".                    *
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
 */
class FlowDesignTest extends \TYPO3\Flow\Tests\FunctionalTestCase {

	/**
	 * @var \TYPO3\CouchDB\Client
	 */
	protected $client;

	/**
	 * @var \TYPO3\CouchDB\Persistence\Backend\FlowDesign
	 */
	protected $design;

	/**
	 * @var boolean
	 */
	static protected $testablePersistenceEnabled = TRUE;

	/**
	 * @return void
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function setUp() {
		parent::setUp();

		$configurationManager = $this->objectManager->get('TYPO3\Flow\Configuration\ConfigurationManager');
		$backendOptions = $this->objectManager->getSettingsByPath(array('TYPO3', 'Flow', 'persistence', 'backendOptions'));

		$this->client = new \TYPO3\CouchDB\Client($backendOptions['dataSourceName']);
		if (isset($backendOptions['database']) && $backendOptions['database'] !== '') {
			$this->client->setDatabaseName($backendOptions['database']);
		}

		$this->design = new \TYPO3\CouchDB\Persistence\Backend\FlowDesign($this->client);

		if ($this->client->databaseExists()) {
			$this->client->deleteDatabase();
		}
	}

	/**
	 * Persist all and destroy the persistence session for the next test
	 *
	 * @return void
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function tearDown() {
		parent::tearDown();

		$this->objectManager->get('TYPO3\Flow\Persistence\Generic\Session')->destroy();
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function entityReferencesReturnsSimpleAndArrayReferences() {
		$baseEntity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity();
		$simpleReferencedEntity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity();
		$baseEntity->setRelatedEntity($simpleReferencedEntity);
		$arrayReferencedEntity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity();
		$relatedEntities = new \Doctrine\Common\Collections\ArrayCollection();
		$relatedEntities->add($arrayReferencedEntity);
		$relatedEntities->add($baseEntity);
		$baseEntity->setRelatedEntities($relatedEntities);

		$this->persistenceManager->add($baseEntity);

		$baseIdentifier = $this->persistenceManager->getIdentifierByObject($baseEntity);
		$simpleReferenceIdentifier = $this->persistenceManager->getIdentifierByObject($simpleReferencedEntity);
		$arrayReferenceIdentifier = $this->persistenceManager->getIdentifierByObject($arrayReferencedEntity);

		$this->persistenceManager->persistAll();

		$identifiers = $this->design->entityReferences($simpleReferenceIdentifier);
		$this->assertEquals(1, count($identifiers));
		$this->assertEquals($baseIdentifier, $identifiers[0]);

		$identifiers = $this->design->entityReferences($arrayReferenceIdentifier);
		$this->assertEquals(1, count($identifiers));
		$this->assertEquals($baseIdentifier, $identifiers[0]);

		$identifiers = $this->design->entityReferences($baseIdentifier);
		$this->assertEquals(0, count($identifiers));
	}


}
?>