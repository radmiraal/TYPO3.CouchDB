<?php
namespace F3\CouchDB\Testing;

require_once(__DIR__ . '/BaseFunctionalTestCase.php');

class CouchDbTest extends \F3\CouchDB\Testing\BaseFunctionalTestCase {

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
		$backend = $this->getObjectManager()->get('F3\FLOW3\Persistence\Backend\BackendInterface');
		$backend->resetStorage();
	}

	/**
	 * @test
	 */
	public function backendIsCouchDbBackend() {
		$backend = $this->getObjectManager()->get('F3\FLOW3\Persistence\Backend\BackendInterface');
		$this->assertType('F3\CouchDB\Persistence\Backend\CouchDbBackend', $backend);
	}

	/**
	 * @test
	 */
	public function createEntity() {
		$entity = $this->getObjectManager()->create('F3\CouchDB\Testing\Domain\Model\TestEntity');
		$this->assertNotNull($entity);
	}

	/**
	 * @test
	 */
	public function persistEntity() {
		$repository = $this->getObjectManager()->get('F3\CouchDB\Testing\Domain\Repository\TestEntityRepository');
		$entity = $this->getObjectManager()->create('F3\CouchDB\Testing\Domain\Model\TestEntity');
		$entity->setName('Foobar');
		$repository->add($entity);
		
		$persistenceManager = $this->getObjectManager()->get('F3\FLOW3\Persistence\PersistenceManagerInterface');
		$persistenceManager->persistAll();

		$entities = $repository->findAll();
		$this->assertContains($entity, $entities);
	}

	/**
	 * @test
	 */
	public function queryByEqualsReturnsCorrectObjects() {
		$repository = $this->getObjectManager()->get('F3\CouchDB\Testing\Domain\Repository\TestEntityRepository');

		$entity1 = $this->getObjectManager()->create('F3\CouchDB\Testing\Domain\Model\TestEntity');
		$entity1->setName('Foo');
		$repository->add($entity1);

		$entity2 = $this->getObjectManager()->create('F3\CouchDB\Testing\Domain\Model\TestEntity');
		$entity2->setName('Bar');
		$repository->add($entity2);
		
		$persistenceManager = $this->getObjectManager()->get('F3\FLOW3\Persistence\PersistenceManagerInterface');
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
		$repository = $this->getObjectManager()->get('F3\CouchDB\Testing\Domain\Repository\TestEntityRepository');

		$entity1 = $this->getObjectManager()->create('F3\CouchDB\Testing\Domain\Model\TestEntity');
		$entity1->setName('Foo');
		$repository->add($entity1);

		$entity2 = $this->getObjectManager()->create('F3\CouchDB\Testing\Domain\Model\TestEntity');
		$entity2->setName('Bar');
		$repository->add($entity2);

		$persistenceManager = $this->getObjectManager()->get('F3\FLOW3\Persistence\PersistenceManagerInterface');
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