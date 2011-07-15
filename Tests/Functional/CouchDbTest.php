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
 * A CouchDB backend functional test.
 *
 * Make sure to configure a test database for the Testing context in
 * Configuration/Testing/Settings.yaml.
 *
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class CouchDbTest extends \TYPO3\FLOW3\Tests\FunctionalTestCase {

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

		$persistenceSession = $this->objectManager->get('TYPO3\FLOW3\Persistence\Generic\Session');
		$persistenceSession->destroy();
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function backendIsCouchDbBackend() {
		$backend = $this->objectManager->get('TYPO3\FLOW3\Persistence\Generic\Backend\BackendInterface');
		$this->assertType('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', $backend);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function createEntity() {
		$entity = $this->objectManager->create('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity');
		$this->assertType('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity', $entity);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function persistEntity() {
		$repository = $this->objectManager->get('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');
		$entity = $this->objectManager->create('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity');
		$entity->setName('Foobar');
		$repository->add($entity);

		$this->tearDown();

		$entities = $repository->findAll();

		$foundEntity = $entities[0];
		$this->assertEquals('Foobar', $foundEntity->getName());
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function getObjectByIdentifierLoadsObjectDataFromDocument() {
		$repository = $this->objectManager->get('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');
		$entity = $this->objectManager->create('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity');
		$entity->setName('Foobar');
		$repository->add($entity);

		$persistenceManager = $this->objectManager->get('TYPO3\FLOW3\Persistence\PersistenceManagerInterface');
		$persistenceManager->persistAll();

		$persistenceSession = $this->objectManager->get('TYPO3\FLOW3\Persistence\Generic\Session');
		$identifier = $persistenceSession->getIdentifierByObject($entity);
		$persistenceSession->destroy();

		$backend = $this->objectManager->get('TYPO3\FLOW3\Persistence\Generic\Backend\BackendInterface');
		$objectData = $backend->getObjectDataByIdentifier($identifier);

		$this->assertEquals($identifier, $objectData['identifier']);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function queryByEqualsReturnsCorrectObjects() {
		$repository = $this->objectManager->get('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');

		$entity1 = $this->objectManager->create('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity');
		$entity1->setName('Foo');
		$repository->add($entity1);

		$entity2 = $this->objectManager->create('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity');
		$entity2->setName('Bar');
		$repository->add($entity2);

		$this->tearDown();

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
		$repository = $this->objectManager->get('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');

		$entity1 = $this->objectManager->create('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity');
		$entity1->setName('Foo');
		$repository->add($entity1);

		$entity2 = $this->objectManager->create('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity');
		$entity2->setName('Bar');
		$repository->add($entity2);

		$this->tearDown();

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
		$repository = $this->objectManager->get('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');

		$entity = $this->objectManager->create('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity');
		$entity->setName('Foo');
		$relatedEntity = $this->objectManager->create('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity');
		$relatedEntity->setName('Bar');
		$entity->setRelatedEntity($relatedEntity);
		$repository->add($entity);

		$this->tearDown();

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
		$repository = $this->objectManager->get('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');

		$entity = $this->objectManager->create('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity');
		$entity->setName('Entity with nested SplObjectStorage entities');
		$relatedEntities = new \SplObjectStorage();
		$relatedEntity = $this->objectManager->create('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity');
		$relatedEntity->setName('Nested entity');
		$relatedEntities->attach($relatedEntity);
		$entity->setRelatedEntities($relatedEntities);
		$repository->add($entity);

		$this->tearDown();

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
	public function nestedMultivalueSplObjectStorageEntityWithBidirectionalAssociation() {
		$repository = $this->objectManager->get('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');

		$entity = $this->objectManager->create('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity');
		$entity->setName('Entity with nested SplObjectStorage entities');
		$relatedEntities = new \SplObjectStorage();
		$relatedEntity = $this->objectManager->create('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity');
		$relatedEntity->setName('Nested entity 1');
		$relatedEntity->setRelatedEntity($entity);
		$relatedEntities->attach($relatedEntity);
		$relatedEntity = $this->objectManager->create('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity');
		$relatedEntity->setName('Nested entity 2');
		$relatedEntity->setRelatedEntity($entity);
		$relatedEntities->attach($relatedEntity);
		$entity->setRelatedEntities($relatedEntities);
		$repository->add($entity);

		$this->tearDown();

		$fooEntity = $repository->findOneByName('Nested entity 1');
		$this->assertNotNull($fooEntity);
		$this->assertNotNull($fooEntity->getRelatedEntity());
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function nestedMultivalueArrayValueObjectIsHandledCorrectly() {
		$repository = $this->objectManager->get('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');

		$entity = $this->objectManager->create('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity');
		$entity->setName('Entity with nested array valueobjects');
		$relatedValueObject1 = $this->objectManager->create('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestValueObject', 'Red');
		$relatedValueObject2 = $this->objectManager->create('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestValueObject', 'Blue');
		$entity->setRelatedValueObjects(array($relatedValueObject1, $relatedValueObject2));
		$repository->add($entity);

		$this->tearDown();

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
		$repository = $this->objectManager->get('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');

		$entity = $this->objectManager->create('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity');
		$entity->setName('Entity with single valueobject');
		$relatedValueObject = $this->objectManager->create('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestValueObject', 'Red');
		$entity->setRelatedValueObject($relatedValueObject);

		$repository->add($entity);

		$this->tearDown();

		$object = $repository->findOneByName('Entity with single valueobject');

		$metadata = $object->FLOW3_Persistence_Metadata;
		$revision = $metadata['CouchDB_Revision'];

		$this->tearDown();

		$object = $repository->findOneByName('Entity with single valueobject');

		$metadata = $object->FLOW3_Persistence_Metadata;
		$newRevision = $metadata['CouchDB_Revision'];

		$this->assertEquals($revision, $newRevision);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function nestedEntitiesInValueObjectsAreReconstructed() {
		$repository = $this->objectManager->get('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');

		$entity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity();
		$entity->setName('Entity with valueobject with reference');
		$nestedEntity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity();
		$nestedEntity->setName('Nested entity');
		$relatedValueObject = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestValueObjectWithReference($nestedEntity);
		$entity->setRelatedValueObjectWithReference($relatedValueObject);

		$repository->add($entity);

		$this->tearDown();

		$object = $repository->findOneByName('Entity with valueobject with reference');

		$restoredValueObject = $object->getRelatedValueObjectWithReference();
		$restoredNestedEntity = $restoredValueObject->getEntity();

		$this->assertEquals('Nested entity', $restoredNestedEntity->getName());
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function arrayWithStringValuesIsReconstituted() {
		$repository = $this->objectManager->get('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');

		$entity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity();
		$entity->setName('Entity with array of strings');
		$entity->setValueArray(array('Foo', 'Bar'));

		$repository->add($entity);

		$this->tearDown();

		$object = $repository->findOneByName('Entity with array of strings');

		$this->assertEquals(array('Foo', 'Bar'), $object->getValueArray());
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function subclassesAreQueriedByParentType() {
		$repository = $this->objectManager->get('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');

		$entity = $this->objectManager->create('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntitySubclass');
		$entity->setName('Entity subclass');

		$repository->add($entity);

		$this->tearDown();

		$object = $repository->findOneByName('Entity subclass');
		$this->assertEquals('Entity subclass', $object->getName());
	}

	/**
	 * Delete the database
	 *
	 * @return void
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	protected function resetPersistenceBackend() {
		$backend = $this->objectManager->get('TYPO3\FLOW3\Persistence\Generic\Backend\BackendInterface');
		$backend->resetStorage();
	}
}
?>