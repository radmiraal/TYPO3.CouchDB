<?php
declare(ENCODING = 'utf-8');
namespace F3\FLOW3\Persistence\Backend\GenericPdo;

/*                                                                        *
 * This script belongs to the FLOW3 framework.                            *
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
	 *
	 * @var \CouchdbClient
	 */
	protected $connection;

	/**
	 * Initializes the backend
	 *
	 * @param array $options
	 * @return void
	 */
	public function initialize(array $options) {
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

		$this->client = new CouchdbClient("http://localhost:5984");
	}

	/**
	 * Fetchs the identifier for the given object, either from the declared UUID
	 * property, the injected UUID or injected hash.
	 *
	 * @param object $object
	 * @return string
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 */
	protected function getIdentifierFromObject($object) {
		$classSchema = $this->classSchemata[$object->FLOW3_AOP_Proxy_getProxyTargetClassName()];

		if ($classSchema->getUuidPropertyName() !== NULL) {
			return $object->FLOW3_AOP_Proxy_getProperty($classSchema->getUuidPropertyName());
		} elseif (property_exists($object, 'FLOW3_Persistence_Entity_UUID')) {
			return $object->FLOW3_Persistence_Entity_UUID;
		} elseif (property_exists($object, 'FLOW3_Persistence_ValueObject_Hash')) {
			return $object->FLOW3_Persistence_ValueObject_Hash;
		}
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
			if ($classSchema->getModelType() === \F3\FLOW3\Reflection\ClassSchema::MODELTYPE_VALUEOBJECT) {
				return $this->persistenceSession->getIdentifierByObject($object);
			}
			$identifier = $this->persistenceSession->getIdentifierByObject($object);
			$objectState = self::OBJECTSTATE_RECONSTITUTED;
		} elseif ($classSchema->getModelType() === \F3\FLOW3\Reflection\ClassSchema::MODELTYPE_VALUEOBJECT && property_exists($object, 'FLOW3_Persistence_ValueObject_Hash') && $this->hasValueobjectRecord($object->FLOW3_Persistence_ValueObject_Hash)) {
			return $object->FLOW3_Persistence_ValueObject_Hash;
		} else {
			$this->validateObject($object);
			$identifier = $this->createObjectDocument($object, $parentIdentifier);
			$objectState = self::OBJECTSTATE_NEW;
		}

		$this->visitedDuringPersistence[$object] = $identifier;

		$objectData = array(
			'identifier' => $identifier,
			'classname' => $classSchema->getClassName(),
			'properties' => $this->collectProperties($classSchema->getProperties(), $object, $identifier)
		);
		if (count($objectData['properties'])) {
			if ($objectState === self::OBJECTSTATE_RECONSTITUTED) {
				$this->validateObject($object);
			}
			$this->setProperties($objectData, $objectState);
		}
		if ($classSchema->getModelType() === \F3\FLOW3\Reflection\ClassSchema::MODELTYPE_ENTITY) {
			$this->persistenceSession->registerReconstitutedEntity($object, $objectData);
		}
		$this->emitPersistedObject($object, $objectState);

		return $identifier;
	}

	/**
	 *
	 * @param array $properties The properties to collect (as per class schema)
	 * @param object $object The object to work on
	 * @param string $identifier The object's identifier
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 */
	protected function collectProperties(array $properties, $object, $identifier) {
		$propertyData = array();
		foreach ($properties as $propertyName => $propertyMetaData) {
			$propertyValue = $object->FLOW3_AOP_Proxy_getProperty($propertyName);
			$propertyType = $propertyMetaData['type'];

			$this->checkType($propertyType, $propertyValue);

				// handle all objects now, because even clean ones need to be traversed
				// as dirty checking is not recursive
			if ($propertyValue instanceof \F3\FLOW3\AOP\ProxyInterface) {
				if ($this->persistenceSession->isDirty($object, $propertyName)) {
					$propertyData[$propertyName] = array(
						'type' => $propertyType,
						'multivalue' => FALSE,
						'value' => array(
							'identifier' => $this->persistObject($propertyValue, $identifier)
						)
					);
				} else {
					$this->persistObject($propertyValue, $identifier);
				}
			} elseif ($this->persistenceSession->isDirty($object, $propertyName)) {
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
}

?>