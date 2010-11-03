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
	 * @inject
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
	protected $database;

	/**
	 * @var F3\CouchDB\EntityByParentIdentifierView
	 */
	protected $entityByParentIdentifierView;

	/**
	 * Initializes the backend and connects the CouchDB client,
	 * will be called by PersistenceManager
	 *
	 * @param array $options
	 * @return void
	 */
	public function initialize(array $options) {
		$options = array_filter($options, function($value) {
			return $value !== NULL;
		});
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
		$this->client->setDatabaseName($this->database);
	}

	/**
	 * Stores or updates an object in the underlying storage.
	 *
	 * @param object $object The object to persist
	 * @param string $parentIdentifier
	 * @return string
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	protected function persistObject($object, $parentIdentifier = NULL) {
		if (isset($this->visitedDuringPersistence[$object])) {
			return $this->visitedDuringPersistence[$object];
		}

		if (!$this->persistenceSession->hasObject($object) && property_exists($object, 'FLOW3_Persistence_clone') && $object->FLOW3_Persistence_clone === TRUE) {
			$this->persistenceManager->replaceObject($this->persistenceSession->getObjectByIdentifier($this->getIdentifierFromObject($object)), $object);
		}

		$classSchema = $this->classSchemata[$object->FLOW3_AOP_Proxy_getProxyTargetClassName()];
		if ($this->persistenceSession->hasObject($object)) {
			$identifier = $this->persistenceSession->getIdentifierByObject($object);
			$objectState = self::OBJECTSTATE_RECONSTITUTED;
		} else {
			// Just get the identifier and register the object, create document with properties later
			$identifier = $this->getIdentifierFromObject($object);
			$this->persistenceSession->registerObject($object, $identifier);

			$objectState = self::OBJECTSTATE_NEW;
		}

		$this->visitedDuringPersistence[$object] = $identifier;

		$dirty = FALSE;

		$objectData = array(
			'identifier' => $identifier,
			'classname' => $classSchema->getClassName(),
			'properties' => $this->collectProperties($classSchema->getProperties(), $object, $identifier, $dirty),
			'metadata' => $this->collectMetadata($object),
			'parentIdentifier' => $parentIdentifier
		);

		if ($objectState === self::OBJECTSTATE_NEW || $dirty) {
			$this->validateObject($object);
			$this->storeObjectDocument($objectData);
		}

		if ($classSchema->getModelType() === \F3\FLOW3\Reflection\ClassSchema::MODELTYPE_ENTITY) {
			$this->persistenceSession->registerReconstitutedEntity($object, $objectData);
		}
		$this->emitPersistedObject($object, $objectState);

		return $identifier;
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

		if (isset($objectData['metadata']) && isset($objectData['metadata']['CouchDB_Revision'])) {
			$objectData['_rev'] = $objectData['metadata']['CouchDB_Revision'];
		}
		unset($objectData['metadata']);

		$this->doOperation(function($client) use (&$objectData) {
			$client->createDocument($objectData);
		});

		return $objectData['identifier'];

	}

	/**
	 *
	 * @param array $properties The properties to collect (as per class schema)
	 * @param object $object The object to work on
	 * @param string $identifier The object's identifier
	 * @param boolean $dirty A dirty flag that is passed by reference and set to TRUE if a dirty property was found
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	protected function collectProperties(array $properties, $object, $identifier, &$dirty) {
		$propertyData = array();
		foreach ($properties as $propertyName => $propertyMetaData) {
			$propertyValue = $object->FLOW3_AOP_Proxy_getProperty($propertyName);
			$propertyType = $propertyMetaData['type'];

			if ($propertyType === 'ArrayObject') {
				throw new \F3\FLOW3\Persistence\Exception('ArrayObject properties are not supported - missing feature?!?', 1283524355);
			}

			if (is_object($propertyValue)) {
				if ($propertyType === 'object') {
					if (!($propertyValue instanceof \F3\FLOW3\AOP\ProxyInterface)) {
						throw new \F3\FLOW3\Persistence\Exception\IllegalObjectTypeException('Property of generic type object holds "' . get_class($propertyValue) . '", which is not persistable (no @entity or @valueobject), in ' . $object->FLOW3_AOP_Proxy_getProxyTargetClassName() . '::' . $propertyName, 1283531761);
					}
					$propertyType = $propertyValue->FLOW3_AOP_Proxy_getProxyTargetClassName();
				} elseif(!($propertyValue instanceof $propertyType)) {
					throw new \F3\FLOW3\Persistence\Exception\UnexpectedTypeException('Expected property of type ' . $propertyType . ', but got ' . get_class($propertyValue) . ' for ' . $object->FLOW3_AOP_Proxy_getProxyTargetClassName() . '::' . $propertyName, 1244465558);
				}
			} elseif ($propertyValue !== NULL && $propertyType !== $this->getType($propertyValue)) {
				throw new \F3\FLOW3\Persistence\Exception\UnexpectedTypeException('Expected property of type ' . $propertyType . ', but got ' . gettype($propertyValue) . ' for ' . $object->FLOW3_AOP_Proxy_getProxyTargetClassName() . '::' . $propertyName, 1244465559);
			}

			if ($this->persistenceSession->isDirty($object, $propertyName)) {
				$dirty = TRUE;
			}

			if ($propertyValue instanceof \F3\FLOW3\AOP\ProxyInterface) {
				// TODO Code for value objects is duplicate with code in persistObject
				$classSchema = $this->classSchemata[$propertyValue->FLOW3_AOP_Proxy_getProxyTargetClassName()];
				$propertyIdentifier = $this->getIdentifierFromObject($propertyValue);
				if ($classSchema->getModelType() === \F3\FLOW3\Reflection\ClassSchema::MODELTYPE_VALUEOBJECT) {
					$noDirtyOnValueObject = FALSE;
					$propertyData[$propertyName] = array(
						'type' => $propertyType,
						'multivalue' => FALSE,
						'value' => array(
							'identifier' => $propertyIdentifier,
							'classname' => $classSchema->getClassName(),
							'properties' => $this->collectProperties($classSchema->getProperties(), $propertyValue, $propertyIdentifier, $noDirtyOnValueObject)
						)
					);
				} else {
					$propertyData[$propertyName] = array(
						'type' => $propertyType,
						'multivalue' => FALSE,
						'value' => array(
							'identifier' => $this->persistObject($propertyValue, $identifier)
						)
					);
				}
			} else {
				switch ($propertyType) {
					case 'DateTime':
						$propertyData[$propertyName] = array(
							'type' => 'DateTime',
							'multivalue' => FALSE,
							'value' => $this->processDateTime($propertyValue)
						);
					break;
					case 'array':
						$propertyData[$propertyName] = array(
							'type' => 'array',
							'multivalue' => TRUE,
							'value' => $this->processArray($propertyValue, $identifier, $this->persistenceSession->getCleanStateOfProperty($object, $propertyName))
						);
					break;
					case 'SplObjectStorage':
						$propertyData[$propertyName] = array(
							'type' => 'SplObjectStorage',
							'multivalue' => TRUE,
							'value' => $this->processSplObjectStorage($propertyValue, $identifier, $this->persistenceSession->getCleanStateOfProperty($object, $propertyName))
						);
					break;
					default:
						$propertyData[$propertyName] = array(
							'type' => $propertyType,
							'multivalue' => FALSE,
							'value' => $propertyValue
						);
					break;
				}
			}
		}

		return $propertyData;
	}

	/**
	 * Creates a unix timestamp from the given DateTime object. If NULL is given
	 * NULL will be returned.
	 *
	 * @param \DateTime $dateTime
	 * @return integer
	 * @todo (Later) Return a JavaScript Date parseable Format (e.g. "2008/06/09 13:52:11 +0000")
	 */
	protected function processDateTime(\DateTime $dateTime = NULL) {
		if ($dateTime instanceof \DateTime) {
			return $dateTime->getTimestamp();
		} else {
			return NULL;
		}
	}

	/**
	 * Store an array as an array.
	 *
	 * Note: Objects contained in the array will have a matching entry created,
	 * the objects must be persisted elsewhere!
	 *
	 * @param array $array The array to persist
	 * @param string $parentIdentifier
	 * @param array $previousArray the previously persisted state of the array
	 * @return array An array with "flat" values representing the array
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	protected function processArray(array $array = NULL, $parentIdentifier = '', array $previousArray = NULL) {
		if ($previousArray !== NULL && is_array($previousArray['value'])) {
			$this->removeDeletedArrayEntries($array, $previousArray['value']);
		}

		if ($array === NULL) {
			return NULL;
		}

		$values = array();
		foreach ($array as $key => $value) {
			if ($value instanceof \DateTime) {
				$values[] = array(
					'type' => 'DateTime',
					'index' => $key,
					'value' => $this->processDateTime($value)
				);
			} elseif ($value instanceof \SplObjectStorage) {
				throw new \F3\FLOW3\Persistence\Exception('SplObjectStorage instances in arrays are not supported - missing feature?!?', 1261048721);
			} elseif ($value instanceof \ArrayObject) {
				throw new \F3\FLOW3\Persistence\Exception('ArrayObject instances in arrays are not supported - missing feature?!?', 1283524345);
			} elseif (is_object($value)) {
				$type = $this->getType($value);
				$classSchema = $this->classSchemata[$value->FLOW3_AOP_Proxy_getProxyTargetClassName()];
				$valueIdentifier = $this->getIdentifierFromObject($value);
				if ($classSchema->getModelType() === \F3\FLOW3\Reflection\ClassSchema::MODELTYPE_VALUEOBJECT) {
					$noDirtyOnValueObject = FALSE;
					$values[] = array(
						'type' => $type,
						'index' => $key,
						'value' => array(
							'identifier' => $valueIdentifier,
							'classname' => $classSchema->getClassName(),
							'properties' => $this->collectProperties($classSchema->getProperties(), $value, $valueIdentifier, $noDirtyOnValueObject)
						)
					);
				} else {
					$values[] = array(
						'type' => $type,
						'index' => $key,
						'value' => array(
							'identifier' => $this->persistObject($value, $parentIdentifier)
						)
					);
				}
			} elseif (is_array($value)) {
				$values[] = array(
					'type' => 'array',
					'index' => $key,
					'value' => $this->processNestedArray($parentIdentifier, $value)
				);
			} else {
				$values[] = array(
					'type' => $this->getType($value),
					'index' => $key,
					'value' => $value
				);
			}
		}

		return $values;
	}

	/**
	 * "Serializes" a nested array for storage.
	 *
	 * @param string $parentIdentifier
	 * @param array $nestedArray
	 * @return string
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 */
	protected function processNestedArray($parentIdentifier, array $nestedArray) {
		$identifier = uniqid('a', TRUE);
		$data = array(
			'multivalue' => TRUE,
			'value' => $this->processArray($nestedArray, $parentIdentifier)
		);
		return $identifier;
	}

	/**
	 * Remove objects removed from array compared to $previousArray.
	 *
	 * @param array $array
	 * @param array $previousArray
	 * @return void
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 */
	protected function removeDeletedArrayEntries(array $array = NULL, array $previousArray) {
		foreach ($previousArray as $item) {
			if ($item['type'] === 'array') {
				$this->removeDeletedArrayEntries($array[$item['index']], $item['value']);
			} elseif ($this->getTypeName($item['type']) === 'object' && !($item['type'] === 'DateTime' || $item['type'] === 'SplObjectStorage')) {
				if (!$this->persistenceSession->hasIdentifier($item['value']['identifier'])) {
						// ingore this identifier, assume it was blocked by security query rewriting
					continue;
				}

				$object = $this->persistenceSession->getObjectByIdentifier($item['value']['identifier']);
				if ($array === NULL || !$this->arrayContainsObject($array, $object)) {
					if ($this->classSchemata[$item['type']]->getModelType() === \F3\FLOW3\Reflection\ClassSchema::MODELTYPE_ENTITY
							&& $this->classSchemata[$item['type']]->isAggregateRoot() === FALSE) {
						$this->removeEntity($this->persistenceSession->getObjectByIdentifier($item['value']['identifier']));
					}
				}
			}
		}
	}

	/**
	 * Store an SplObjectStorage inline
	 *
	 * @param \SplObjectStorage $splObjectStorage The SplObjectStorage to persist
	 * @param string $parentIdentifier
	 * @param array $previousObjectStorage the previously persisted state of the SplObjectStorage
	 * @return array An array with "flat" values representing the SplObjectStorage
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 */
	protected function processSplObjectStorage(\SplObjectStorage $splObjectStorage = NULL, $parentIdentifier = '', array $previousObjectStorage = NULL) {
		if ($previousObjectStorage !== NULL && $previousObjectStorage['value'] !== NULL) {
			$this->removeDeletedSplObjectStorageEntries($splObjectStorage, $previousObjectStorage['value']);
		}

		if ($splObjectStorage === NULL) {
			return NULL;
		}

		$values = array();
		foreach ($splObjectStorage as $object) {
			if ($object instanceof \DateTime) {
				$values[] = array(
					'type' => 'DateTime',
					'index' => NULL,
					'value' => $object->getTimestamp()
				);
			} elseif ($object instanceof \SplObjectStorage) {
				throw new \F3\FLOW3\Persistence\Exception('SplObjectStorage instances in SplObjectStorage are not supported - missing feature?!?', 1283524360);
			} elseif ($object instanceof \ArrayObject) {
				throw new \F3\FLOW3\Persistence\Exception('ArrayObject instances in SplObjectStorage are not supported - missing feature?!?', 1283524350);
			} else {
				$type = $this->getType($object);
				$classSchema = $this->classSchemata[$object->FLOW3_AOP_Proxy_getProxyTargetClassName()];
				$objectIdentifier = $this->getIdentifierFromObject($object);
				if ($classSchema->getModelType() === \F3\FLOW3\Reflection\ClassSchema::MODELTYPE_VALUEOBJECT) {
					$noDirtyOnValueObject = FALSE;
					$values[] = array(
						'type' => $type,
						'index' => NULL,
						'value' => array(
							'identifier' => $objectIdentifier,
							'classname' => $classSchema->getClassName(),
							'properties' => $this->collectProperties($classSchema->getProperties(), $object, $objectIdentifier, $noDirtyOnValueObject)
						)
					);
				} else {
					$values[] = array(
						'type' => $type,
						'index' => NULL,
						'value' => array(
							'identifier' => $this->persistObject($object, $parentIdentifier)
						)
					);
				}
			}
		}

		return $values;
	}

	/**
	 * Remove objects removed from SplObjectStorage compared to
	 * $previousSplObjectStorage.
	 *
	 * @param \SplObjectStorage $splObjectStorage
	 * @param array $previousObjectStorage
	 * @return void
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 */
	protected function removeDeletedSplObjectStorageEntries(\SplObjectStorage $splObjectStorage = NULL, array $previousObjectStorage = NULL) {
			// remove objects detached since reconstitution
		foreach ($previousObjectStorage as $item) {
			if ($splObjectStorage instanceof \F3\FLOW3\Persistence\LazySplObjectStorage && !$this->persistenceSession->hasIdentifier($item['value']['identifier'])) {
					// ingore this identifier, assume it was blocked by security query rewriting upon activation
				continue;
			}

			$object = $this->persistenceSession->getObjectByIdentifier($item['value']['identifier']);
			if ($splObjectStorage === NULL || !$splObjectStorage->contains($object)) {
				if ($this->classSchemata[$object->FLOW3_AOP_Proxy_getProxyTargetClassName()]->getModelType() === \F3\FLOW3\Reflection\ClassSchema::MODELTYPE_ENTITY
						&& $this->classSchemata[$object->FLOW3_AOP_Proxy_getProxyTargetClassName()]->isAggregateRoot() === FALSE) {
					$this->removeEntity($object);
				}
			}
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
	 * Get the CouchDB revision of an object
	 *
	 * @param object $object An object
	 * @return string The current revision if it was set, NULL otherwise
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	protected function getRevisionByObject($object) {
		$metadata = $this->collectMetadata($object);
		if ($metadata !== NULL && isset($metadata['CouchDB_Revision'])) {
			return $metadata['CouchDB_Revision'];
		}
		return NULL;
	}

	/**
	 * Store a view inside CouchDB if it is not yet defined
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
		} catch(\F3\CouchDB\Client\ClientException $e) {
			$information = $e->getInformation();
			if ($information['error'] === 'not_found' && $information['reason'] === 'missing') {
				$design = new \stdClass();
				$design->_id = '_design/' . $view->getDesignName();
				$design->views = new \stdClass();
			}
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
		if (is_array($result->rows)) {
			return (count($result->rows) === 1) ? $result->rows[0]->value : 0;
		} else {
			throw new \Exception('Could not get count', 1287074016);
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
	 * @return array The results of the view
	 */
	public function queryView(\F3\CouchDB\ViewInterface $view, $arguments) {
		$that = $this;
		return $this->doOperation(function($client) use ($view, &$arguments, $that) {
			try {
				return $client->queryView($view->getDesignName(), $view->getViewName(), $view->getViewParameters($arguments));
			} catch(\F3\CouchDB\Client\NotFoundException $e) {
				$that->storeView($view);
				return $client->queryView($view->getDesignName(), $view->getViewName(), $view->getViewParameters($arguments));
			}
		});
	}

	/**
	 * Process a CouchDB result and add metadata and process
	 * object values by loading objects.
	 *
	 * @param array $result The raw document from CouchDB
	 * @return array
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	protected function resultToObjectData($result) {
		$objectData = \F3\FLOW3\Utility\Arrays::convertObjectToArray($result);
		$objectData['metadata'] = array(
			'CouchDB_Revision' => $objectData['_rev']
		);
		$identifiersToFetch = array();
		foreach ($objectData['properties'] as $propertyName => $propertyData) {
			if (!$propertyData['multivalue']) {
				// Load entity
				if (isset($propertyData['value']['identifier']) && !isset($propertyData['value']['classname'])) {
					$identifiersToFetch[$propertyData['value']['identifier']] = NULL;
					$objectData['properties'][$propertyName]['value'] = &$identifiersToFetch[$propertyData['value']['identifier']];
				}
			} else {
				for ($index = 0; $index < count($propertyData['value']); $index++) {
					// Load entity
					if (isset($propertyData['value'][$index]['value']['identifier']) && !isset($propertyData['value'][$index]['value']['classname'])) {
						$identifiersToFetch[$propertyData['value'][$index]['value']['identifier']] = NULL;
						$objectData['properties'][$propertyName]['value'][$index]['value'] = &$identifiersToFetch[$propertyData['value'][$index]['value']['identifier']];
					}
				}
			}
		}

		$documents = $this->doOperation(function(\F3\CouchDB\Client $client) use ($identifiersToFetch) {
			return $client->getDocuments(array_keys($identifiersToFetch), array('include_docs' => TRUE));
		});
		foreach ($documents->rows as $document) {
			$identifiersToFetch[$document->id] = $this->resultToObjectData($document->doc);
		}
		return $objectData;
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
		} catch(\F3\CouchDB\Client\ClientException $e) {
			$information = $e->getInformation();
			if ($information['error'] === 'not_found' && $information['reason'] === 'no_db_file') {
				if ($this->client->createDatabase($this->database)) {
					return $this->doOperation($couchDbOperation);
				} else {
					throw new \F3\FLOW3\Persistence\Exception('Could not create database ' . $this->database, 1286901880);
				}
			} else {
				throw $e;
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
		$databaseName = $this->database;
		$this->doOperation(function($client) use ($databaseName) {
			if ($client->databaseExists($databaseName)) {
				$client->deleteDatabase($databaseName);
			}
			$client->createDatabase($databaseName);
			$client->setDatabaseName($databaseName);
		});
	}

	/**
	 * Returns the type name as used in the database table names.
	 *
	 * @param string $type
	 * @return string
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 */
	protected function getTypeName($type) {
		if (strstr($type, '\\')) {
			return 'object';
		} else {
			return strtolower($type);
		}
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

	/**
	 * @param string $dataSourceName
	 * @return void
	 */
	public function setDataSourceName($dataSourceName) {
		$this->dataSourceName = $dataSourceName;
	}

	/**
	 * @param string $database
	 * @return void
	 */
	public function setDatabase($database) {
		$this->database = $database;
	}
}

?>