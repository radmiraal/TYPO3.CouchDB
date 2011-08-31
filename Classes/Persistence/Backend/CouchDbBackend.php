<?php
namespace TYPO3\CouchDB\Persistence\Backend;

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
 * @scope singleton
 */
class CouchDbBackend extends \TYPO3\FLOW3\Persistence\Generic\Backend\AbstractBackend {

	/**
	 * @var \TYPO3\CouchDB\Client
	 */
	protected $client;

	/**
	 * @var \TYPO3\FLOW3\Object\ObjectManagerInterface
	 */
	protected $objectManager;

	/**
	 * The URL of the CouchDB server. Valid URLs could be:
	 * - http://127.0.0.1:5984
	 * - http://user:pass@127.0.0.1:5984
	 * - http://127.0.0.1:5984/my_database
	 *
	 * @var string
	 */
	protected $dataSourceName;

	/**
	 * The CouchDB database to use. If it doesn't exist, it will be created.
	 *
	 * @var string
	 */
	protected $databaseName = NULL;

	/**
	 * @var TYPO3\CouchDB\EntityByParentIdentifierView
	 */
	protected $entityByParentIdentifierView;

	/**
	 * @var boolean
	 */
	protected $enableCouchdbLucene = FALSE;

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
	 * @param \TYPO3\FLOW3\Object\ObjectManagerInterface $objectManager
	 * @return void
	 */
	public function injectObjectManager(\TYPO3\FLOW3\Object\ObjectManagerInterface $objectManager) {
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
		$this->classSchemata = $this->reflectionService->getClassSchemata();
		$this->connect();
	}

	/**
	 * Connect to CouchDB and select the database
	 *
	 * @return void
	 */
	protected function connect() {
		$this->client = $this->objectManager->create('TYPO3\CouchDB\Client', $this->dataSourceName);
		if ($this->databaseName !== NULL) {
			$this->client->setDatabaseName($this->databaseName);
		}
	}

