<?php
namespace TYPO3\CouchDB\Tests\Unit\Persistence\Backend;

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
class CouchDbBackendTest extends \TYPO3\FLOW3\Tests\UnitTestCase {

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function initializeConnectsToCouchDb() {
		$mockReflectionService = $this->getMock('TYPO3\FLOW3\Reflection\ReflectionService');
		$mockReflectionService->expects($this->any())->method('getClassSchemata');

		$backend = $this->getMock('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', array('connect'));
		$backend->expects($this->once())->method('connect');
		$backend->injectReflectionService($mockReflectionService);

		$backend->initialize(array('database' => 'foo', 'dataSourceName' => 'http://1.2.3.4:5678'));
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function connectCreatesClientWithDataSourceNameAndSetsDatabasename() {
		$mockClient = $this->getMock('TYPO3\CouchDB\Client', array(), array(), '', FALSE);
		$mockClient->expects($this->atLeastOnce())->method('setDatabaseName')->with('foo');

		$mockObjectManager = $this->getMock('TYPO3\FLOW3\Object\ObjectManagerInterface');
		$mockObjectManager->expects($this->atLeastOnce())->method('create')->with('TYPO3\CouchDB\Client', 'http://1.2.3.4:5678')->will($this->returnValue($mockClient));

		$backend = $this->getAccessibleMock('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', array('dummy'));
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
		$object = $this->getMock($className);
		$identifier = 'abcdefg';

		$mockClassSchema = $this->getMock('TYPO3\FLOW3\Reflection\ClassSchema', array(), array(), '', FALSE);
		$mockClassSchema->expects($this->any())->method('getProperties')->will($this->returnValue(array('foo' => 'options')));

		$mockPersistenceSession = $this->getMock('TYPO3\FLOW3\Persistence\Generic\Session');
		$mockPersistenceSession->expects($this->atLeastOnce())
			->method('hasObject')
			->with($object)
			->will($this->returnValue(TRUE));

		$backend = $this->getAccessibleMock('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', array('collectMetadata', 'collectProperties', 'validateObject', 'storeObjectDocument'));
		$backend->_set('classSchemata', array(get_class($object) => $mockClassSchema));
		$backend->injectPersistenceSession($mockPersistenceSession);
		$objectData = array();
		$result = $backend->_call('storeObject', $object, $identifier, NULL, $objectData);

		$this->assertEquals(\TYPO3\FLOW3\Persistence\Generic\Backend\AbstractBackend::OBJECTSTATE_RECONSTITUTED, $result);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function storeObjectRegistersObjectAndReturnsNewObjectStateIfSessionDoesntHaveObject() {
		$className = 'stdClass';
		$object = $this->getMock($className);
		$identifier = 'abcdefg';

		$mockClassSchema = $this->getMock('TYPO3\FLOW3\Reflection\ClassSchema', array(), array(), '', FALSE);
		$mockClassSchema->expects($this->any())->method('getProperties')->will($this->returnValue(array('foo' => 'options')));

		$mockPersistenceSession = $this->getMock('TYPO3\FLOW3\Persistence\Generic\Session');
		$mockPersistenceSession->expects($this->atLeastOnce())
			->method('hasObject')
			->will($this->returnValue(FALSE));
		$mockPersistenceSession->expects($this->once())->method('registerObject')->with($this->anything(), $identifier);

		$backend = $this->getAccessibleMock('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', array('collectMetadata', 'collectProperties', 'validateObject', 'storeObjectDocument'));
		$backend->_set('classSchemata', array(get_class($object) => $mockClassSchema));
		$backend->injectPersistenceSession($mockPersistenceSession);
		$objectData = array();
		$result = $backend->_call('storeObject', $object, $identifier, NULL, $objectData);

		$this->assertEquals(\TYPO3\FLOW3\Persistence\Generic\Backend\AbstractBackend::OBJECTSTATE_NEW, $result);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function storeObjectSetsObjectDataFromCollectedPropertiesAndMetadata() {
		$className = 'stdClass';
		$object = $this->getMock($className);
		$identifier = 'abcdefg';

		$mockClassSchema = $this->getMock('TYPO3\FLOW3\Reflection\ClassSchema', array(), array(), '', FALSE);
		$classSchemaProperties = array('foo' => 'options');
		$mockClassSchema->expects($this->any())->method('getProperties')->will($this->returnValue($classSchemaProperties));
		$mockClassSchema->expects($this->any())->method('getClassName')->will($this->returnValue('SchemaClassName'));

		$mockPersistenceSession = $this->getMock('TYPO3\FLOW3\Persistence\Generic\Session');
		$mockPersistenceSession->expects($this->any())
			->method('hasObject')
			->will($this->returnValue(FALSE));

		$backend = $this->getAccessibleMock('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', array('collectMetadata', 'collectProperties', 'validateObject', 'storeObjectDocument'));
		$backend->_set('classSchemata', array(get_class($object) => $mockClassSchema));
		$backend->injectPersistenceSession($mockPersistenceSession);

		$collectedProperties = array('foo' => 'bar', 'bar' => 'baz');
		$backend->expects($this->any())->method('collectProperties')->with($identifier, $this->anything(), $classSchemaProperties, FALSE)->will($this->returnValue($collectedProperties));

		$collectedMetadata = array('metadata' => 'foo', 'CouchDB_Revision' => '');
		$backend->expects($this->any())->method('collectMetadata')->will($this->returnValue($collectedMetadata));

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
		$object->FLOW3_Persistence_Metadata = $metadata;

		$backend = $this->getAccessibleMock('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', array('dummy'));
		$result = $backend->_call('collectMetadata', $object);

		$this->assertEquals($metadata, $result);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function storeObjectDocumentCreatesDocumentFromObjectData() {
		$backend = $this->getAccessibleMock('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', array('dummy'));
		$mockClient = $this->getMock('TYPO3\CouchDB\Client', array(), array(), '', FALSE);
		$mockResponse = $this->getMock('TYPO3\CouchDB\Client\StatusResponse', array(), array(), '', FALSE);

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

		$mockClient->expects($this->once())->method('createDocument')->with($documentData)->will($this->returnValue($mockResponse));

		$backend->_set('client', $mockClient);

		$result = $backend->_call('storeObjectDocument', $objectData);
	}


	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function storeObjectDocumentCreatesNewDocumentFromObjectDataIfNoRevisionInMetadata() {
		$backend = $this->getAccessibleMock('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', array('dummy'));
		$mockClient = $this->getMock('TYPO3\CouchDB\Client', array(), array(), '', FALSE);
		$mockResponse = $this->getMock('TYPO3\CouchDB\Client\StatusResponse', array(), array(), '', FALSE);

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

		$mockClient->expects($this->once())->method('createDocument')->with($documentData)->will($this->returnValue($mockResponse));

		$backend->_set('client', $mockClient);

		$result = $backend->_call('storeObjectDocument', $objectData);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function collectPropertiesChecksPropertyValues() {
		$object = $this->getMock('TYPO3\FLOW3\AOP\ProxyInterface');
		$object->foo = NULL;

		$mockPersistenceSession = $this->getMock('TYPO3\FLOW3\Persistence\Generic\Session');
		$mockPersistenceSession->expects($this->any())
			->method('isDirty')
			->with($object, 'foo')
			->will($this->returnValue(FALSE));

		$backend = $this->getAccessibleMock('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', array('checkPropertyValue', 'flattenValue'));
		$backend->expects($this->once())->method('checkPropertyValue')->with($object, 'foo', array('type' => 'string', 'metadata' => 'bar'));
		$backend->injectPersistenceSession($mockPersistenceSession);

		$identifier = 'abcdefg';
		$properties = array(
			'foo' => array(
				'type' => 'string',
				'metadata' => 'bar'
			)
		);
		$dirty = FALSE;
		$backend->_callRef('collectProperties', $identifier, $object, $properties, $dirty);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function collectPropertiesSetsDirtyReferenceIfPropertyIsDirty() {
		$object = $this->getMock('TYPO3\FLOW3\AOP\ProxyInterface');
		$object->foo = NULL;

		$mockPersistenceSession = $this->getMock('TYPO3\FLOW3\Persistence\Generic\Session');
		$mockPersistenceSession->expects($this->any())
			->method('isDirty')
			->with($object, 'foo')
			->will($this->returnValue(TRUE));

		$backend = $this->getAccessibleMock('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', array('checkPropertyValue', 'flattenValue'));
		$backend->injectPersistenceSession($mockPersistenceSession);

		$identifier = 'abcdefg';
		$properties = array(
			'foo' => array(
				'type' => 'string',
				'metadata' => 'bar'
			)
		);
		$dirty = FALSE;
		$backend->_callRef('collectProperties', $identifier, $object, $properties, $dirty);

		$this->assertTrue($dirty);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function collectPropertiesDoesntSetDirtyReferenceIfNoPropertyIsDirty() {
		$object = $this->getMock('TYPO3\FLOW3\AOP\ProxyInterface');
		$object->foo = NULL;

		$mockPersistenceSession = $this->getMock('TYPO3\FLOW3\Persistence\Generic\Session');
		$mockPersistenceSession->expects($this->any())
			->method('isDirty')
			->with($object, 'foo')
			->will($this->returnValue(FALSE));

		$backend = $this->getAccessibleMock('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', array('checkPropertyValue', 'flattenValue'));
		$backend->injectPersistenceSession($mockPersistenceSession);

		$identifier = 'abcdefg';
		$properties = array(
			'foo' => array(
				'type' => 'string',
				'metadata' => 'bar'
			)
		);
		$dirty = FALSE;
		$backend->_callRef('collectProperties', $identifier, $object, $properties, $dirty);

		$this->assertFalse($dirty);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function collectPropertiesSkipsInternalIdentifierProperty() {
		$object = $this->getMock('TYPO3\FLOW3\AOP\ProxyInterface');
		$object->foo = 'baz';
		$object->FLOW3_Persistence_Identifier = '9febd71d-da12-4c80-ae6e-a4edc24f9a57';

		$mockPersistenceSession = $this->getMock('TYPO3\FLOW3\Persistence\Generic\Session');

		$backend = $this->getAccessibleMock('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', array('checkPropertyValue'));
		$backend->injectPersistenceSession($mockPersistenceSession);

		$identifier = 'abcdefg';
		$properties = array(
			'foo' => array(
				'type' => 'string',
				'metadata' => 'bar',
			),
			'FLOW3_Persistence_Identifier' => array(
				'multivalue' => false,
				'type' => 'string'
			)
		);
		$dirty = FALSE;
		$properties = $backend->_callRef('collectProperties', $identifier, $object, $properties, $dirty);
		$this->assertFalse(isset($properties['FLOW3_Persistence_Identifier']), 'Internal identifier property should be skipped');
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function removeEntitiesByParentQueriesEntitiesByParentIdentifier() {
		$backend = $this->getAccessibleMock('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', array('queryView', 'getEntityByParentIdentifierView'));

		$result = new \stdClass();
		$result->rows = array();

		$mockEntityByParentIdentifierView = $this->getMock('TYPO3\CouchDB\EntityByParentIdentifierView');
		$backend->expects($this->atLeastOnce())->method('getEntityByParentIdentifierView')->will($this->returnValue($mockEntityByParentIdentifierView));
		$backend->expects($this->once())->method('queryView')->with($mockEntityByParentIdentifierView, array('parentIdentifier' => 'abcdefg'))->will($this->returnValue($result));

		$backend->_call('removeEntitiesByParent', 'abcdefg');
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function removeEntitiesByParentRemovesNonAggregateRootEntities() {
		$mockObject = $this->getMock('TYPO3\FLOW3\AOP\ProxyInterface');

		$mockPersistenceSession = $this->getMock('TYPO3\FLOW3\Persistence\Generic\Session');
		$mockPersistenceSession->expects($this->once())->method('getObjectByIdentifier')->with('xyz')->will($this->returnValue($mockObject));

		$mockClassSchema = $this->getMock('TYPO3\FLOW3\Reflection\ClassSchema', array(), array(), '', FALSE);
		$mockClassSchema->expects($this->any())->method('getModelType')->will($this->returnValue(\TYPO3\FLOW3\Reflection\ClassSchema::MODELTYPE_ENTITY));
		$mockClassSchema->expects($this->any())->method('isAggregateRoot')->will($this->returnValue(FALSE));

		$backend = $this->getAccessibleMock('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', array('queryView', 'getEntityByParentIdentifierView', 'removeEntity', 'reallyRemoveEntity'));
		$backend->injectPersistenceSession($mockPersistenceSession);
		$backend->_set('classSchemata', array(get_class($mockObject) => $mockClassSchema));

		$row = new \stdClass();
		$row->id = 'xyz';
		$result = new \stdClass();
		$result->rows = array($row);

		$mockEntityByParentIdentifierView = $this->getMock('TYPO3\CouchDB\EntityByParentIdentifierView');
		$backend->expects($this->any())->method('getEntityByParentIdentifierView')->will($this->returnValue($mockEntityByParentIdentifierView));
		$backend->expects($this->any())->method('queryView')->will($this->returnValue($result));

		$backend->expects($this->once())->method('reallyRemoveEntity')->with($mockObject);

		$backend->_call('removeEntitiesByParent', 'abcdefg');
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function removeEntitiesByParentDoesNotRemoveAggregateRootEntities() {
		$mockObject = $this->getMock('TYPO3\FLOW3\AOP\ProxyInterface');

		$mockPersistenceSession = $this->getMock('TYPO3\FLOW3\Persistence\Generic\Session');
		$mockPersistenceSession->expects($this->once())->method('getObjectByIdentifier')->with('xyz')->will($this->returnValue($mockObject));

		$mockClassSchema = $this->getMock('TYPO3\FLOW3\Reflection\ClassSchema', array(), array(), '', FALSE);
		$mockClassSchema->expects($this->any())->method('getModelType')->will($this->returnValue(\TYPO3\FLOW3\Reflection\ClassSchema::MODELTYPE_ENTITY));
		$mockClassSchema->expects($this->any())->method('isAggregateRoot')->will($this->returnValue(TRUE));

		$backend = $this->getAccessibleMock('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', array('queryView', 'getEntityByParentIdentifierView', 'removeEntity'));
		$backend->injectPersistenceSession($mockPersistenceSession);
		$backend->_set('classSchemata', array(get_class($mockObject) => $mockClassSchema));

		$row = new \stdClass();
		$row->id = 'xyz';
		$result = new \stdClass();
		$result->rows = array($row);

		$mockEntityByParentIdentifierView = $this->getMock('TYPO3\CouchDB\EntityByParentIdentifierView');
		$backend->expects($this->any())->method('getEntityByParentIdentifierView')->will($this->returnValue($mockEntityByParentIdentifierView));
		$backend->expects($this->any())->method('queryView')->will($this->returnValue($result));

		$backend->expects($this->never())->method('removeEntity');

		$backend->_call('removeEntitiesByParent', 'abcdefg');
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function reallyRemoveEntityDeletesDocumentWithRevisionFromObject() {
		$mockObject = $this->getMock('TYPO3\FLOW3\AOP\ProxyInterface');
		$mockClient = $this->getMock('TYPO3\CouchDB\Client', array(), array(), '', FALSE);
		$mockFlow3Design = $this->getMock('TYPO3\CouchDB\Persistence\Backend\Flow3Design', array(), array(), '', FALSE);
		$mockFlow3Design->expects($this->any())->method('entityReferences')->will($this->returnValue(array()));

		$mockPersistenceSession = $this->getMock('TYPO3\FLOW3\Persistence\Generic\Session');
		$mockPersistenceSession->expects($this->once())->method('getIdentifierByObject')->with($mockObject)->will($this->returnValue('xyz'));

		$backend = $this->getAccessibleMock('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', array('removeEntitiesByParent', 'getRevisionByObject', 'emitRemovedObject'));
		$backend->injectPersistenceSession($mockPersistenceSession);
		$backend->_set('client', $mockClient);
		$backend->_set('flow3Design', $mockFlow3Design);

		$backend->expects($this->once())->method('getRevisionByObject')->with($mockObject)->will($this->returnValue('5-revisionid'));
		$mockClient->expects($this->once())->method('deleteDocument')->with('xyz', '5-revisionid');

		$backend->_call('reallyRemoveEntity', $mockObject);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function reallyRemoveEntityCallsRemoveEntitiesByParentAndEmitRemovedObject() {
		$mockObject = $this->getMock('TYPO3\FLOW3\AOP\ProxyInterface');
		$mockFlow3Design = $this->getMock('TYPO3\CouchDB\Persistence\Backend\Flow3Design', array(), array(), '', FALSE);
		$mockFlow3Design->expects($this->any())->method('entityReferences')->will($this->returnValue(array()));

		$mockPersistenceSession = $this->getMock('TYPO3\FLOW3\Persistence\Generic\Session');
		$mockPersistenceSession->expects($this->any())->method('getIdentifierByObject')->will($this->returnValue('xyz'));

		$backend = $this->getAccessibleMock('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', array('doOperation', 'removeEntitiesByParent', 'getRevisionByObject', 'emitRemovedObject'));
		$backend->injectPersistenceSession($mockPersistenceSession);
		$backend->_set('flow3Design', $mockFlow3Design);

		$backend->expects($this->any())->method('getRevisionByObject')->will($this->returnValue('7-abc'));
		$backend->expects($this->once())->method('removeEntitiesByParent')->with('xyz');
		$backend->expects($this->once())->method('emitRemovedObject')->with($mockObject);

		$backend->_call('reallyRemoveEntity', $mockObject);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function removeValueObjectDoesNoOperation() {
		$mockObject = $this->getMock('TYPO3\FLOW3\AOP\ProxyInterface');

		$backend = $this->getAccessibleMock('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', array('doOperation'));
		$backend->expects($this->never())->method('doOperation');

		$backend->_call('removeValueObject', $mockObject);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function processObjectPersistsNestedEntities() {
		$mockObject = $this->getMock('TYPO3\FLOW3\AOP\ProxyInterface');

		$mockClassSchema = $this->getMock('TYPO3\FLOW3\Reflection\ClassSchema', array(), array(), '', FALSE);
		$mockClassSchema->expects($this->any())->method('getModelType')->will($this->returnValue(\TYPO3\FLOW3\Reflection\ClassSchema::MODELTYPE_ENTITY));

		$backend = $this->getAccessibleMock('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', array('getIdentifierByObject', 'persistObject'));
		$backend->expects($this->once())->method('persistObject')->with($mockObject, 'xyz')->will($this->returnValue('abc'));
		$backend->_set('classSchemata', array(get_class($mockObject) => $mockClassSchema));

		$result = $backend->_call('processObject', $mockObject, 'xyz');
		$this->assertEquals(array(
			'identifier' => 'abc'
		), $result);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function processObjectPersistsEntities() {
		$mockObject = $this->getMock('TYPO3\FLOW3\AOP\ProxyInterface');

		$mockClassSchema = $this->getMock('TYPO3\FLOW3\Reflection\ClassSchema', array(), array(), '', FALSE);
		$mockClassSchema->expects($this->any())->method('getModelType')->will($this->returnValue(\TYPO3\FLOW3\Reflection\ClassSchema::MODELTYPE_ENTITY));

		$backend = $this->getAccessibleMock('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', array('getIdentifierByObject', 'persistObject'));
		$backend->expects($this->once())->method('persistObject')->with($mockObject, 'xyz')->will($this->returnValue('abc'));
		$backend->_set('classSchemata', array(get_class($mockObject) => $mockClassSchema));

		$result = $backend->_call('processObject', $mockObject, 'xyz');
		$this->assertEquals(array(
			'identifier' => 'abc'
		), $result);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function processObjectInlinesValueObjects() {
		$mockObject = $this->getMock('TYPO3\FLOW3\AOP\ProxyInterface');

		$properties = array(
			'foo' => 'options'
		);
		$mockClassSchema = $this->getMock('TYPO3\FLOW3\Reflection\ClassSchema', array(), array(), '', FALSE);
		$mockClassSchema->expects($this->any())->method('getModelType')->will($this->returnValue(\TYPO3\FLOW3\Reflection\ClassSchema::MODELTYPE_VALUEOBJECT));
		$mockClassSchema->expects($this->any())->method('getProperties')->will($this->returnValue($properties));
		$mockClassSchema->expects($this->any())->method('getClassName')->will($this->returnValue($properties));

		$mockPersistenceSession = $this->getMock('TYPO3\FLOW3\Persistence\Generic\Session');
		$mockPersistenceSession->expects($this->any())->method('getIdentifierByObject')->with($mockObject)->will($this->returnValue('abc'));

		$backend = $this->getAccessibleMock('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', array('collectProperties'));
		$backend->injectPersistenceSession($mockPersistenceSession);
		$backend->expects($this->once())->method('collectProperties')->with('abc', $mockObject, $properties, FALSE)->will($this->returnValue(array('foo' => 'bar')));
		$backend->_set('classSchemata', array(get_class($mockObject) => $mockClassSchema));

		$result = $backend->_call('processObject', $mockObject, 'xyz');
		$this->assertEquals(array(
			'identifier' => 'abc',
			'classname' => get_class($mockObject),
			'properties' => array(
				'foo' => 'bar'
			)
		), $result);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function getRevisionByObjectCollectsMetadataAndGetsCouchDbRevision() {
		$mockObject = $this->getMock('TYPO3\FLOW3\AOP\ProxyInterface');

		$backend = $this->getAccessibleMock('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', array('collectMetadata'));
		$backend->expects($this->once())->method('collectMetadata')->with($mockObject)->will($this->returnValue(array('CouchDB_Revision' => '7-revisionid')));

		$result = $backend->_call('getRevisionByObject', $mockObject);
		$this->assertEquals('7-revisionid', $result);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function getRevisionByObjectGetsRevisionFromCouchDbIfNoRevisionSet() {
		$mockObject = $this->getMock('TYPO3\FLOW3\AOP\ProxyInterface');
		$mockClient = $this->getMock('TYPO3\CouchDB\Client', array(), array(), '', FALSE);
		$mockPersistenceSession = $this->getMock('TYPO3\FLOW3\Persistence\Generic\Session');
		$mockResponse = $this->getMock('TYPO3\CouchDB\Client\RawResponse', array(), array(), '', FALSE);

		$backend = $this->getAccessibleMock('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', array('collectMetadata'));
		$backend->_set('client', $mockClient);
		$backend->_set('persistenceSession', $mockPersistenceSession);

		$mockPersistenceSession->expects($this->any())->method('getIdentifierByObject')->with($mockObject)->will($this->returnValue('abc-def'));
		$mockResponse->expects($this->any())->method('getRevision')->will($this->returnValue('42-xyz'));

		$backend->expects($this->once())->method('collectMetadata')->with($mockObject)->will($this->returnValue(NULL));
		$mockClient->expects($this->once())->method('getDocumentInformation')->with('abc-def')->will($this->returnValue($mockResponse));

		$result = $backend->_call('getRevisionByObject', $mockObject);
		$this->assertEquals('42-xyz', $result);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function storeViewDoesNothingIfViewExistsAlready() {
		$mockView = $this->getMock('TYPO3\CouchDB\ViewInterface');
		$mockView->expects($this->any())->method('getDesignName')->will($this->returnValue('designName'));
		$mockView->expects($this->any())->method('getViewName')->will($this->returnValue('viewName'));

		$designDocument = new \stdClass();
		$designDocument->views = new \stdClass();
		$designDocument->views->viewName = new \stdClass();

		$mockClient = $this->getMock('TYPO3\CouchDB\Client', array(), array(), '', FALSE);
		$mockClient->expects($this->once())->method('getDocument')->with('_design/designName')->will($this->returnValue($designDocument));
		$mockClient->expects($this->never())->method('createDocument');
		$mockClient->expects($this->never())->method('updateDocument');

		$backend = $this->getAccessibleMock('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', array('collectMetadata'));
		$backend->_set('client', $mockClient);

		$backend->_call('storeView', $mockView);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function storeViewUpdatesDesignDocumentForNewView() {
		$mockView = $this->getMock('TYPO3\CouchDB\ViewInterface');
		$mockView->expects($this->any())->method('getDesignName')->will($this->returnValue('designName'));
		$mockView->expects($this->any())->method('getViewName')->will($this->returnValue('viewName'));
		$mockView->expects($this->any())->method('getMapFunctionSource')->will($this->returnValue('function(doc) { doSomething(); }'));


		$designDocument = new \stdClass();
		$designDocument->_id = 'abc';
		$designDocument->_rev = '2-revisionid';
		$designDocument->views = new \stdClass();

		$updateDocument = new \stdClass();
		$updateDocument->_id = 'abc';
		$updateDocument->_rev = '2-revisionid';
		$updateDocument->views = new \stdClass();
		$updateDocument->views->viewName = new \stdClass();
		$updateDocument->views->viewName->map = 'function(doc) { doSomething(); }';

		$mockClient = $this->getMock('TYPO3\CouchDB\Client', array(), array(), '', FALSE);
		$mockClient->expects($this->once())->method('getDocument')->with('_design/designName')->will($this->returnValue($designDocument));
		$mockClient->expects($this->once())->method('updateDocument')->with($updateDocument);

		$backend = $this->getAccessibleMock('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', array('collectMetadata'));
		$backend->_set('client', $mockClient);

		$backend->_call('storeView', $mockView);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function storeViewCreatesDesignDocumentIfNotExisting() {
		$mockView = $this->getMock('TYPO3\CouchDB\ViewInterface');
		$mockView->expects($this->any())->method('getDesignName')->will($this->returnValue('designName'));
		$mockView->expects($this->any())->method('getViewName')->will($this->returnValue('viewName'));
		$mockView->expects($this->any())->method('getMapFunctionSource')->will($this->returnValue('function(doc) { doSomething(); }'));
		$mockView->expects($this->any())->method('getReduceFunctionSource')->will($this->returnValue('_count'));

		$mockException = $this->getMock('TYPO3\CouchDB\Client\NotFoundException', array(), array(), '', FALSE);

		$createDocument = new \stdClass();
		$createDocument->_id = '_design/designName';
		$createDocument->views = new \stdClass();
		$createDocument->views->viewName = new \stdClass();
		$createDocument->views->viewName->map = 'function(doc) { doSomething(); }';
		$createDocument->views->viewName->reduce = '_count';

		$mockClient = $this->getMock('TYPO3\CouchDB\Client', array(), array(), '', FALSE);
		$mockClient->expects($this->once())->method('getDocument')->with('_design/designName')->will($this->throwException($mockException));
		$mockClient->expects($this->once())->method('createDocument')->with($createDocument);

		$backend = $this->getAccessibleMock('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', array('collectMetadata'));
		$backend->_set('client', $mockClient);

		$backend->_call('storeView', $mockView);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function getObjectCountByQueryCreatesAndExecutesQueryViewForCount() {
		$mockQuery = $this->getMock('TYPO3\FLOW3\Persistence\QueryInterface');
		$mockQueryView =$this->getMock('TYPO3\CouchDB\QueryView', array(), array(), '', FALSE);

		$mockObjectManager = $this->getMock('TYPO3\FLOW3\Object\ObjectManagerInterface');
		$mockObjectManager->expects($this->once())->method('create')->with('TYPO3\CouchDB\QueryView', $mockQuery)->will($this->returnValue($mockQueryView));

		$backend = $this->getAccessibleMock('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', array('queryView'));
		$backend->injectObjectManager($mockObjectManager);

		$result = new \stdClass();
		$result->rows = array();

		$backend->expects($this->once())->method('queryView')->with($mockQueryView, array('query' => $mockQuery, 'count' => TRUE))->will($this->returnValue($result));

		$count = $backend->_call('getObjectCountByQuery', $mockQuery);
		$this->assertEquals(0, $count);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function getObjectCountByQueryReturnsValueOfCount() {
		$mockQuery = $this->getMock('TYPO3\FLOW3\Persistence\QueryInterface');
		$mockQueryView =$this->getMock('TYPO3\CouchDB\QueryView', array(), array(), '', FALSE);

		$mockObjectManager = $this->getMock('TYPO3\FLOW3\Object\ObjectManagerInterface');
		$mockObjectManager->expects($this->any())->method('create')->will($this->returnValue($mockQueryView));

		$backend = $this->getAccessibleMock('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', array('queryView'));
		$backend->injectObjectManager($mockObjectManager);

		$row = new \stdClass();
		$row->value = 42;
		$result = new \stdClass();
		$result->rows = array($row);

		$backend->expects($this->any())->method('queryView')->will($this->returnValue($result));

		$count = $backend->_call('getObjectCountByQuery', $mockQuery);
		$this->assertEquals(42, $count);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function getObjectDataByIdentifierGetsDocumentAndCallsResultToObjectData() {
		$document = new \stdClass();
		$document->_id = 'xyz';

		$mockClient = $this->getMock('TYPO3\CouchDB\Client', array(), array(), '', FALSE);
		$mockClient->expects($this->once())->method('getDocument')->with('xyz')->will($this->returnValue($document));

		$backend = $this->getAccessibleMock('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', array('documentsToObjectData'));
		$backend->_set('client', $mockClient);

		$backend->expects($this->once())->method('documentsToObjectData')->with(array($document))->will($this->returnValue(array(array('identifier' => 'xyz'))));

		$objectData = $backend->getObjectDataByIdentifier('xyz');
		$this->assertEquals(array('identifier' => 'xyz'), $objectData);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function getObjectDataByIdentifierReturnsFalseIfDocumentDoesntExist() {
		$document = new \stdClass();
		$document->_id = 'xyz';

		$mockClient = $this->getMock('TYPO3\CouchDB\Client', array(), array(), '', FALSE);
		$mockClient->expects($this->once())->method('getDocument')->with('xyz')->will($this->returnValue(NULL));

		$backend = $this->getAccessibleMock('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', array('documentsToObjectData'));
		$backend->_set('client', $mockClient);

		$objectData = $backend->getObjectDataByIdentifier('xyz');

		$this->assertFalse($objectData);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function getObjectDataByQueryQueriesViewAndProcessesRows() {
		$row = new \stdClass();
		$row->value = new \stdClass();
		$row->value->_id = 'xyz';

		$result = new \stdClass();
		$result->rows = array($row);

		$mockQuery = $this->getMock('TYPO3\FLOW3\Persistence\QueryInterface');
		$mockQueryView =$this->getMock('TYPO3\CouchDB\QueryView', array(), array(), '', FALSE);

		$mockObjectManager = $this->getMock('TYPO3\FLOW3\Object\ObjectManagerInterface');
		$mockObjectManager->expects($this->any())->method('create')->will($this->returnValue($mockQueryView));

		$backend = $this->getAccessibleMock('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', array('queryView', 'documentsToObjectData'));
		$backend->injectObjectManager($mockObjectManager);

		$backend->expects($this->once())->method('queryView')->with($mockQueryView)->will($this->returnValue($result));
		$backend->expects($this->once())->method('documentsToObjectData')->with(array($row->value))->will($this->returnValue(array(array('identifier' => 'xyz'))));

		$objectDataArray = $backend->getObjectDataByQuery($mockQuery);
		$this->assertEquals(array(array('identifier' => 'xyz')), $objectDataArray);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function queryViewQueriesViewWithViewMethods() {
		$mockView = $this->getMock('TYPO3\CouchDB\ViewInterface');
		$mockClient = $this->getMock('TYPO3\CouchDB\Client', array(), array(), '', FALSE);

		$result = new \stdClass();
		$result->rows = array();

		$backend = $this->getAccessibleMock('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', array('documentsToObjectData'));
		$backend->_set('client', $mockClient);

		$mockView->expects($this->once())->method('getDesignName')->will($this->returnValue('designName'));
		$mockView->expects($this->once())->method('getViewName')->will($this->returnValue('viewName'));
		$mockView->expects($this->once())->method('buildViewParameters')->with(array('argument' => 'value'))->will($this->returnValue(array('param' => 'foo')));
		$mockClient->expects($this->once())->method('queryView')->with('designName', 'viewName', array('param' => 'foo'))->will($this->returnValue($result));


		$viewResult = $backend->queryView($mockView, array('argument' => 'value'));
		$this->assertEquals($result, $viewResult);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function documentsToObjectDataConvertsObjectToArrayAndSetsIdentifierRevisionAndProperties() {
		$document = new \stdClass();
		$document->_id = 'abcdefg';
		$document->_rev = '3-revisionid';
		$document->properties = new \stdClass();
		$document->properties->foo = new \stdClass();
		$document->properties->foo->multivalue = FALSE;
		$document->properties->foo->value = 'Bar';
		$document->properties->foo->type = 'string';
		$document->classname = 'FooBar';

		$backend = $this->getAccessibleMock('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', array('dummy'));
		$mockClassSchema = $this->getMock('TYPO3\FLOW3\Reflection\ClassSchema', array(), array(), '', FALSE);
		$backend->_set('classSchemata', array('FooBar' => $mockClassSchema));

		$objectData = array(
			'identifier' => 'abcdefg',
			'classname' => 'FooBar',
			'metadata' => array(
				'CouchDB_Revision' => '3-revisionid'
			),
			'properties' => array(
				'foo' => array(
					'multivalue' => FALSE,
					'type' => 'string',
					'value' => 'Bar'
				)
			)
		);
		$this->assertEquals(array($objectData), $backend->_call('documentsToObjectData', array($document)));
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function documentsToObjectDataFetchesNestedSinglevalueEntities() {
		$document = new \stdClass();
		$document->_id = 'abcdefg';
		$document->_rev = '3-revisionid';
		$document->properties = new \stdClass();
		$document->properties->foo = new \stdClass();
		$document->properties->foo->multivalue = FALSE;
		$document->properties->foo->type = 'BarBaz';
		$document->properties->foo->value = new \stdClass();
		$document->properties->foo->value->identifier = 'xyz';
		$document->classname = 'FooBar';

		$nestedResult = new \stdClass();
		$nestedResult->_id = 'xyz';
		$nestedResult->_rev = '2-revisionid';
		$nestedResult->classname = 'BarBaz';
		$nestedResult->properties = new \stdClass();
		$nestedResult->properties->bar = new \stdClass();
		$nestedResult->properties->bar->multivalue = FALSE;
		$nestedResult->properties->bar->type = 'string';
		$nestedResult->properties->bar->value = 'Bar';
		$nestedDoc = new \stdClass();
		$nestedDoc->id = 'xyz';
		$nestedDoc->doc = $nestedResult;
		$nestedResults = new \stdClass();
		$nestedResults->rows = array($nestedDoc);

		$mockClient = $this->getMock('TYPO3\CouchDB\Client', array(), array(), '', FALSE);
		$backend = $this->getAccessibleMock('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', array('dummy'));
		$mockClassSchemaFooBar = $this->getMock('TYPO3\FLOW3\Reflection\ClassSchema', array(), array(), '', FALSE);
		$mockClassSchemaFooBar->expects($this->any())->method('hasProperty')->will($this->returnValue(TRUE));
		$mockClassSchemaBarBaz = $this->getMock('TYPO3\FLOW3\Reflection\ClassSchema', array(), array(), '', FALSE);
		$mockClassSchemaBarBaz->expects($this->any())->method('hasProperty')->will($this->returnValue(TRUE));
		$backend->_set('classSchemata', array('FooBar' => $mockClassSchemaFooBar, 'BarBaz' => $mockClassSchemaBarBaz));
		$backend->_set('client', $mockClient);

		$mockClient->expects($this->once())->method('getDocuments')->with(array('xyz'), array('include_docs' => TRUE))->will($this->returnValue($nestedResults));

		$objectData = array(
			'identifier' => 'abcdefg',
			'classname' => 'FooBar',
			'metadata' => array(
				'CouchDB_Revision' => '3-revisionid'
			),
			'properties' => array(
				'foo' => array(
					'multivalue' => FALSE,
					'type' => 'BarBaz',
					'value' => array(
						'identifier' => 'xyz',
						'metadata' => array(
							'CouchDB_Revision' => '2-revisionid'
						),
						'classname' => 'BarBaz',
						'properties' => array(
							'bar' => array(
								'multivalue' => FALSE,
								'type' => 'string',
								'value' => 'Bar'
							)
						)
					)
				)
			)
		);
		$this->assertEquals(array($objectData), $backend->_call('documentsToObjectData', array($document)));
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function documentsToObjectDataFetchesNestedMultivalueEntities() {
		$fooValue = new \stdClass();
		$fooValue->type = 'BarBaz';
		$fooValue->index = 0;
		$fooValue->value = new \stdClass();
		$fooValue->value->identifier = 'xyz';
		$document = new \stdClass();
		$document->_id = 'abcdefg';
		$document->_rev = '3-revisionid';
		$document->properties = new \stdClass();
		$document->properties->foo = new \stdClass();
		$document->properties->foo->multivalue = TRUE;
		$document->properties->foo->type = 'array';
		$document->properties->foo->value = array($fooValue);
		$document->classname = 'FooBar';

		$nestedResult = new \stdClass();
		$nestedResult->_id = 'xyz';
		$nestedResult->_rev = '2-revisionid';
		$nestedResult->classname = 'BarBaz';
		$nestedResult->properties = new \stdClass();
		$nestedResult->properties->bar = new \stdClass();
		$nestedResult->properties->bar->multivalue = FALSE;
		$nestedResult->properties->bar->type = 'string';
		$nestedResult->properties->bar->value = 'Bar';
		$nestedDoc = new \stdClass();
		$nestedDoc->id = 'xyz';
		$nestedDoc->doc = $nestedResult;
		$nestedResults = new \stdClass();
		$nestedResults->rows = array($nestedDoc);

		$mockClient = $this->getMock('TYPO3\CouchDB\Client', array(), array(), '', FALSE);
		$backend = $this->getAccessibleMock('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', array('dummy'));
		$mockClassSchemaFooBar = $this->getMock('TYPO3\FLOW3\Reflection\ClassSchema', array(), array(), '', FALSE);
		$mockClassSchemaFooBar->expects($this->any())->method('hasProperty')->will($this->returnValue(TRUE));
		$mockClassSchemaBarBaz = $this->getMock('TYPO3\FLOW3\Reflection\ClassSchema', array(), array(), '', FALSE);
		$mockClassSchemaBarBaz->expects($this->any())->method('hasProperty')->will($this->returnValue(TRUE));
		$backend->_set('classSchemata', array('FooBar' => $mockClassSchemaFooBar, 'BarBaz' => $mockClassSchemaBarBaz));
		$backend->_set('client', $mockClient);

		$mockClient->expects($this->once())->method('getDocuments')->with(array('xyz'), array('include_docs' => TRUE))->will($this->returnValue($nestedResults));

		$objectData = array(
			'identifier' => 'abcdefg',
			'classname' => 'FooBar',
			'metadata' => array(
				'CouchDB_Revision' => '3-revisionid'
			),
			'properties' => array(
				'foo' => array(
					'multivalue' => TRUE,
					'type' => 'array',
					'value' => array(
						array(
							'type' => 'BarBaz',
							'index' => 0,
							'value' => array(
								'identifier' => 'xyz',
								'classname' => 'BarBaz',
								'metadata' => array(
									'CouchDB_Revision' => '2-revisionid'
								),
								'properties' => array(
									'bar' => array(
										'multivalue' => FALSE,
										'type' => 'string',
										'value' => 'Bar'
									)
								)
							)
						)
					)
				)
			)
		);
		$this->assertEquals(array($objectData), $backend->_call('documentsToObjectData', array($document)));
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function documentsToObjectDataProcessesLazyProperties() {
		$fooValue = new \stdClass();
		$fooValue->type = 'BarBaz';
		$fooValue->index = 0;
		$fooValue->value = new \stdClass();
		$fooValue->value->identifier = 'xyz';
		$document = new \stdClass();
		$document->_id = 'abcdefg';
		$document->_rev = '3-revisionid';
		$document->properties = new \stdClass();
		$document->properties->foo = new \stdClass();
		$document->properties->foo->multivalue = TRUE;
		$document->properties->foo->type = 'array';
		$document->properties->foo->value = array($fooValue);
		$document->properties->bar = new \stdClass();
		$document->properties->bar->multivalue = FALSE;
		$document->properties->bar->type = 'BarBaz';
		$document->properties->bar->value = new \stdClass();
		$document->properties->bar->value->identifier = 'xyz';
		$document->classname = 'FooBar';

		$mockClient = $this->getMock('TYPO3\CouchDB\Client', array(), array(), '', FALSE);
		$backend = $this->getAccessibleMock('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', array('dummy'));
		$mockClassSchemaFooBar = $this->getMock('TYPO3\FLOW3\Reflection\ClassSchema', array(), array(), '', FALSE);
		$mockClassSchemaFooBar->expects($this->any())->method('hasProperty')->will($this->returnValue(TRUE));
		$mockClassSchemaBarBaz = $this->getMock('TYPO3\FLOW3\Reflection\ClassSchema', array(), array(), '', FALSE);
		$mockClassSchemaBarBaz->expects($this->any())->method('hasProperty')->will($this->returnValue(TRUE));
		$backend->_set('classSchemata', array('FooBar' => $mockClassSchemaFooBar, 'BarBaz' => $mockClassSchemaBarBaz));
		$backend->_set('client', $mockClient);

		$mockClassSchemaFooBar->expects($this->any())->method('getProperty')->will($this->returnValue(array('lazy' => TRUE)));
		$mockClient->expects($this->never())->method('getDocuments');

		$objectData = array(
			'identifier' => 'abcdefg',
			'classname' => 'FooBar',
			'metadata' => array(
				'CouchDB_Revision' => '3-revisionid'
			),
			'properties' => array(
				'foo' => array(
					'multivalue' => TRUE,
					'type' => 'array',
					'value' => array(
						array(
							'type' => 'BarBaz',
							'index' => 0,
							'value' => array(
								'identifier' => 'xyz',
								'classname' => 'BarBaz',
								'properties' => array()
							)
						)
					)
				),
				'bar' => array(
					'multivalue' => FALSE,
					'type' => 'BarBaz',
					'value' => array(
						'identifier' => 'xyz',
						'classname' => 'BarBaz',
						'properties' => array()
					)
				)
			)
		);
		$this->assertEquals(array($objectData), $backend->_call('documentsToObjectData', array($document)));
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function resultToObjectDataProcessesEntitiesInNestedValueObjects() {
		$this->markTestIncomplete('Not implemented');
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function removeIndexDoesRemoveDesignDocumentForIndex() {
		$mockIndex = $this->getMock('TYPO3\CouchDB\Domain\Index\LuceneIndex');
		$mockIndex->expects($this->any())->method('getIndexName')->will($this->returnValue('lucene_myIndexName'));

		$designDocument = new \stdClass();
		$designDocument->_id = '_design/lucene_myIndexName';
		$designDocument->_rev = '42-abc';

		$mockClient = $this->getMock('TYPO3\CouchDB\Client', array(), array(), '', FALSE);
		$mockClient->expects($this->once())->method('getDocument')->with('_design/lucene_myIndexName')->will($this->returnValue($designDocument));
		$mockClient->expects($this->once())->method('deleteDocument')->with('_design/lucene_myIndexName', '42-abc');

		$backend = $this->getAccessibleMock('TYPO3\CouchDB\Persistence\Backend\CouchDbBackend', array('dummy'));
		$backend->_set('client', $mockClient);

		$backend->removeIndex($mockIndex);
	}

}
?>