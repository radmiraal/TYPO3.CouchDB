<?php
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
 * CouchDB Lucene backend functional test.
 *
 * Make sure to configure a test database for the Testing context in
 * Configuration/Testing/Settings.yaml and CouchDB-Lucene is running.
 *
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class CouchDbLuceneTest extends \TYPO3\Flow\Tests\FunctionalTestCase {

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
		$backendOptions = $this->objectManager->getSettingsByPath(array('TYPO3', 'FLOW3', 'persistence', 'backendOptions'));

		if (!$backendOptions['enableCouchdbLucene']) {
			$this->markTestSkipped('CouchDB Lucene not enabled');
		}

		$this->resetPersistenceBackend();
	}

	/**
	 * Persist all and destroy the persistence session for the next test
	 *
	 * @return void
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function tearDown() {
		parent::tearDown();

		$this->resetPersistenceBackend();
	}

	/**
	 * @test
	 * @author Felix Oertel <oertel@networkteam.com>
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function queryByLikeReturnsCorrectObjects() {
		$repository = $this->objectManager->get('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');

		$entity1 = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity();
		$entity1->setName('FooABCFoo');
		$repository->add($entity1);

		$entity2 = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity();
		$entity2->setName('BarXYZBar');
		$repository->add($entity2);

		$persistenceManager = $this->objectManager->get('TYPO3\Flow\Persistence\PersistenceManagerInterface');
		$persistenceManager->persistAll();

		$persistenceSession = $this->objectManager->get('TYPO3\Flow\Persistence\Generic\Session');
		$persistenceSession->destroy();

		$entities = $repository->findByNameLike('foo*');
		$this->assertEquals(1, count($entities));
		$foundEntity1 = $entities[0];
		$this->assertEquals('FooABCFoo', $foundEntity1->getName());

		$entities = $repository->findByNameLike('bar*bar');
		$this->assertEquals(1, count($entities));
		$foundEntity2 = $entities[0];
		$this->assertContains('BarXYZBar', $foundEntity2->getName());
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function indexingAndQueryingSingleNestedValueObject() {
		$repository = $this->objectManager->get('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');

		$entity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity();
		$entity->setName('Some entity');
		$valueObject = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestValueObject('green');
		$entity->setRelatedValueObject($valueObject);
		$repository->add($entity);

		$entity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity();
		$entity->setName('Some other entity');
		$valueObject = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestValueObject('blue');
		$entity->setRelatedValueObject($valueObject);
		$repository->add($entity);

		$persistenceManager = $this->objectManager->get('TYPO3\Flow\Persistence\PersistenceManagerInterface');
		$persistenceManager->persistAll();

		$persistenceSession = $this->objectManager->get('TYPO3\Flow\Persistence\Generic\Session');
		$persistenceSession->destroy();

		$entities = $repository->findByColor('green');
		$this->assertEquals(1, count($entities));
		$foundEntity = $entities[0];
		$this->assertEquals('Some entity', $foundEntity->getName());
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function queryingWithLogicalOr() {
		$repository = $this->objectManager->get('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');

		$entity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity();
		$entity->setName('Some entity');
		$valueObject = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestValueObject('green');
		$entity->setRelatedValueObject($valueObject);
		$repository->add($entity);

		$persistenceManager = $this->objectManager->get('TYPO3\Flow\Persistence\PersistenceManagerInterface');
		$persistenceManager->persistAll();

		$persistenceSession = $this->objectManager->get('TYPO3\Flow\Persistence\Generic\Session');
		$persistenceSession->destroy();

		$entities = $repository->findByNameOrColor('Foo', 'green');
		$this->assertEquals(1, count($entities));
		$foundEntity = $entities[0];
		$this->assertEquals('Some entity', $foundEntity->getName());

		$entities = $repository->findByNameOrColor('Some entity', 'foo');
		$this->assertEquals(1, count($entities));
		$foundEntity = $entities[0];
		$this->assertEquals('Some entity', $foundEntity->getName());
	}

	/**
	 * Larger documents are transferred differently from CouchDB Lucene, so the
	 * HttpConnector had a bug with wrong handling of chunked transfer.
	 *
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function chunkedDataIsTransferredCorrectly() {
		$repository = $this->objectManager->get('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');

		$entity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity();
		$entity->setName(str_repeat('Some entity-', 2000));
		$entity->setRelatedValueObject(new Fixtures\Domain\Model\TestValueObject('green'));
		$repository->add($entity);

		$persistenceManager = $this->objectManager->get('TYPO3\Flow\Persistence\PersistenceManagerInterface');
		$persistenceManager->persistAll();

		$persistenceSession = $this->objectManager->get('TYPO3\Flow\Persistence\Generic\Session');
		$persistenceSession->destroy();

		$entities = $repository->findByColor('green')->toArray();
		$this->assertEquals(1, count($entities));
	}

	/**
	 * Delete the database
	 *
	 * @return void
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	protected function resetPersistenceBackend() {
		$backend = $this->objectManager->get('TYPO3\Flow\Persistence\Generic\Backend\BackendInterface');
		$backend->resetStorage();
	}

}
?>