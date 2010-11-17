<?php
declare(ENCODING = 'utf-8');
namespace F3\CouchDB\Tests\Unit\Persistence\Backend;

/*                                                                        *
 * This script belongs to the FLOW3 package "CouchDB".                    *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License as published by the Free   *
 * Software Foundation, either version 3 of the License, or (at your      *
 * option) any later version.                                             *
 *                                                                        *
 * This script is distributed in the hope that it will be useful, but     *
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
 * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General      *
 * Public License for more details.                                       *
 *                                                                        *
 * You should have received a copy of the GNU General Public License      *
 * along with the script.                                                 *
 * If not, see http://www.gnu.org/licenses/gpl.html                       *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

/**
 * Unit tests for the CouchDB persistence backend
 *
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class CouchDbBackendTest extends \F3\Testing\BaseTestCase {

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function initializeConnectsToCouchDb() {
		$mockReflectionService = $this->getMock('F3\FLOW3\Reflection\ReflectionService');
		$mockReflectionService->expects($this->any())->method('getClassSchemata');

		$backend = $this->getMock('F3\CouchDB\Persistence\Backend\CouchDbBackend', array('connect'));
		$backend->expects($this->once())->method('connect');
		$backend->injectReflectionService($mockReflectionService);

		$backend->initialize(array('database' => 'foo', 'dataSourceName' => 'http://1.2.3.4:5678'));
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function connectCreatesClientWithDataSourceNameAndSetsDatabasename() {
		$mockClient = $this->getMock('F3\CouchDB\Client', array(), array(), '', FALSE);
		$mockClient->expects($this->atLeastOnce())->method('setDatabaseName')->with('foo');

		$mockObjectManager = $this->getMock('F3\FLOW3\Object\ObjectManagerInterface');
		$mockObjectManager->expects($this->atLeastOnce())->method('create')->with('F3\CouchDB\Client', 'http://1.2.3.4:5678')->will($this->returnValue($mockClient));

		$backend = $this->getAccessibleMock('F3\CouchDB\Persistence\Backend\CouchDbBackend', array('dummy'));
		$backend->injectObjectManager($mockObjectManager);
		$backend->_set('dataSourceName', 'http://1.2.3.4:5678');
		$backend->_set('databaseName', 'foo');

		$backend->_call('connect');
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function storeObjectReturnsReconstitutedObjectStateIfSessionHasObject() {
		$className = 'stdClass';
		$object = $this->getMock($className, array('FLOW3_AOP_Proxy_getProxyTargetClassName'));
		$object->expects($this->any())->method('FLOW3_AOP_Proxy_getProxyTargetClassName')->will($this->returnValue($className));
		$identifier = 'abcdefg';

		$mockClassSchema = $this->getMock('F3\FLOW3\Reflection\ClassSchema', array(), array(), '', FALSE);
		$mockClassSchema->expects($this->any())->method('getProperties')->will($this->returnValue(array('foo' => 'options')));

		$mockPersistenceSession = $this->getMock('F3\FLOW3\Persistence\Session');
		$mockPersistenceSession->expects($this->atLeastOnce())
			->method('hasObject')
			->with($object)
			->will($this->returnValue(TRUE));

		$backend = $this->getAccessibleMock('F3\CouchDB\Persistence\Backend\CouchDbBackend', array('collectMetadata', 'collectProperties', 'validateObject', 'storeObjectDocument'));
		$backend->_set('classSchemata', array($className => $mockClassSchema));
		$backend->injectPersistenceSession($mockPersistenceSession);
		$objectData = array();
		$result = $backend->_call('storeObject', $object, $identifier, NULL, $objectData);

		$this->assertEquals(\F3\FLOW3\Persistence\Backend\AbstractBackend::OBJECTSTATE_RECONSTITUTED, $result);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function storeObjectRegistersObjectAndReturnsNewObjectStateIfSessionDoesntHaveObject() {
		$className = 'stdClass';
		$object = $this->getMock($className, array('FLOW3_AOP_Proxy_getProxyTargetClassName'));
		$object->expects($this->any())->method('FLOW3_AOP_Proxy_getProxyTargetClassName')->will($this->returnValue($className));
		$identifier = 'abcdefg';

		$mockClassSchema = $this->getMock('F3\FLOW3\Reflection\ClassSchema', array(), array(), '', FALSE);
		$mockClassSchema->expects($this->any())->method('getProperties')->will($this->returnValue(array('foo' => 'options')));

		$mockPersistenceSession = $this->getMock('F3\FLOW3\Persistence\Session');
		$mockPersistenceSession->expects($this->atLeastOnce())
			->method('hasObject')
			->with($object)
			->will($this->returnValue(FALSE));
		$mockPersistenceSession->expects($this->once())->method('registerObject')->with($object, $identifier);

		$backend = $this->getAccessibleMock('F3\CouchDB\Persistence\Backend\CouchDbBackend', array('collectMetadata', 'collectProperties', 'validateObject', 'storeObjectDocument'));
		$backend->_set('classSchemata', array($className => $mockClassSchema));
		$backend->injectPersistenceSession($mockPersistenceSession);
		$objectData = array();
		$result = $backend->_call('storeObject', $object, $identifier, NULL, $objectData);

		$this->assertEquals(\F3\FLOW3\Persistence\Backend\AbstractBackend::OBJECTSTATE_NEW, $result);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function storeObjectSetsObjectDataFromCollectedPropertiesAndMetadata() {
		$className = 'stdClass';
		$object = $this->getMock($className, array('FLOW3_AOP_Proxy_getProxyTargetClassName'));
		$object->expects($this->any())->method('FLOW3_AOP_Proxy_getProxyTargetClassName')->will($this->returnValue($className));
		$identifier = 'abcdefg';

		$mockClassSchema = $this->getMock('F3\FLOW3\Reflection\ClassSchema', array(), array(), '', FALSE);
		$classSchemaProperties = array('foo' => 'options');
		$mockClassSchema->expects($this->any())->method('getProperties')->will($this->returnValue($classSchemaProperties));
		$mockClassSchema->expects($this->any())->method('getClassName')->will($this->returnValue('SchemaClassName'));

		$mockPersistenceSession = $this->getMock('F3\FLOW3\Persistence\Session');
		$mockPersistenceSession->expects($this->any())
			->method('hasObject')
			->with($object)
			->will($this->returnValue(FALSE));

		$backend = $this->getAccessibleMock('F3\CouchDB\Persistence\Backend\CouchDbBackend', array('collectMetadata', 'collectProperties', 'validateObject', 'storeObjectDocument'));
		$backend->_set('classSchemata', array($className => $mockClassSchema));
		$backend->injectPersistenceSession($mockPersistenceSession);

		$collectedProperties = array('foo' => 'bar', 'bar' => 'baz');
		$backend->expects($this->any())->method('collectProperties')->with($identifier, $object, $classSchemaProperties, FALSE)->will($this->returnValue($collectedProperties));

		$collectedMetadata = array('metadata' => 'foo');
		$backend->expects($this->any())->method('collectMetadata')->with($object)->will($this->returnValue($collectedMetadata));

		$objectData = array();
		$parentIdentifier = 'xyz';
		$result = $backend->_callRef('storeObject', $object, $identifier, $parentIdentifier, $objectData);

		$expectedObjectData = array(
			'identifier' => 'abcdefg',
			'classname' => 'SchemaClassName',
			'properties' => $collectedProperties,
			'metadata' => $collectedMetadata,
			'parentIdentifier' => 'xyz'
		);

		$this->assertEquals($expectedObjectData, $objectData);
	}


	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function collectMetadataReturnsProxyMetadata() {
		$object = $this->getMock('stdClass', array('FLOW3_AOP_Proxy_hasProperty', 'FLOW3_AOP_Proxy_getProperty'));
		$metadata = array('metadata' => 'foo');
		$object->expects($this->atLeastOnce())->method('FLOW3_AOP_Proxy_hasProperty')->with('FLOW3_Persistence_Metadata')->will($this->returnValue(TRUE));
		$object->expects($this->atLeastOnce())->method('FLOW3_AOP_Proxy_getProperty')->with('FLOW3_Persistence_Metadata')->will($this->returnValue($metadata));

		$backend = $this->getAccessibleMock('F3\CouchDB\Persistence\Backend\CouchDbBackend', array('dummy'));
		$result = $backend->_call('collectMetadata', $object);

		$this->assertEquals($metadata, $result);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function storeObjectDocumentCreatesDocumentFromObjectData() {
		$backend = $this->getAccessibleMock('F3\CouchDB\Persistence\Backend\CouchDbBackend', array('dummy'));
		$mockClient = $this->getMock('F3\CouchDB\Client', array(), array(), '', FALSE);

		$objectData = array(
			'identifier' => 'abcdefg',
			'metadata' => array(
				'CouchDB_Revision' => '1-revision-id'
			),
			'properties' => array(
				'foo' => 'bar'
			)
		);
		$documentData = array(
			'_id' => 'abcdefg',
			'_rev' => '1-revision-id',
			'properties' => array(
				'foo' => 'bar'
			)
		);

		$mockClient->expects($this->once())->method('createDocument')->with($documentData);

		$backend->_set('client', $mockClient);

		$result = $backend->_call('storeObjectDocument', $objectData);
	}


	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function storeObjectDocumentCreatesNewDocumentFromObjectDataIfNoRevisionInMetadata() {
		$backend = $this->getAccessibleMock('F3\CouchDB\Persistence\Backend\CouchDbBackend', array('dummy'));
		$mockClient = $this->getMock('F3\CouchDB\Client', array(), array(), '', FALSE);

		$objectData = array(
			'identifier' => 'abcdefg',
			'metadata' => array(
			),
			'properties' => array(
				'foo' => 'bar'
			)
		);
		$documentData = array(
			'_id' => 'abcdefg',
			'properties' => array(
				'foo' => 'bar'
			)
		);

		$mockClient->expects($this->once())->method('createDocument')->with($documentData);

		$backend->_set('client', $mockClient);

		$result = $backend->_call('storeObjectDocument', $objectData);
	}
}
?>