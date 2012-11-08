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

use Doctrine\ORM\Mapping as ORM;
use TYPO3\FLOW3\Annotations as FLOW3;

/**
 * A CouchDB design document to specify views
 */
class Flow3Design extends \TYPO3\CouchDB\DesignDocument {

	/**
	 * Design document name, should be the same as the design name of the query view
	 * @var string
	 */
	protected $name = 'FLOW3';

	/**
	 * Constructor
	 *
	 * @param \TYPO3\CouchDB\Client $client
	 */
	public function __construct($client) {
		$this->client = $client;
	}

	/**
	 * Declaration of entityReferences
	 *
	 * The map function recursively traverses the properties
	 * of an object document and emits all referenced identifiers.
	 * References to the document itself are not emitted so that
	 * self references work.
	 *
	 * @return array The view declaration
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	static public function entityReferencesDeclaration() {
		return array(
			'map' => '
				function(doc) {
					if (doc._deleted) return;
					function traverseProperties(properties) {
						for (var propertyName in properties) {
							if (properties[propertyName].value) {
								if (properties[propertyName].multivalue) {
									properties[propertyName].value.forEach(function(value) {
										if (value.value && value.value.properties) {
											traverseProperties(value.value.properties);
										} else if (value.value.identifier) {
											if (value.value.identifier !== doc._id) {
												emit(value.value.identifier, null);
											}
										}
									});
								} else if (properties[propertyName].value.properties) {
									traverseProperties(properties[propertyName].value.properties);
								} else if (properties[propertyName].value.identifier) {
									if (properties[propertyName].value.identifier !== doc._id) {
										emit(properties[propertyName].value.identifier, null);
									}
								}
							}
						}
					}
					if (doc.properties) {
						traverseProperties(doc.properties);
					}
				}
			',
			'reduce' => '_count'
		);
	}

	/**
	 * Get referenced document ids by an entity identifier
	 *
	 * @param string $identifier
	 * @param integer $limit Limit for safety, defaults to 10000
	 * @return array Ids of documents that reference the given identifier
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function entityReferences($identifier, $limit = 10000) {
		$result = $this->queryView('entityReferences', array(
			'key' => $identifier,
			'reduce' => FALSE,
			'decodeAssociativeArray' => TRUE,
			'limit' => $limit
		));
		return array_map(function($row) {
			return $row['id'];
		}, $result['rows']);
	}

}
?>