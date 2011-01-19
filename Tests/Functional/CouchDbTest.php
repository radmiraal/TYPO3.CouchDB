<?php
declare(ENCODING = 'utf-8');
namespace F3\CouchDB\Tests\Functional;

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
 * A CouchDB backend functional test.
 *
 * Make sure to configure a test database for the Testing context in
 * Configuration/Testing/Settings.yaml.
 *
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class CouchDbTest extends \F3\FLOW3\Tests\FunctionalTestCase {

	/**
	 * @return void
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function setUp() {
		$this->resetPersistenceBackend();
	}

	/**
	 * Delete the database
	 *
	 * @return void
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	protected function resetPersistenceBackend() {
		$backend = $this->objectManager->get('F3\FLOW3\Persistence\Backend\BackendInterface');
		$backend->resetStorage();
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function backendIsCouchDbBackend() {
		$backend = $this->objectManager->get('F3\FLOW3\Persistence\Backend\BackendInterface');
		$this->assertType('F3\CouchDB\Persistence\Backend\CouchDbBackend', $backend);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function createEntity() {
		$entity = $this->objectManager->create('F3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity');
		$this->assertType('F3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity', $entity);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function persistEntity() {
		$repository = $this->objectManager->get('F3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');
		$entity = $this->objectManager->create('F3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity');
		$entity->setName('Foobar');
		$repository->add($entity);

		$persistenceManager = $this->objectManager->get('F3\FLOW3\Persistence\PersistenceManagerInterface');
		$persistenceManager->persistAll();

		$persistenceSession = $this->objectManager->get('F3\FLOW3\Persistence\Session');
		$persistenceSession->destroy();

		$entities = $repository->findAll();
		$foundEntity = $entities[0];
		$this->assertEquals('Foobar', $foundEntity->getName());
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function getObjectByIdentifierLoadsObjectDataFromDocument() {
		$repository = $this->objectManager->get('F3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');
		$entity = $this->objectManager->create('F3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity');
		$entity->setName('Foobar');
		$repository->add($entity);

		$persistenceManager = $this->objectManager->get('F3\FLOW3\Persistence\PersistenceManagerInterface');
		$persistenceManager->persistAll();

		$persistenceSession = $this->objectManager->get('F3\FLOW3\Persistence\Session');
		$identifier = $persistenceSession->getIdentifierByObject($entity);
		$persistenceSession->destroy();

		$backend = $this->objectManager->get('F3\FLOW3\Persistence\Backend\BackendInterface');
		$objectData = $backend->getObjectDataByIdentifier($identifier);

		$this->assertEquals($identifier, $objectData['identifier']);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function queryByEqualsReturnsCorrectObjects() {
		$repository = $this->objectManager->get('F3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');

		$entity1 = $this->objectManager->create('F3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity');
		$entity1->setName('Foo');
		$repository->add($entity1);

		$entity2 = $this->objectManager->create('F3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity');
		$entity2->setName('Bar');
		$repository->add($entity2);

		$persistenceManager = $this->objectManager->get('F3\FLOW3\Persistence\PersistenceManagerInterface');
		$persistenceManager->persistAll();

		$persistenceSession = $this->objectManager->get('F3\FLOW3\Persistence\Session');
		$persistenceSession->destroy();

		$entities = $repository->findByName('Foo');
		$this->assertEquals(1, count($entities));
		$foundEntity1 = $entities[0];
		$this->assertEquals('Foo', $foundEntity1->getName());

		$entities = $repository->findByName('Bar');
		$this->assertEquals(1, count($entities));
		$foundEntity2 = $entities[0];
		$this->assertContains('Bar', $foundEntity2->getName());
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function countyByEqualsReturnsCorrectObjects() {
		$repository = $this->objectManager->get('F3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');

		$entity1 = $this->objectManager->create('F3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity');
		$entity1->setName('Foo');
		$repository->add($entity1);

		$entity2 = $this->objectManager->create('F3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity');
		$entity2->setName('Bar');
		$repository->add($entity2);

		$persistenceManager = $this->objectManager->get('F3\FLOW3\Persistence\PersistenceManagerInterface');
		$persistenceManager->persistAll();

		$persistenceSession = $this->objectManager->get('F3\FLOW3\Persistence\Session');
		$persistenceSession->destroy();

		$count = $repository->countByName('Foo');
		$this->assertEquals(1, $count);

		$count = $repository->countByName('Bar');
		$this->assertEquals(1, $count);

		$count = $repository->countByName('Baz');
		$this->assertEquals(0, $count);

		$count = $repository->countAll();
		$this->assertEquals(2, $count);

	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function nestedSinglevalueEntityIsFetchedCorrectly() {
		$repository = $this->objectManager->get('F3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');

		$entity = $this->objectManager->create('F3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity');
		$entity->setName('Foo');
		$relatedEntity = $this->objectManager->create('F3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity');
		$relatedEntity->setName('Bar');
		$entity->setRelatedEntity($relatedEntity);
		$repository->add($entity);

		$relatedEntity = $this->objectManager->create('F3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity');
		$relatedEntity->setName('Bar');

		$persistenceManager = $this->objectManager->get('F3\FLOW3\Persistence\PersistenceManagerInterface');
		$persistenceManager->persistAll();

		$persistenceSession = $this->objectManager->get('F3\FLOW3\Persistence\Session');
		$persistenceSession->destroy();

		$fooEntity = $repository->findOneByName('Foo');
		$this->assertNotNull($fooEntity);
		$this->assertNotNull($fooEntity->getRelatedEntity());
		$this->assertEquals('Bar', $fooEntity->getRelatedEntity()->getName());
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function nestedMultivalueSplObjectStorageEntityIsFetchedCorrectly() {
		$repository = $this->objectManager->get('F3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');

		$entity = $this->objectManager->create('F3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity');
		$entity->setName('Entity with nested SplObjectStorage entities');
		$relatedEntity = $this->objectManager->create('F3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity');
		$relatedEntity->setName('Nested entity');
		$relatedEntities = new \SplObjectStorage();
		$relatedEntities->attach($relatedEntity);
		$entity->setRelatedEntities($relatedEntities);
		$repository->add($entity);

		$persistenceManager = $this->objectManager->get('F3\FLOW3\Persistence\PersistenceManagerInterface');
		$persistenceManager->persistAll();

		$persistenceSession = $this->objectManager->get('F3\FLOW3\Persistence\Session');
		$persistenceSession->destroy();

		$fooEntity = $repository->findOneByName('Entity with nested SplObjectStorage entities');
		$this->assertNotNull($fooEntity);
		$this->assertNotNull($fooEntity->getRelatedEntities());
		$this->assertEquals(1, count($fooEntity->getRelatedEntities()));
		$fooEntity->getRelatedEntities()->rewind();
		$barEntity = $fooEntity->getRelatedEntities()->current();
		$this->assertNotNull($barEntity);
		$this->assertEquals('Nested entity', $barEntity->getName());
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function nestedMultivalueArrayValueObjectIsHandledCorrectly() {
		$repository = $this->objectManager->get('F3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');

		$entity = $this->objectManager->create('F3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity');
		$entity->setName('Entity with nested array valueobjects');
		$relatedValueObject1 = $this->objectManager->create('F3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestValueObject', 'Red');
		$relatedValueObject2 = $this->objectManager->create('F3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestValueObject', 'Blue');
		$entity->setRelatedValueObjects(array($relatedValueObject1, $relatedValueObject2));
		$repository->add($entity);

		$persistenceManager = $this->objectManager->get('F3\FLOW3\Persistence\PersistenceManagerInterface');
		$persistenceManager->persistAll();

		$persistenceSession = $this->objectManager->get('F3\FLOW3\Persistence\Session');
		$persistenceSession->destroy();

		$fooEntity = $repository->findOneByName('Entity with nested array valueobjects');
		$this->assertNotNull($fooEntity);
		$this->assertNotNull($fooEntity->getRelatedValueObjects());
		$this->assertEquals(2, count($fooEntity->getRelatedValueObjects()));
		$relatedValueObjects = $fooEntity->getRelatedValueObjects();
		$this->assertNotNull($relatedValueObjects[0]);
		$this->assertEquals('Red', $relatedValueObjects[0]->getColor());
		$this->assertNotNull($relatedValueObjects[1]);
		$this->assertEquals('Blue', $relatedValueObjects[1]->getColor());
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function valueObjectsDontTriggerDirtyObject() {
		$repository = $this->objectManager->get('F3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');

		$entity = $this->objectManager->create('F3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity');
		$entity->setName('Entity with single valueobject');
		$relatedValueObject = $this->objectManager->create('F3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestValueObject', 'Red');
		$entity->setRelatedValueObject($relatedValueObject);

		$repository->add($entity);

		$persistenceManager = $this->objectManager->get('F3\FLOW3\Persistence\PersistenceManagerInterface');
		$persistenceManager->persistAll();

		$persistenceSession = $this->objectManager->get('F3\FLOW3\Persistence\Session');
		$persistenceSession->destroy();

		$object = $repository->findOneByName('Entity with single valueobject');

		$metadata = $object->FLOW3_AOP_Proxy_getProperty('FLOW3_Persistence_Metadata');
		$revision = $metadata['CouchDB_Revision'];

		$persistenceManager = $this->objectManager->get('F3\FLOW3\Persistence\PersistenceManagerInterface');
		$persistenceManager->persistAll();

		$persistenceSession = $this->objectManager->get('F3\FLOW3\Persistence\Session');
		$persistenceSession->destroy();

		$object = $repository->findOneByName('Entity with single valueobject');

		$metadata = $object->FLOW3_AOP_Proxy_getProperty('FLOW3_Persistence_Metadata');
		$newRevision = $metadata['CouchDB_Revision'];

		$this->assertEquals($revision, $newRevision);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function nestedEntitiesInValueObjectsAreReconstructed() {
		$repository = $this->objectManager->get('F3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');

		$entity = $this->objectManager->create('F3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity');
		$entity->setName('Entity with valueobject with reference');
		$nestedEntity = $this->objectManager->create('F3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity');
		$nestedEntity->setName('Nested entity');
		$relatedValueObject = $this->objectManager->create('F3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestValueObjectWithReference', $nestedEntity);
		$entity->setRelatedValueObjectWithReference($relatedValueObject);

		$repository->add($entity);

		$persistenceManager = $this->objectManager->get('F3\FLOW3\Persistence\PersistenceManagerInterface');
		$persistenceManager->persistAll();

		$persistenceSession = $this->objectManager->get('F3\FLOW3\Persistence\Session');
		$persistenceSession->destroy();

		$object = $repository->findOneByName('Entity with valueobject with reference');

		$restoredValueObject = $object->getRelatedValueObjectWithReference();
		$restoredNestedEntity = $restoredValueObject->getEntity();

		$this->assertEquals('Nested entity', $restoredNestedEntity->getName());
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function subclassesAreQueriedByParentType() {
		$repository = $this->objectManager->get('F3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');

		$entity = $this->objectManager->create('F3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntitySubclass');
		$entity->setName('Entity subclass');

		$repository->add($entity);

		$persistenceManager = $this->objectManager->get('F3\FLOW3\Persistence\PersistenceManagerInterface');
		$persistenceManager->persistAll();

		$persistenceSession = $this->objectManager->get('F3\FLOW3\Persistence\Session');
		$persistenceSession->destroy();

		$object = $repository->findOneByName('Entity subclass');
		$this->assertEquals('Entity subclass', $object->getName());
	}

	/**
	 * Persist all and destroy the persistence session for the next test
	 *
	 * @return void
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function tearDown() {
		$persistenceManager = $this->objectManager->get('F3\FLOW3\Persistence\PersistenceManagerInterface');
		$persistenceManager->persistAll();

		$persistenceSession = $this->objectManager->get('F3\FLOW3\Persistence\Session');
		$persistenceSession->destroy();
	}
}
?>