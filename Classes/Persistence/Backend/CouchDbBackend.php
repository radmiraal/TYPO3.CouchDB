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
	 * @var \CouchdbClient
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
	 *
	 * @param \F3\FLOW3\Object\ObjectManagerInterface $objectManager
	 */
	public function injectObjectManager(\F3\FLOW3\Object\ObjectManagerInterface $objectManager) {
		$this->objectManager = $objectManager;
	}

	/**
	 * Initializes the backend
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
	 * Connect to the database
	 *
	 * @return void
	 */
	protected function connect() {
		if (!extension_loaded("couchdb")) {
			throw new \F3\FLOW3\Persistence\Exception('The PHP extension "couchdb" must be installed and loaded in order to use the CouchDB backend.', 1283188509);
		}

		// TODO Use options
		$this->client = new \CouchdbClient($this->dataSourceName);
		$this->client->selectDB('my_rossmann');
	}

	/**
	 * Stores or updates an object in the underlying storage.
	 *
	 * @param object $object The object to persist
	 * @param string $parentIdentifier
	 * @return string
	 * @author Karsten Dambekalns <karsten@typo3.org>
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
			'metadata' => $this->collectMetadata($object)
		);

		if ($objectState === self::OBJECTSTATE_NEW || $dirty) {
			$this->validateObject($object);
			$this->storeObjectDocument($object, $objectData);
		}

		if ($classSchema->getModelType() === \F3\FLOW3\Reflection\ClassSchema::MODELTYPE_ENTITY) {
			$this->persistenceSession->registerReconstitutedEntity($object, $objectData);
		}
		$this->emitPersistedObject($object, $objectState);

		return $identifier;
	}

	/**
	 * Get metadata from Proxy if it was set before. The metadata is used
	 * to collect information about the current revision and is understood
	 * by the DataMapper.
	 *
	 * @param object $object
	 * @return array
	 */
	protected function collectMetadata($object) {
		$metadata = NULL;
		if ($object->FLOW3_AOP_Proxy_hasProperty('FLOW3_Persistence_Metadata')) {
			$metadata = $object->FLOW3_AOP_Proxy_getProperty('FLOW3_Persistence_Metadata');
		}
		return $metadata;
	}

	/**
	 * Creates a document with inline properties for the given object
	 *
	 * @param object $object The object for which to create a node
	 * @param array $objectData
	 * @return string The identifier of the created record
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	protected function storeObjectDocument($object, $objectData) {
		$objectData['_id'] = $objectData['identifier'];

		if (isset($objectData['metadata']) && isset($objectData['metadata']['CouchDB_Revision'])) {
			$objectData['_rev'] = $objectData['metadata']['CouchDB_Revision'];
		}
		unset($objectData['metadata']);
		$this->client->storeDoc($objectData);

		return $objectData['identifier'];

	}

	/**
	 *
	 * @param array $properties The properties to collect (as per class schema)
	 * @param object $object The object to work on
	 * @param string $identifier The object's identifier
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
	 * TODO return JavaScript Date parseavle Format (e.g. "2008/06/09 13:52:11 +0000") 
	 *
	 * @param \DateTime $dateTime
	 * @return integer
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
	protected function processArray(array $array = NULL, $parentIdentifier, array $previousArray = NULL) {
		if ($previousArray !== NULL) {
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
				$values[] = array(
					'type' => $type,
					'index' => $key,
					'value' => array(
						'identifier' => $this->persistObject($value, $parentIdentifier)
					)
				);
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
	protected function processSplObjectStorage(\SplObjectStorage $splObjectStorage = NULL, $parentIdentifier, array $previousObjectStorage = NULL) {
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
				// TODO Handle value object inline!
				$values[] = array(
					'type' => $type,
					'index' => NULL,
					'value' => array(
						'identifier' => $this->persistObject($object, $parentIdentifier)
					)
				);
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
	protected function removeDeletedSplObjectStorageEntries(\SplObjectStorage $splObjectStorage = NULL, array $previousObjectStorage) {
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
	 * Removes an entity (TODO remove all entities contained within it's boundary)
	 *
	 * @param object $object An object
	 * @return void
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	protected function removeEntity($object) {
		// TODO $this->removeEntitiesByParent($object);

		$identifier = $this->persistenceSession->getIdentifierByObject($object);
		$revision = $this->getRevisionByObject($object);

		$this->client->deleteDoc($identifier, $revision);

		$this->emitRemovedObject($object);
	}

	/**
	 * Get the CouchDB revision of an object
	 *
	 * @param object $object An object
	 * @return string The current revision if it was set, NULL otherwise
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	protected function getRevisionByObject($object) {
		// TODO Get the revision from the object somehow
		$metadata = $this->collectMetadata($object);
		if ($metadata !== NULL && isset($metadata['CouchDB_Revision'])) {
			return $metadata['CouchDB_Revision'];
		}
		return NULL;
	}

	protected function storeView(\F3\CouchDB\ViewInterface $view) {
		// TODO Cache Design document
		// TODO Cache Query!!!
		try {
			$design = $this->client->getDoc('_design/' . $view->getDesignName());

			if (isset($design->views->{$view->getViewName()})) {
				return;
			}
		} catch(\CouchdbClientException $e) {
			$message = json_decode($e->getMessage(), TRUE);
			if ($message['error'] === 'not_found') {
				$design = new \stdClass();
				$design->_id = '_design/' . $view->getDesignName();
				$design->views = new \stdClass();
			}
		}

		$design->views->{$view->getViewName()} = new \stdClass();
		$design->views->{$view->getViewName()}->map = $view->getMapFunctionSource();
		if ($view->getReduceFunctionSource() !== NULL) {
			$design->views->{$view->getViewName()}->map = $view->getReduceFunctionSource();
		}

		$this->client->storeDoc($design);
	}

	/**
	 * Returns the number of records matching the query.
	 *
	 * @param \F3\FLOW3\Persistence\QueryInterface $query
	 * @return integer
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 * @todo optimize so properties are ignored and the db is asked for the count only
	 */
	public function getObjectCountByQuery(\F3\FLOW3\Persistence\QueryInterface $query) {
		return count($this->getObjectDataByQuery($query));
	}

	/**
	 * Returns the object data for the given identifier.
	 *
	 * @param string $identifier The UUID or Hash of the object
	 * @return array
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function getObjectDataByIdentifier($identifier) {
		$doc = $this->client->getDoc($identifier);
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
	 * "Execute" a view with the given arguments, these are view specific. The
	 * view will be stored in CouchDB if it is not yet defined.
	 *
	 * @param ViewInterface $view The view to execute
	 * @param array $arguments An array with arguments to the view
	 * @return array Array of object data
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function getObjectDataByView(\F3\CouchDB\ViewInterface $view, $arguments) {
		$this->storeView($view);
		$result = $this->client->getView($view->getDesignName(), $view->getViewName(), $view->getViewParameters($arguments));
		$data = array();
		if ($result !== NULL) {
			foreach ($result->rows as $row) {
				if ($row->value === NULL) {
					$data[] = $this->resultToObjectData($row->doc);
				}
			}
		}
		return $data;
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
		$objectData = json_decode(json_encode($result), TRUE);
		$objectData['metadata'] = array(
			'CouchDB_Revision' => $objectData['_rev']
		);
		foreach ($objectData['properties'] as $propertyName => $propertyData) {
			if (!$propertyData['multivalue']) {
				// Load entity
				if (isset($propertyData['value']['identifier']) && !isset($propertyData['value']['classname'])) {
					$objectData['properties'][$propertyName]['value'] = $this->getObjectDataByIdentifier($propertyData['value']['identifier']);
				}
			} else {
				for ($index = 0; $index < count($propertyData['value']); $index++) {
					// Load entity
					if (isset($propertyData['value'][$index]['value']['identifier']) && !isset($propertyData['value'][$index]['value']['classname'])) {
						$objectData['properties'][$propertyName]['value'][$index]['value'] = $this->getObjectDataByIdentifier($propertyData['value'][$index]['value']['identifier']);
					}
				}
			}
		}
		return $objectData;
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
	 * @param string $dataSourceName
	 */
	public function setDataSourceName($dataSourceName) {
		$this->dataSourceName = $dataSourceName;
	}

	/**
	 * @param string $database
	 */
	public function setDatabase($database) {
		$this->database = $database;
	}
}

?>