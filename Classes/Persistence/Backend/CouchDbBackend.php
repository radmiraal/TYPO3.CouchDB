<?php
declare(ENCODING = 'utf-8');
namespace F3\CouchDB\Persistence\Backend;

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
 * A CouchDB persistence backend
 *
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class CouchDbBackend extends \F3\FLOW3\Persistence\Backend\AbstractBackend {

	/**
	 * @var \F3\CouchDB\Client
	 */
	protected $client;

	/**
	 * @var \F3\FLOW3\Object\ObjectManagerInterface
	 */
	protected $objectManager;

	/**
	 * The URL of the CouchDB server. Valid URLs could be:
	 * - http://127.0.0.1:5984
	 * - http://user:pass@127.0.0.1:5984
	 *
	 * @var string
	 */
	protected $dataSourceName;

	/**
	 * The CouchDB database to use. If it doesn't exist, it will be created.
	 *
	 * @var string
	 */
	protected $databaseName;

	/**
	 * @var F3\CouchDB\EntityByParentIdentifierView
	 */
	protected $entityByParentIdentifierView;

	/**
	 * @param string $dataSourceName
	 * @return void
	 */
	public function setDataSourceName($dataSourceName) {
		$this->dataSourceName = $dataSourceName;
	}

	/**
	 * @param string $databaseName
	 * @return void
	 */
	public function setDatabase($databaseName) {
		$this->databaseName = $databaseName;
	}

	/**
	 * @param \F3\FLOW3\Object\ObjectManagerInterface $objectManager
	 * @return void
	 */
	public function injectObjectManager(\F3\FLOW3\Object\ObjectManagerInterface $objectManager) {
		$this->objectManager = $objectManager;
	}

	/**
	 * Initializes the backend and connects the CouchDB client,
	 * will be called by PersistenceManager
	 *
	 * @param array $options
	 * @return void
	 */
	public function initialize(array $options) {
		parent::initialize($options);
		$this->connect();
	}

	/**
	 * Connect to CouchDB and select the database
	 *
	 * @return void
	 */
	protected function connect() {
		$this->client = $this->objectManager->create('F3\CouchDB\Client', $this->dataSourceName);
		$this->client->setDatabaseName($this->databaseName);
	}

	/**
	 * Actually store an object, backend-specific
	 *
	 * @param object $object
	 * @param string $identifier
	 * @param string $parentIdentifier
	 * @param array $objectData
	 * @return integer one of self::OBJECTSTATE_*
	 */
	protected function storeObject($object, $identifier, $parentIdentifier, array &$objectData) {
		if ($this->persistenceSession->hasObject($object)) {
			$objectState = self::OBJECTSTATE_RECONSTITUTED;
		} else {
				// Just get the identifier and register the object, create document with properties later
			$this->persistenceSession->registerObject($object, $identifier);
			$objectState = self::OBJECTSTATE_NEW;
		}

		$classSchema = $this->classSchemata[$object->FLOW3_AOP_Proxy_getProxyTargetClassName()];
		$dirty = FALSE;
		$objectData = array(
			'identifier' => $identifier,
			'classname' => $classSchema->getClassName(),
			'properties' => $this->collectProperties($identifier, $object, $classSchema->getProperties(), $dirty),
			'metadata' => $this->collectMetadata($object),
			'parentIdentifier' => $parentIdentifier
		);

		if ($objectState === self::OBJECTSTATE_NEW || $dirty) {
			$this->validateObject($object);
			$this->storeObjectDocument($objectData);
		}

		return $objectState;
	}

	/**
	 * Get metadata from AOP Proxy if it was set before in the DataMapper.
	 *
	 * @param object $object The object to get the metadata for
	 * @return array The metadata as an array
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	protected function collectMetadata($object) {
		$metadata = NULL;
		if ($object->FLOW3_AOP_Proxy_hasProperty('FLOW3_Persistence_Metadata')) {
			$metadata = $object->FLOW3_AOP_Proxy_getProperty('FLOW3_Persistence_Metadata');
		}
		return $metadata;
	}

	/**
	 * Creates or updates a document for the given object data. An update is
	 * done by using the revision inside the metadata of the object.
	 *
	 * @param array $objectData The object data for the object to store
	 * @return string The identifier of the created record
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 * @todo Catch exceptions for conflicts when updating the document
	 * @todo (Later) Try to use an update handler inside CouchDB for partial updates
	 */
	protected function storeObjectDocument($objectData) {
		$objectData['_id'] = $objectData['identifier'];
		unset($objectData['identifier']);

		if (isset($objectData['metadata']) && isset($objectData['metadata']['CouchDB_Revision'])) {
			$objectData['_rev'] = $objectData['metadata']['CouchDB_Revision'];
		}
		unset($objectData['metadata']);

		$this->doOperation(function($client) use (&$objectData) {
			$client->createDocument($objectData);
		});

		return $objectData['_id'];
	}

	/**
	 * CouchDB does not do partial updates, thus this method always collects the
	 * full set of properties.
	 * Value objects are always inlined.
	 *
	 * @param string $identifier The object's identifier
	 * @param object $object The object to work on
	 * @param array $properties The properties to collect (as per class schema)
	 * @param boolean $dirty A dirty flag that is passed by reference and set to TRUE if a dirty property was found
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	protected function collectProperties($identifier, $object, array $properties, &$dirty) {
		$propertyData = array();
		foreach ($properties as $propertyName => $propertyMetaData) {
			$this->checkPropertyValue($object, $propertyName, $propertyMetaData);
			$propertyValue = $object->FLOW3_AOP_Proxy_getProperty($propertyName);

			if ($propertyMetaData['type'] === 'object') {
				$propertyType = $propertyValue->FLOW3_AOP_Proxy_getProxyTargetClassName();
			} else {
				$propertyType = $propertyMetaData['type'];
			}

			if ($this->persistenceSession->isDirty($object, $propertyName)) {
				$dirty = TRUE;
			}

			$this->flattenValue($identifier, $object, $propertyName, $propertyMetaData, $propertyData);
		}

		return $propertyData;
	}

	/**
	 * Remove all non-aggregate-root objects that have the given identifier set
	 * as their parentIdentifier inside the CouchDB document.
	 *
	 * @param string $identifier The identifier of the parent object
	 * @return void
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	protected function removeEntitiesByParent($identifier) {
		$result = $this->queryView($this->getEntityByParentIdentifierView(), array('parentIdentifier' => $identifier));
		if ($result !== NULL && isset($result->rows) && is_array($result->rows)) {
			foreach ($result->rows as $row) {
				$object = $this->persistenceSession->getObjectByIdentifier($row->id);
				if ($this->classSchemata[$object->FLOW3_AOP_Proxy_getProxyTargetClassName()]->getModelType() === \F3\FLOW3\Reflection\ClassSchema::MODELTYPE_ENTITY
						&& $this->classSchemata[$object->FLOW3_AOP_Proxy_getProxyTargetClassName()]->isAggregateRoot() === FALSE) {
					$this->removeEntity($object);
				}
			};
		}
	}

	/**
	 * Removes an entity
	 *
	 * @param object $object An object
	 * @return void
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	protected function removeEntity($object) {
		$identifier = $this->persistenceSession->getIdentifierByObject($object);
		$revision = $this->getRevisionByObject($object);

		$this->removeEntitiesByParent($identifier);

		$this->doOperation(function($client) use ($identifier, $revision) {
			return $client->deleteDocument($identifier, $revision);
		});

		$this->emitRemovedObject($object);
	}

	/**
	 * Remove a value object. Does nothing for CouchDB, since value objects
	 * are embedded in documents.
	 *
	 * @param object $object
	 * @return void
	 */
	protected function removeValueObject($object) {}

	/**
	 * Process object data for an object
	 *
	 * @param \F3\FLOW3\AOP\ProxyInterface $object
	 * @param string $parentIdentifier
	 * @return array The object data for the given object
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 */
	protected function processObject(\F3\FLOW3\AOP\ProxyInterface $object, $parentIdentifier) {
		$className = $object->FLOW3_AOP_Proxy_getProxyTargetClassName();
		$classSchema = $this->classSchemata[$className];
		if ($classSchema->getModelType() === \F3\FLOW3\Reflection\ClassSchema::MODELTYPE_VALUEOBJECT) {
			$valueIdentifier = $this->getIdentifierByObject($object);
			$noDirtyOnValueObject = FALSE;
			return array(
				'identifier' => $valueIdentifier,
				'classname' => $className,
				'properties' => $this->collectProperties($valueIdentifier, $object, $classSchema->getProperties(), $noDirtyOnValueObject)
			);
		} else {
			return array(
				'identifier' => $this->persistObject($object, $parentIdentifier)
			);
		}
	}

	/**
	 * Get the CouchDB revision of an object
	 *
	 * @param object $object An object
	 * @return string The current revision if it was set, NULL otherwise
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	protected function getRevisionByObject($object) {
		$metadata = $this->collectMetadata($object);
		if (is_array($metadata) && isset($metadata['CouchDB_Revision'])) {
			return $metadata['CouchDB_Revision'];
		}
		return NULL;
	}

	/**
	 * Store a view inside CouchDB if it is not yet defined. Creates the
	 * design document on the fly if it does not exist already.
	 *
	 * @param \F3\CouchDB\ViewInterface $view
	 * @return void
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function storeView(\F3\CouchDB\ViewInterface $view) {
		try {
			$design = $this->doOperation(function($client) use ($view) {
				return $client->getDocument('_design/' . $view->getDesignName());
			});

			if (isset($design->views->{$view->getViewName()})) {
				return;
			}
		} catch(\F3\CouchDB\Client\NotFoundException $notFoundException) {
			$design = new \stdClass();
			$design->_id = '_design/' . $view->getDesignName();
			$design->views = new \stdClass();
		}

		$design->views->{$view->getViewName()} = new \stdClass();
		$design->views->{$view->getViewName()}->map = $view->getMapFunctionSource();
		if ($view->getReduceFunctionSource() !== NULL) {
			$design->views->{$view->getViewName()}->reduce = $view->getReduceFunctionSource();
		}

		$this->doOperation(function($client) use ($design) {
			if (isset($design->_rev)) {
				$client->updateDocument($design, $design->_id);
			} else {
				$client->createDocument($design);
			}
		});
	}

	/**
	 * Returns the number of records matching the query.
	 *
	 * @param \F3\FLOW3\Persistence\QueryInterface $query
	 * @return integer
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function getObjectCountByQuery(\F3\FLOW3\Persistence\QueryInterface $query) {
		$view = $this->objectManager->create('F3\CouchDB\QueryView', $query);
		$result = $this->queryView($view, array('query' => $query, 'count' => TRUE));
		if ($result !== NULL && isset($result->rows) && is_array($result->rows)) {
			return (count($result->rows) === 1) ? $result->rows[0]->value : 0;
		} else {
			throw new \F3\CouchDB\InvalidResultException('Could not get count from result', 1287074016, NULL, $result);
		}
	}

	/**
	 * Returns the object data for the given identifier.
	 *
	 * @param string $identifier The UUID or Hash of the object
	 * @return array
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 * @todo Maybe introduce a ObjectNotFound exception?
	 */
	public function getObjectDataByIdentifier($identifier) {
		$doc = $this->doOperation(function($client) use ($identifier) {
			return $client->getDocument($identifier);
		});
		if ($doc === NULL) {
			throw new \F3\FLOW3\Persistence\Exception\UnknownObjectException('Unknown object with identifier ' . $identifier, 1286902479);
		}
		return $this->resultToObjectData($doc);
	}

	/**
	 * Returns the object data matching the $query.
	 *
	 * @param \F3\FLOW3\Persistence\QueryInterface $query
	 * @return array
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function getObjectDataByQuery(\F3\FLOW3\Persistence\QueryInterface $query) {
		$view = $this->objectManager->create('F3\CouchDB\QueryView', $query);
		return $this->getObjectDataByView($view, array('query' => $query));
	}

	/**
	 * Get view results and convert documents to object data. The view can
	 * either emit the full object data document as the value or use
	 * the query option "include_docs=true".
	 *
	 * @param \F3\CouchDB\ViewInterface $view The view to execute
	 * @param array $arguments An array with arguments to the view
	 * @return array Array of object data
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function getObjectDataByView(\F3\CouchDB\ViewInterface $view, $arguments) {
		$result = $this->queryView($view, $arguments);
		$data = array();
		if ($result !== NULL) {
			foreach ($result->rows as $row) {
				$data[] = $this->resultToObjectData($row->value !== NULL ? $row->value : $row->doc);
			}
		}
		return $data;
	}

	/**
	 * "Execute" a view with the given arguments, these are view specific. The
	 * view will be stored in CouchDB if it is not yet defined.
	 *
	 * @param ViewInterface $view
	 * @param array $arguments
	 * @return object The results of the view
	 */
	public function queryView(\F3\CouchDB\ViewInterface $view, $arguments) {
		$that = $this;
		return $this->doOperation(function($client) use ($view, &$arguments, $that) {
			try {
				return $client->queryView($view->getDesignName(), $view->getViewName(), $view->buildViewParameters($arguments));
			} catch(\F3\CouchDB\Client\NotFoundException $notFoundException) {
				$that->storeView($view);
				return $client->queryView($view->getDesignName(), $view->getViewName(), $view->buildViewParameters($arguments));
			}
		});
	}

	/**
	 * Process a CouchDB result and add metadata and process
	 * object values by loading objects.
	 *
	 * @param object $result The raw document from CouchDB
	 * @return array
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	protected function resultToObjectData($result, &$knownObjects = array()) {
		$objectData = \F3\FLOW3\Utility\Arrays::convertObjectToArray($result);
		$objectData['identifier'] = $objectData['_id'];
		$objectData['metadata'] = array(
			'CouchDB_Revision' => $objectData['_rev']
		);
		unset($objectData['_id']);
		unset($objectData['_rev']);

		$knownObjects[$objectData['identifier']] = TRUE;
		$identifiersToFetch = array();

		if (!isset($objectData['classname'])) {
			throw new \F3\CouchDB\InvalidResultException('Expected property "classname" in document', 1290442039, NULL, $result);
		}
		if (!isset($this->classSchemata[$objectData['classname']])) {
			throw new \F3\CouchDB\InvalidResultException('Class "' . $objectData['classname'] . '" was not registered', 1290442092, NULL, $result);
		}

		$this->processResultProperties($objectData['properties'], $identifiersToFetch, $knownObjects, $this->classSchemata[$objectData['classname']]);

		if (count($identifiersToFetch) > 0) {
			$documents = $this->doOperation(function(\F3\CouchDB\Client $client) use ($identifiersToFetch) {
				return $client->getDocuments(array_keys($identifiersToFetch), array('include_docs' => TRUE));
			});

			foreach ($documents->rows as $document) {
				$identifiersToFetch[$document->id] = $this->resultToObjectData($document->doc, $knownObjects);
			}
		}

		return $objectData;
	}

	/**
	 * Process an array of object data properties and add identifiers to fetch
	 * for recursive processing in nested objects
	 *
	 * @param array $properties
	 * @param array $identifiersToFetch
	 * @param array $knownObjects
	 * @param \F3\FLOW3\Reflection\ClassSchema $classSchema
	 * @return void
	 */
	protected function processResultProperties(&$properties, &$identifiersToFetch, &$knownObjects, $classSchema) {
		foreach ($properties as $propertyName => &$propertyData) {
			if (!$propertyData['multivalue']) {
				if (isset($propertyData['value']['identifier']) && !isset($propertyData['value']['classname'])) {
					if (!isset($knownObjects[$propertyData['value']['identifier']])) {
						$propertyMetadata = $classSchema->getProperty($propertyName);
						if ($propertyMetadata['lazy'] !== TRUE) {
							$identifiersToFetch[$propertyData['value']['identifier']] = NULL;
							$propertyData['value'] = &$identifiersToFetch[$propertyData['value']['identifier']];
						} else {
							$propertyData['value'] = array('identifier' => $propertyData['value']['identifier'], 'classname' => $propertyData['type'], 'properties' => array());
						}
					}
				} elseif (is_array($propertyData['value']) && isset($propertyData['value']['properties'])) {
					$this->processResultProperties($propertyData['value']['properties'], $identifiersToFetch, $knownObjects, $this->classSchemata[$propertyData['value']['classname']]);
				}
			} else {
				for ($index = 0; $index < count($propertyData['value']); $index++) {
					if (isset($propertyData['value'][$index]['value']['identifier']) && !isset($propertyData['value'][$index]['value']['classname'])) {
						if (!isset($knownObjects[$propertyData['value'][$index]['value']['identifier']])) {
							$propertyMetadata = $classSchema->getProperty($propertyName);
							if ($propertyMetadata['lazy'] !== TRUE) {
								$identifiersToFetch[$propertyData['value'][$index]['value']['identifier']] = NULL;
								$propertyData['value'][$index]['value'] = &$identifiersToFetch[$propertyData['value'][$index]['value']['identifier']];
							} else {
								$propertyData['value'][$index]['value'] = array('identifier' => $propertyData['value'][$index]['value']['identifier'], 'classname' => $propertyData['value'][$index]['type'], 'properties' => array());
							}
						}
					} elseif (is_array($propertyData['value']) && isset($propertyData['value'][$index]['value']['properties'])) {
						$this->processResultProperties($propertyData['value'][$index]['value']['properties'], $identifiersToFetch, $knownObjects, $this->classSchemata[$propertyData['value'][$index]['value']['classname']]);
					}
				}
			}
		}
	}

	/**
	 * Do a CouchDB operation and handle error conversion and creation of
	 * the database on the fly.
	 *
	 * @param \Closure $couchDbOperation
	 * @return mixed
	 */
	protected function doOperation(\Closure $couchDbOperation) {
		try {
			return $couchDbOperation($this->client);
		} catch(\F3\CouchDB\Client\ClientException $clientException) {
			$information = $clientException->getInformation();
			if ($information['error'] === 'not_found' && $information['reason'] === 'no_db_file') {
				if ($this->client->createDatabase($this->databaseName)) {
					return $this->doOperation($couchDbOperation);
				} else {
					throw new \F3\FLOW3\Persistence\Exception('Could not create database ' . $this->database, 1286901880);
				}
			} else {
				throw $clientException;
			}
		}
	}

	/**
	 * Delete the database with all documents, it will be recreated on
	 * next access.
	 *
	 * @return void
	 */
	public function resetStorage() {
		$databaseName = $this->databaseName;
		$this->doOperation(function($client) use ($databaseName) {
			if ($client->databaseExists($databaseName)) {
				$client->deleteDatabase($databaseName);
			}
			$client->createDatabase($databaseName);
			$client->setDatabaseName($databaseName);
		});
	}

	/**
	 * @return \F3\CouchDB\EntityByParentIdentifierView
	 */
	public function getEntityByParentIdentifierView() {
		if ($this->entityByParentIdentifierView === NULL) {
			$this->entityByParentIdentifierView = $this->objectManager->create('F3\CouchDB\EntityByParentIdentifierView');
		}
		return $this->entityByParentIdentifierView;
	}

}

?>