	/**
	 * Override persistObject of AbstractBackend to fix a problem with multiple
	 * instances when merging objects and document update conflicts.
	 *
	 * @param object $object
	 * @param string $parentIdentifier
	 * @return string
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	protected function persistObject($object, $parentIdentifier) {
		$identifier = $this->persistenceSession->getIdentifierByObject($object);
		if ($this->persistenceSession->hasIdentifier($identifier) && $this->persistenceSession->getObjectByIdentifier($identifier) != $object) {
			return $identifier;
		}
		return parent::persistObject($object, $parentIdentifier);
	}

	/**
	 * Actually store an object, backend-specific
	 *
	 * @param object $object
	 * @param string $identifier
	 * @param string $parentIdentifier
	 * @param array $objectData
	 * @return integer one of self::OBJECTSTATE_*
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	protected function storeObject($object, $identifier, $parentIdentifier, array &$objectData) {
		if ($this->persistenceSession->hasObject($object)) {
			$objectState = self::OBJECTSTATE_RECONSTITUTED;
		} else {
				// Just get the identifier and register the object, create document with properties later
			$this->persistenceSession->registerObject($object, $identifier);
			$objectState = self::OBJECTSTATE_NEW;
		}

		$classSchema = $this->classSchemata[get_class($object)];
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
			$revision = $this->storeObjectDocument($objectData);
			$this->setRevisionMetadata($object, $revision);
			$objectData['metadata']['CouchDB_Revision'] = $revision;
		}

		return $objectState;
	}

	/**
	 * Iterate over deleted entities and process them.
	 * This method overrides the default behaviour by really
	 *
	 * @return void
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	protected function processDeletedObjects() {
		foreach ($this->deletedEntities as $entity) {
			if ($this->persistenceSession->hasObject($entity)) {
				$this->reallyRemoveEntity($entity);
				$this->persistenceSession->unregisterReconstitutedEntity($entity);
				$this->persistenceSession->unregisterObject($entity);
			}
		}
		$this->deletedEntities = new \SplObjectStorage();
	}

	/**
	 * Get metadata from AOP Proxy if it was set before in the DataMapper.
	 *
	 * @param object $object The object to get the metadata for
	 * @return array The metadata as an array
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	protected function collectMetadata($object) {
		if (isset($object->FLOW3_Persistence_Metadata)) {
			return $object->FLOW3_Persistence_Metadata;
		}
	}

	/**
	 * Set metadata for the stored revision on the AOP proxy to resolve
	 * document update conflicts after explicit calls to persistAll().
	 *
	 * @param object $object
	 * @param string $revision
	 * @return void
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	protected function setRevisionMetadata($object, $revision) {
		$object->FLOW3_Persistence_Metadata = array(
			'CouchDB_Revision' => $revision
		);
	}

	/**
	 * Creates or updates a document for the given object data. An update is
	 * done by using the revision inside the metadata of the object.
	 *
	 * @param array $objectData The object data for the object to store
	 * @return string The revision of the created record
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 * @todo Catch exceptions for conflicts when updating the document
	 * @todo (Later) Try to use an update handler inside CouchDB for partial updates
	 */
	protected function storeObjectDocument(array $objectData) {
		$objectData['_id'] = $objectData['identifier'];
		unset($objectData['identifier']);

		if (isset($objectData['metadata']) && isset($objectData['metadata']['CouchDB_Revision'])) {
			$objectData['_rev'] = $objectData['metadata']['CouchDB_Revision'];
		}
		unset($objectData['metadata']);

		$result = $this->doOperation(function($client) use (&$objectData) {
			return $client->createDocument($objectData);
		});

		return $result->getRevision();
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
			$propertyValue = \TYPO3\FLOW3\Reflection\ObjectAccess::getProperty($object, $propertyName, TRUE);

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
				if ($this->classSchemata[get_class($object)]->getModelType() === \TYPO3\FLOW3\Reflection\ClassSchema::MODELTYPE_ENTITY
						&& $this->classSchemata[get_class($object)]->isAggregateRoot() === FALSE) {
					$this->reallyRemoveEntity($object);
				}
			};
		}
	}

	/**
	 * Removes the document for an entity and also removes all entities that
	 * belong to this object as a parent and are not an aggregate root.
	 *
	 * If the revision of an object is not set (e.g. for lazy references)
	 * the revision will be fetched.
	 *
	 * @param object $object An object
	 * @return void
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	protected function reallyRemoveEntity($object) {
		$identifier = $this->persistenceSession->getIdentifierByObject($object);
		$revision = $this->getRevisionByObject($object);

		$this->removeEntitiesByParent($identifier);

		$this->doOperation(function($client) use ($identifier, $revision) {
			return $client->deleteDocument($identifier, $revision);
		});

		$this->emitRemovedObject($object);
	}

	/**
	 * Implement remove entity by attaching entities to
	 * the list of to be deleted entities. This works as long as
	 * commit first persists and then deletes objects.
	 *
	 * @param object $object
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	protected function removeEntity($object) {
		$this->deletedEntities->attach($object);
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
	 *
	 * @param type $object
	 * @param type $objectState
	 * @return void
	 */
	protected function emitPersistedObject($object, $objectState) {
		if (property_exists($object, 'FLOW3_Persistence_clone') && $object->FLOW3_Persistence_clone === TRUE) {
				// Detach any deleted entity that has been merged afterwards
			$this->deletedEntities->detach($object);
		}
		parent::emitPersistedObject($object, $objectState);
	}

	/**
	 * Process object data for an object
	 *
	 * @param object $object
	 * @param string $parentIdentifier
	 * @return array The object data for the given object
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 */
	protected function processObject($object, $parentIdentifier) {
		$className = get_class($object);
		$classSchema = $this->classSchemata[$className];
		if ($classSchema->getModelType() === \TYPO3\FLOW3\Reflection\ClassSchema::MODELTYPE_VALUEOBJECT) {
			$valueIdentifier = $this->persistenceSession->getIdentifierByObject($object);
			$noDirtyOnValueObject = FALSE;
			return array(
				'identifier' => $valueIdentifier,
				'classname' => $className,
				'properties' => $this->collectProperties($valueIdentifier, $object, $classSchema->getProperties(), $noDirtyOnValueObject)
			);
		} else {
			if ($classSchema->isAggregateRoot() && !$this->persistenceManager->isNewObject($object)) {
				return array(
					'identifier' => $this->persistenceSession->getIdentifierByObject($object)
				);
			} else {
				return array(
					'identifier' => $this->persistObject($object, $parentIdentifier)
				);
			}
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
		try {
			$identifier = $this->persistenceSession->getIdentifierByObject($object);
			$rawResponse = $this->client->getDocumentInformation($identifier);
			return $rawResponse->getRevision();
		} catch(\TYPO3\CouchDB\Client\NotFoundException $notFoundException) {
			return NULL;
		}
	}

	/**
	 * Store a view inside CouchDB if it is not yet defined. Creates the
	 * design document on the fly if it does not exist already.
	 *
	 * @param \TYPO3\CouchDB\ViewInterface $view
	 * @return void
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function storeView(\TYPO3\CouchDB\ViewInterface $view) {
		try {
			$design = $this->doOperation(function($client) use ($view) {
				return $client->getDocument('_design/' . $view->getDesignName());
			});

			if (isset($design->views->{$view->getViewName()})) {
				return;
			}
		} catch(\TYPO3\CouchDB\Client\NotFoundException $notFoundException) {
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
	 * Store an index inside CouchDB if it is not yet defined. Creates the
	 * index document on the fly if it does not exist already.
	 *
	 * @param \TYPO3\CouchDB\Domain\Index\LuceneIndex $index
	 * @param array $arguments
	 * @return void
	 * @author Felix Oertel <oertel@networkteam.com>
	 */
	public function storeIndex(\TYPO3\CouchDB\Domain\Index\LuceneIndex $index, array $arguments) {
		try {
			$design = $this->doOperation(function($client) use ($index) {
				return $client->getDocument('_design/' . $index->getIndexName());
			});

			if (isset($design->{$index->getIndexType()}->search)) {
				return;
			}
		} catch (\TYPO3\CouchDB\Client\NotFoundException $notFoundException) {
			$design = new \stdClass();
			$design->_id = '_design/' . $index->getIndexName();
			$design->{$index->getIndexType()} = new \stdClass();
		}

		$design->{$index->getIndexType()}->search = new \stdClass();
		$design->{$index->getIndexType()}->search->index = $index->getIndexFunctionSource();

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
	 * @param \TYPO3\FLOW3\Persistence\QueryInterface $query
	 * @return integer
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function getObjectCountByQuery(\TYPO3\FLOW3\Persistence\QueryInterface $query) {
		if ($query instanceof \TYPO3\CouchDB\Persistence\LuceneQuery) {
			$result = $this->queryIndex($query->getIndex(), array('query' => $query, 'count' => TRUE));
			if ($result !== NULL && isset($result->total_rows) && is_int($result->total_rows)) {
				return $result->total_rows;
			} else {
				throw new \TYPO3\CouchDB\InvalidResultException('Could not get count from result', 1287074017, NULL, $result);
			}
		} else {
			$view = $this->objectManager->create('TYPO3\CouchDB\QueryView', $query);
			$result = $this->queryView($view, array('query' => $query, 'count' => TRUE));
			if ($result !== NULL && isset($result->rows) && is_array($result->rows)) {
				return (count($result->rows) === 1) ? $result->rows[0]->value : 0;
			} else {
				throw new \TYPO3\CouchDB\InvalidResultException('Could not get count from result', 1287074016, NULL, $result);
			}
		}
	}

	/**
	 * Returns the object data for the given identifier.
	 *
	 * @param string $identifier The UUID or Hash of the object
	 * @return array The object data of the object or FALSE if the identifier was not found
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 * @todo Maybe introduce a ObjectNotFound exception?
	 */
	public function getObjectDataByIdentifier($identifier, $objectType = NULL) {
		try {
			$doc = $this->doOperation(function($client) use ($identifier) {
				return $client->getDocument($identifier);
			});
		} catch(\TYPO3\CouchDB\Client\NotFoundException $notFoundException) {
			$doc = NULL;
		}
		if ($doc === NULL) {
			return FALSE;
		}
		$data = $this->documentsToObjectData(array($doc));
		return $data[0];
	}

	/**
	 * Returns the object data matching the $query.
	 *
	 * @param \TYPO3\FLOW3\Persistence\QueryInterface $query
	 * @return array
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function getObjectDataByQuery(\TYPO3\FLOW3\Persistence\QueryInterface $query) {
		if ($query instanceof \TYPO3\CouchDB\Persistence\LuceneQuery) {
			return $this->getObjectDataByIndex($query->getIndex(), array('query' => $query));
		} else {
			$view = $this->objectManager->create('TYPO3\CouchDB\QueryView', $query);
			return $this->getObjectDataByView($view, array('query' => $query));
		}
	}

	/**
	 * Get view results and convert documents to object data. The view can
	 * either emit the full object data document as the value or use
	 * the query option "include_docs=true".
	 *
	 * @param \TYPO3\CouchDB\ViewInterface $view The view to execute
	 * @param array $arguments An array with arguments to the view
	 * @return array Array of object data
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function getObjectDataByView(\TYPO3\CouchDB\ViewInterface $view, array $arguments) {
		$result = $this->queryView($view, $arguments);
		if ($result !== NULL) {
			return $this->documentsToObjectData($this->resultToDocuments($result));
		} else {
			return array();
		}
	}

	/**
	 * Get results for a lucene query and convert documents to object data.
	 *
	 * @param \TYPO3\CouchDB\Domain\Index\LuceneIndex $index The index to execute
	 * @param array $arguments An array with arguments to the index
	 * @return array Array of object data
	 * @author Felix Oertel <oertel@networkteam.com>
	 */
	public function getObjectDataByIndex(\TYPO3\CouchDB\Domain\Index\LuceneIndex $index, array $arguments) {
		$result = $this->queryIndex($index, $arguments);
		if ($result !== NULL) {
			return $this->documentsToObjectData($this->resultToDocuments($result));
		} else {
			return array();
		}
	}

	/**
	 * "Execute" a view with the given arguments, these are view specific. The
	 * view will be stored in CouchDB if it is not yet defined.
	 *
	 * @param \TYPO3\CouchDB\ViewInterface $view
	 * @param array $arguments
	 * @return object The results of the view
	 */
	public function queryView(\TYPO3\CouchDB\ViewInterface $view, array $arguments) {
		$that = $this;
		return $this->doOperation(function($client) use ($view, &$arguments, $that) {
			try {
				return $client->queryView($view->getDesignName(), $view->getViewName(), $view->buildViewParameters($arguments));
			} catch(\TYPO3\CouchDB\Client\NotFoundException $notFoundException) {
				$that->storeView($view);
				return $client->queryView($view->getDesignName(), $view->getViewName(), $view->buildViewParameters($arguments));
			}
		});
	}

	/**
	 * "Execute" a lucene query.
	 *
	 * @param \TYPO3\CouchDB\Domain\Index\LuceneIndex $index The index to execute
	 * @param array $arguments An array with arguments to the index
	 * @return object The results of the index
	 * @author Felix Oertel <oertel@networkteam.com>
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function queryIndex(\TYPO3\CouchDB\Domain\Index\LuceneIndex $index, array $arguments) {
		$that = $this;
		return $this->doOperation(function($client) use ($index, &$arguments, $that) {
			try {
				return $client->queryIndex($index->getIndexName(), $index->getIndexType(), $index->buildIndexParameters($arguments));
			} catch(\TYPO3\CouchDB\Client\ClientException $clientException) {
				$information = $clientException->getInformation();
				if ($information['reason'] === 'no_such_view') {
					$that->storeIndex($index, $arguments);
					return $client->queryIndex($index->getIndexName(), $index->getIndexType(), $index->buildIndexParameters($arguments));
				}
				throw $clientException;
			}
		});
	}

	/**
	 * Process CouchDB results, add metadata and process object
	 * values by loading objects. This method processes documents
	 * batched for loading nested entities.
	 *
	 * @param array $documents Documents as objects
	 * @param array &$knownObjects
	 * @return array
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	protected function documentsToObjectData(array $documents, array &$knownObjects = array()) {
		$identifiersToFetch = array();
		$data = array();
		foreach ($documents as $document) {
			$objectData = \TYPO3\FLOW3\Utility\Arrays::convertObjectToArray($document);
				// CouchDB marks documents as deleted, we need to skip these documents here
			if (isset($objectData['deleted']) && $objectData['deleted'] === TRUE) {
				continue;
			}
			$objectData['identifier'] = $objectData['_id'];
			$objectData['metadata'] = array(
				'CouchDB_Revision' => $objectData['_rev']
			);
			unset($objectData['_id']);
			unset($objectData['_rev']);

			$knownObjects[$objectData['identifier']] = TRUE;

			if (!isset($objectData['classname'])) {
				throw new \TYPO3\CouchDB\InvalidResultException('Expected property "classname" in document', 1290442039, NULL, $document);
			}
			if (!isset($this->classSchemata[$objectData['classname']])) {
				throw new \TYPO3\CouchDB\InvalidResultException('Class "' . $objectData['classname'] . '" was not registered', 1290442092, NULL, $document);
			}

			$this->processResultProperties($objectData['properties'], $identifiersToFetch, $knownObjects, $this->classSchemata[$objectData['classname']]);

			$data[] = $objectData;
		}

		if (count($identifiersToFetch) > 0) {
			$documents = $this->resultToDocuments($this->doOperation(function(\TYPO3\CouchDB\Client $client) use ($identifiersToFetch) {
				return $client->getDocuments(array_keys($identifiersToFetch), array('include_docs' => TRUE));
			}));

			$fetchedObjectsData = $this->documentsToObjectData($documents, $knownObjects);

			foreach ($fetchedObjectsData as $fetchedObjectData) {
				$identifiersToFetch[$fetchedObjectData['identifier']] = $fetchedObjectData;
			}
		}

		return $data;
	}

	/**
	 * Process an array of object data properties and add identifiers to fetch
	 * for recursive processing in nested objects
	 *
	 * @param array &$properties
	 * @param array &$identifiersToFetch
	 * @param array &$knownObjects
	 * @param \TYPO3\FLOW3\Reflection\ClassSchema $classSchema
	 * @return void
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	protected function processResultProperties(array &$properties, array &$identifiersToFetch, array &$knownObjects, \TYPO3\FLOW3\Reflection\ClassSchema $classSchema) {
		foreach ($properties as $propertyName => &$propertyData) {
				// Skip unknown properties
			if (!$classSchema->hasProperty($propertyName)) {
				continue;
			}
			$propertyMetadata = $classSchema->getProperty($propertyName);
			if (!$propertyData['multivalue']) {
				if (isset($propertyData['value']['identifier']) && !isset($propertyData['value']['classname'])) {
					if ($propertyMetadata['lazy'] !== TRUE) {
						if (!isset($knownObjects[$propertyData['value']['identifier']])) {
							$identifiersToFetch[$propertyData['value']['identifier']] = NULL;
							$propertyData['value'] = &$identifiersToFetch[$propertyData['value']['identifier']];
						}
					} else {
						$propertyData['value'] = array('identifier' => $propertyData['value']['identifier'], 'classname' => $propertyData['type'], 'properties' => array());
					}
				} elseif (is_array($propertyData['value']) && isset($propertyData['value']['properties'])) {
					$this->processResultProperties($propertyData['value']['properties'], $identifiersToFetch, $knownObjects, $this->classSchemata[$propertyData['value']['classname']]);
				}
			} else {
				for ($index = 0; $index < count($propertyData['value']); $index++) {
					if (isset($propertyData['value'][$index]['value']['identifier']) && !isset($propertyData['value'][$index]['value']['classname'])) {
						if ($propertyMetadata['lazy'] !== TRUE) {
							if (!isset($knownObjects[$propertyData['value'][$index]['value']['identifier']])) {
								$identifiersToFetch[$propertyData['value'][$index]['value']['identifier']] = NULL;
								$propertyData['value'][$index]['value'] = &$identifiersToFetch[$propertyData['value'][$index]['value']['identifier']];
							}
						} else {
							$propertyData['value'][$index]['value'] = array('identifier' => $propertyData['value'][$index]['value']['identifier'], 'classname' => $propertyData['value'][$index]['type'], 'properties' => array());
						}
					} elseif (is_array($propertyData['value']) && isset($propertyData['value'][$index]['value']['properties']) && is_array($propertyData['value'][$index]['value'])) {
						$this->processResultProperties($propertyData['value'][$index]['value']['properties'], $identifiersToFetch, $knownObjects, $this->classSchemata[$propertyData['value'][$index]['value']['classname']]);
					}
				}
			}
		}
	}

	/**
	 * Convert a CouchDB result to an array of documents
	 *
	 * @param object $result
	 * @return array
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	protected function resultToDocuments($result) {
		if (!isset($result->rows)) {
			throw new \TYPO3\CouchDB\InvalidResultException('Expected property "rows" in result', 1290693732, NULL, $result);
		}
		return array_map(function($row) {
			if (!isset($row->doc) && !isset($row->value)) {
				throw new \TYPO3\CouchDB\InvalidResultException('Expected property "doc" or "value" in row, got ' . var_export($row, TRUE), 1290693735, NULL, $row);
			}
			return isset($row->doc) && $row->doc !== NULL ? $row->doc : $row->value;
		}, $result->rows);
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
		} catch(\TYPO3\CouchDB\Client\ClientException $clientException) {
			$information = $clientException->getInformation();
			if (isset($information['error']) && $information['error'] === 'not_found' && $information['reason'] === 'no_db_file') {
				if ($this->client->createDatabase($this->databaseName)) {
					return $this->doOperation($couchDbOperation);
				} else {
					throw new \TYPO3\FLOW3\Persistence\Exception('Could not create database ' . $this->database, 1286901880);
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
	 * @return \TYPO3\CouchDB\EntityByParentIdentifierView
	 */
	public function getEntityByParentIdentifierView() {
		if ($this->entityByParentIdentifierView === NULL) {
			$this->entityByParentIdentifierView = $this->objectManager->create('TYPO3\CouchDB\EntityByParentIdentifierView');
		}
		return $this->entityByParentIdentifierView;
	}

	/**
	 * @return boolean
	 */
	public function getEnableCouchdbLucene() {
		return $this->enableCouchdbLucene;
	}

	/**
	 * @param boolean $enableCouchdbLucene
	 * @return void
	 */
	public function setEnableCouchdbLucene($enableCouchdbLucene) {
		$this->enableCouchdbLucene = $enableCouchdbLucene;
	}

}
?>