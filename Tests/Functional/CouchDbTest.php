<?php
declare(ENCODING = 'utf-8');
namespace F3\CouchDB\Tests\Functional;

class CouchDbTest extends \F3\Testing\FunctionalTestCase {

	/**
	 *
	 */
	public function setUp() {
		$this->resetPersistenceBackend();
	}

	/**
	 *
	 */
	protected function resetPersistenceBackend() {
		$backend = $this->objectManager->get('F3\FLOW3\Persistence\Backend\BackendInterface');
		$backend->resetStorage();
	}

	/**
	 * @test
	 */
	public function backendIsCouchDbBackend() {
		$backend = $this->objectManager->get('F3\FLOW3\Persistence\Backend\BackendInterface');
		$this->assertType('F3\CouchDB\Persistence\Backend\CouchDbBackend', $backend);
	}

	/**
	 * @test
	 */
	public function createEntity() {
		$entity = $this->objectManager->create('F3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity');
		$this->assertNotNull($entity);
	}

	/**
	 * @test
	 */
	public function persistEntity() {
		$repository = $this->objectManager->get('F3\CouchDB\Tests\Functional\Fixtures\Domain\Repository\TestEntityRepository');
		$entity = $this->objectManager->create('F3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity');
		$entity->setName('Foobar');
		$repository->add($entity);

		$persistenceManager = $this->objectManager->get('F3\FLOW3\Persistence\PersistenceManagerInterface');
		$persistenceManager->persistAll();

		$entities = $repository->findAll();
		$this->assertContains($entity, $entities);
	}

	/**
	 * @test
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

		$entities = $repository->findByName('Foo');
		$this->assertEquals(1, count($entities));
		$this->assertContains($entity1, $entities);

		$entities = $repository->findByName('Bar');
		$this->assertEquals(1, count($entities));
		$this->assertContains($entity2, $entities);
	}

	/**
	 * @test
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

		$count = $repository->countByName('Foo');
		$this->assertEquals(1, $count);

		$count = $repository->countByName('Bar');
		$this->assertEquals(1, $count);

		$count = $repository->countByName('Baz');
		$this->assertEquals(0, $count);

		$count = $repository->countAll();
		$this->assertEquals(2, $count);

	}
}
?>