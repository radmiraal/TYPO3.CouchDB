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
		try {
			parent::tearDown();
		} catch(\Exception $e) {
			\TYPO3\FLOW3\var_dump($e, 'Exception during tearDown');
		}

		$this->tearDownPersistence();
	}

	/**
	 * @return void
	 */
	public function tearDownPersistence() {
		$this->persistenceManager->persistAll();
		$this->persistenceManager->clearState();
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function backendIsCouchDbBackend() {
		$backend = $this->objectManager->get('TYPO3\FLOW3\Persistence\Generic\Backend\BackendInterface');
		$this->assertInstanceOf('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', $backend);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function createEntity() {
		$entity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity();
		$this->assertInstanceOf('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity', $entity);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function persistEntity() {
		$repository = $this->objectManager->get('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');
		$entity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity();
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
		$entity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity();
		$entity->setName('Foobar');
		$repository->add($entity);

		$identifier = $this->persistenceManager->getIdentifierByObject($entity);

		$this->tearDownPersistence();

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

		$entity1 = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity();
		$entity1->setName('Foo');
		$repository->add($entity1);

		$entity2 = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity();
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

		$entity1 = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity();
		$entity1->setName('Foo');
		$repository->add($entity1);

		$entity2 = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity();
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
	public function queryPropertiesOfNestedValueObjectReturnsCorrectObjects() {
		$repository = $this->objectManager->get('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');

		$entity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity();
		$entity->setName('Foo');
		$entity->setRelatedValueObject(new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestValueObject('purple'));
		$repository->add($entity);

		$otherEntity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity();
		$otherEntity->setName('Bar');
		$repository->add($otherEntity);

		$this->tearDown();

		$entities = $repository->findByRelatedValueObjectColor('purple');
		$this->assertEquals(1, count($entities));

		$entities = $repository->findByRelatedValueObjectColor('green');
		$this->assertEquals(0, count($entities));
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function nestedSinglevalueEntityIsFetchedCorrectly() {
		$repository = $this->objectManager->get('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');

		$entity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity();
		$entity->setName('Foo');
		$relatedEntity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity();
		$relatedEntity->setName('Bar');
		$entity->setRelatedEntity($relatedEntity);
		$repository->add($entity);
		$repository->add($relatedEntity);

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
	public function nestedMultivalueArrayCollectionEntityIsFetchedCorrectly() {
		$repository = $this->objectManager->get('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');

		$entity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity();
		$entity->setName('Entity with nested ArrayCollection entities');
		$relatedEntities = new \Doctrine\Common\Collections\ArrayCollection();
		$relatedEntity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity();
		$relatedEntity->setName('Nested entity');
		$relatedEntities->add($relatedEntity);
		$entity->setRelatedEntities($relatedEntities);
		$repository->add($entity);
		$repository->add($relatedEntity);

		$this->tearDown();

		$fooEntity = $repository->findOneByName('Entity with nested ArrayCollection entities');
		$this->assertNotNull($fooEntity);
		$this->assertNotNull($fooEntity->getRelatedEntities());
		$this->assertEquals(1, count($fooEntity->getRelatedEntities()));
		$barEntity = $fooEntity->getRelatedEntities()->first();
		$this->assertNotNull($barEntity);
		$this->assertEquals('Nested entity', $barEntity->getName());
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function nestedMultivalueArrayCollectionEntityWithBidirectionalAssociation() {
		$repository = $this->objectManager->get('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');

		$entity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity();
		$entity->setName('Entity with nested ArrayCollection entities');
		$relatedEntities = new \Doctrine\Common\Collections\ArrayCollection();
		$relatedEntity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity();
		$relatedEntity->setName('Nested entity 1');
		$relatedEntity->setRelatedEntity($entity);
		$relatedEntities->add($relatedEntity);
		$repository->add($relatedEntity);
		$relatedEntity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity();
		$relatedEntity->setName('Nested entity 2');
		$relatedEntity->setRelatedEntity($entity);
		$relatedEntities->add($relatedEntity);
		$repository->add($relatedEntity);
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

		$entity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity();
		$entity->setName('Entity with nested array valueobjects');
		$relatedValueObject1 = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestValueObject('Red');
		$relatedValueObject2 = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestValueObject('Blue');
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
	public function getObjectByIdentifierWithUnknownIdentifierShouldNotThrowException() {
		$this->assertNull($this->persistenceManager->getObjectByIdentifier('somenonexistentidentifier'));
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function deletingNestedMultivalueArrayCollectionNonRootEntityDeletesEntity() {
		$repository = $this->objectManager->get('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');

		$entity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity();
		$entity->setName('Entity with nested ArrayCollection non root entities');
		$relatedNonRootEntities = new \Doctrine\Common\Collections\ArrayCollection();
		$relatedNonRootEntity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestNonRootEntity();
		$relatedNonRootEntity->setName('Nested entity 1');
		$relatedNonRootEntities->add($relatedNonRootEntity);
		$entity->setRelatedNonRootEntities($relatedNonRootEntities);
		$repository->add($entity);

		$identifier = $this->persistenceManager->getIdentifierByObject($relatedNonRootEntity);

		$this->tearDown();

		$fooEntity = $repository->findOneByName('Entity with nested ArrayCollection non root entities');
		$fooEntity->getRelatedNonRootEntities()->clear();

		$repository->update($fooEntity);

		$this->tearDown();

		$fooEntity = $repository->findOneByName('Entity with nested ArrayCollection non root entities');
		$this->assertEquals(0, count($fooEntity->getRelatedNonRootEntities()));

		$this->assertTrue($this->persistenceManager->getObjectByIdentifier($identifier) === NULL, 'Assert nested entity was deleted');
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function deletingLazyNestedMultivalueArrayCollectionNonRootEntityDeletesEntity() {
		$repository = $this->objectManager->get('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');

		$entity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity();
		$entity->setName('Entity with nested ArrayCollection lazy non root entities');
		$relatedNonRootEntities = new \Doctrine\Common\Collections\ArrayCollection();
		$relatedNonRootEntity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestLazyNonRootEntity();
		$relatedNonRootEntity->setName('Lazy entity 1');
		$relatedNonRootEntities->add($relatedNonRootEntity);
		$entity->setRelatedLazyNonRootEntities($relatedNonRootEntities);
		$repository->add($entity);

		$identifier = $this->persistenceManager->getIdentifierByObject($relatedNonRootEntity);

		$this->tearDown();

		$fooEntity = $repository->findOneByName('Entity with nested ArrayCollection lazy non root entities');
		$fooEntity->getRelatedLazyNonRootEntities()->clear();

		$repository->update($fooEntity);

		$this->tearDown();

		$fooEntity = $repository->findOneByName('Entity with nested ArrayCollection lazy non root entities');
		$this->assertEquals(0, count($fooEntity->getRelatedLazyNonRootEntities()));

		$this->assertTrue($this->persistenceManager->getObjectByIdentifier($identifier) === NULL, 'Assert lazy nested entity was deleted');
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function unsettingLazyNestedNonRootEntityDeletesEntity() {
		$repository = $this->objectManager->get('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');

		$entity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity();
		$entity->setName('Entity with nested lazy non root entity');
		$relatedNonRootEntity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestLazyNonRootEntity();
		$relatedNonRootEntity->setName('Lazy entity 1');
		$entity->setRelatedLazyNonRootEntity($relatedNonRootEntity);
		$repository->add($entity);

		$identifier = $this->persistenceManager->getIdentifierByObject($relatedNonRootEntity);

		$this->tearDown();

		$fooEntity = $repository->findOneByName('Entity with nested lazy non root entity');
		$fooEntity->setRelatedLazyNonRootEntity(NULL);

		$repository->update($fooEntity);

		$this->tearDown();

		$fooEntity = $repository->findOneByName('Entity with nested lazy non root entity');
		$this->assertTrue($fooEntity->getRelatedLazyNonRootEntity() === NULL, 'Related entity is unset');

		$this->assertTrue($this->persistenceManager->getObjectByIdentifier($identifier) === NULL, 'Assert lazy nested entity was deleted');
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function unsettingReferencedNestedNonRootEntityDoesNotDeleteEntity() {
		$repository = $this->objectManager->get('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');

		$entity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity();
		$entity->setName('Entity with nested lazy non root entity');
		$relatedLazyNonRootEntity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestLazyNonRootEntity();
		$relatedLazyNonRootEntity->setName('Non root entity');
		$entity->setRelatedLazyNonRootEntity($relatedLazyNonRootEntity);
		$entity->setRelatedLazyNonRootEntities(new \Doctrine\Common\Collections\ArrayCollection(array($relatedLazyNonRootEntity)));
		$repository->add($entity);

		$identifier = $this->persistenceManager->getIdentifierByObject($relatedLazyNonRootEntity);

		$this->tearDown();

		$fooEntity = $repository->findOneByName('Entity with nested lazy non root entity');
		$fooEntity->setRelatedLazyNonRootEntity(NULL);

		$repository->update($fooEntity);

		$this->tearDown();

		$fooEntity = $repository->findOneByName('Entity with nested lazy non root entity');
		$this->assertTrue($fooEntity->getRelatedLazyNonRootEntity() === NULL, 'Related entity is unset');

		$referencedEntity = $this->persistenceManager->getObjectByIdentifier($identifier);
		// Test for name to trigger load of lazy entity
		$this->assertEquals('Non root entity', $referencedEntity->getName(), 'Assert lazy nested entity was NOT deleted');
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function valueObjectsDontTriggerDirtyObject() {
		$repository = $this->objectManager->get('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');

		$entity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity();
		$entity->setName('Entity with single valueobject');
		$relatedValueObject = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestValueObject('Red');
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
		$repository->add($nestedEntity);

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

		$entity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntitySubclass();
		$entity->setName('Entity subclass');

		$repository->add($entity);

		$this->tearDown();

		$object = $repository->findOneByName('Entity subclass');
		$this->assertEquals('Entity subclass', $object->getName());
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function updateMappedEntityUpdatesProperties() {
		$repository = $this->objectManager->get('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');
		$entity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity();
		$entity->setName('Foobar');
		$repository->add($entity);

		$identifier = $this->persistenceManager->getIdentifierByObject($entity);

		$this->tearDown();

		$source = array('__identity' => $identifier, 'name' => 'Foofoo');
		$propertyMapper = $this->objectManager->get('TYPO3\FLOW3\Property\PropertyMapper');
		$mappedEntity = $propertyMapper->convert($source, 'TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity');

		$repository->update($mappedEntity);

		$this->assertEquals('Foofoo', $mappedEntity->getName());

		$this->tearDown();

		$entity = $this->persistenceManager->getObjectByIdentifier($identifier);

		$this->assertEquals('Foofoo', $entity->getName());
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function updateMappedEntityWithoutUpdateDoesNotPersist() {
		$repository = $this->objectManager->get('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');
		$entity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity();
		$entity->setName('Foobar');
		$repository->add($entity);

		$identifier = $this->persistenceManager->getIdentifierByObject($entity);

		$this->tearDown();

		$source = array('__identity' => $identifier, 'name' => 'Foofoo');
		$propertyMapper = $this->objectManager->get('TYPO3\FLOW3\Property\PropertyMapper');
		$mappedEntity = $propertyMapper->convert($source, 'TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity');

		$this->assertEquals('Foofoo', $mappedEntity->getName());

		$this->tearDown();

		$entity = $this->persistenceManager->getObjectByIdentifier($identifier);

		$this->assertNotEquals('Foofoo', $entity->getName());
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function updateMappedEntityWithCycle() {
		$repository = $this->objectManager->get('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');
		$entity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity();
		$entity->setName('Foobar');
		$entity->setRelatedEntity($entity);
		$repository->add($entity);

		$identity = $this->persistenceManager->getIdentifierByObject($entity);

		$this->tearDown();

		$source = array('__identity' => $identity, 'name' => 'Foofoo');
		$propertyMapper = $this->objectManager->get('TYPO3\FLOW3\Property\PropertyMapper');
		$mappedEntity = $propertyMapper->convert($source, 'TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity');

		$repository->update($mappedEntity);

		$this->assertEquals('Foofoo', $mappedEntity->getName());
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function idAnnotationIsUsedByPersistenceManager() {
		$repository = $this->objectManager->get('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\CustomIdEntityRepository');
		$entity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\CustomIdEntity('MySpecialId');
		$repository->add($entity);

		$identifier = $this->persistenceManager->getIdentifierByObject($entity);

		$this->assertEquals('MySpecialId', $identifier, 'getIdentifierByObject should return custom id');

		$this->tearDown();

		$persistedEntity = $this->persistenceManager->getObjectByIdentifier($identifier);

		$persistedIdentifier = $this->persistenceManager->getIdentifierByObject($persistedEntity);

		$this->assertEquals('MySpecialId', $persistedEntity->getCustomId(), 'Custom id property should return value');
		$this->assertEquals('MySpecialId', $persistedIdentifier, 'Identifier of entity should have value of annotated property');
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function getObjectByIdentifierWithSoftDeletedEntityIsNull() {
		$repository = $this->objectManager->get('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestSoftDeletedEntityRepository');
		$entity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestSoftDeletedEntity();
		$entity->setSpecialId('MySpecialId');
		$repository->add($entity);

		$identifier = $this->persistenceManager->getIdentifierByObject($entity);

		$this->tearDownPersistence();

		$entity = $this->persistenceManager->getObjectByIdentifier($identifier);
		$this->assertNotNull($entity);

		$repository->remove($entity);

		$this->tearDownPersistence();

		$entity = $this->persistenceManager->getObjectByIdentifier($identifier);

		$this->assertNull($entity);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function getObjectByIdentifierWithKeepIdEntityIsNull() {
		$repository = $this->objectManager->get('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestKeepIdEntityRepository');
		$entity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestKeepIdEntity();
		$entity->setSpecialId('MySpecialId');
		$repository->add($entity);

		$identifier = $this->persistenceManager->getIdentifierByObject($entity);

		$this->tearDownPersistence();

		$entity = $this->persistenceManager->getObjectByIdentifier($identifier);
		$this->assertNotNull($entity);

		$repository->remove($entity);

		$this->tearDownPersistence();

		$entity = $this->persistenceManager->getObjectByIdentifier($identifier);

		$this->assertNull($entity);
	}

	/**
	 * @test
	 * @expectedException \TYPO3\CouchDB\Client\ConflictException
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function removeSoftDeletedEntityWillKeepIdentifier() {
		$repository = $this->objectManager->get('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestSoftDeletedEntityRepository');

		$entity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestSoftDeletedEntity();
		$entity->setSpecialId('MySpecialId');
		$repository->add($entity);

		$identifier = $this->persistenceManager->getIdentifierByObject($entity);

		$this->tearDownPersistence();

		$entity = $this->persistenceManager->getObjectByIdentifier($identifier);
		$this->assertNotNull($entity);

		$repository->remove($entity);

		$this->tearDownPersistence();

		$entity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestSoftDeletedEntity();
		$entity->setSpecialId('MySpecialId');
		$repository->add($entity);

		try {
			$this->tearDownPersistence();
		} catch(\TYPO3\CouchDB\Client\ConflictException $exception) {
			$this->persistenceManager->clearState();
			throw $exception;
		}
	}

	/**
	 * @test
	 * @expectedException \TYPO3\CouchDB\Client\ConflictException
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function removeKeepIdEntityWillKeepIdentifier() {
		$repository = $this->objectManager->get('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestKeepIdEntityRepository');

		$entity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestKeepIdEntity();
		$entity->setSpecialId('MySpecialId');
		$repository->add($entity);

		$identifier = $this->persistenceManager->getIdentifierByObject($entity);

		$this->tearDownPersistence();

		$entity = $this->persistenceManager->getObjectByIdentifier($identifier);
		$this->assertNotNull($entity);

		$repository->remove($entity);

		$this->tearDownPersistence();

		$entity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestKeepIdEntity();
		$entity->setSpecialId('MySpecialId');
		$repository->add($entity);

		try {
			$this->tearDownPersistence();
		} catch(\TYPO3\CouchDB\Client\ConflictException $exception) {
			$this->persistenceManager->clearState();
			throw $exception;
		}
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function countAllWithDeletedSoftDeleteEntityWillReturnZero() {
		$this->markTestIncomplete('This feature needs a change to the view code');
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function countAllWithDeletedKeepIdEntityWillReturnZero() {
		$repository = $this->objectManager->get('TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestKeepIdEntityRepository');

		$entity = new \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestKeepIdEntity();
		$entity->setSpecialId('MySpecialId');
		$repository->add($entity);

		$identifier = $this->persistenceManager->getIdentifierByObject($entity);

		$this->tearDownPersistence();

		$this->assertEquals(1, $repository->countAll());

		$entity = $this->persistenceManager->getObjectByIdentifier($identifier);

		$repository->remove($entity);

		$this->tearDownPersistence();

		$this->assertEquals(0, $repository->countAll(), 'Soft deleted entity should not count to result');
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