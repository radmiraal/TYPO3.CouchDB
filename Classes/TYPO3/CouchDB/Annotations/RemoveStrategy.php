<?php
namespace TYPO3\CouchDB\Annotations;

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

use Doctrine\Common\Annotations\Annotation as DoctrineAnnotation;

/**
 * Annotation to select a specific strategy for the removal of entity documents.
 *
 * @Annotation
 * @DoctrineAnnotation\Target("CLASS")
 */
final class RemoveStrategy {

	/**
	 * Delete a document on remove (default behaviour)
	 */
	const STRATEGY_DELETE = 'DELETE';

	/**
	 * Soft delete a document by marking it as deleted, the content will stay
	 * inside the document.
	 */
	const STRATEGY_SOFT_DELETE = 'SOFT_DELETE';

	/**
	 * The document will not be deleted to reserve the document id, but
	 * the content will be removed for a deleted flag.
	 */
	const STRATEGY_KEEP_ID = 'KEEP_ID';

	/**
	 * The strategy to use when removing an entity
	 * @var string
	 */
	public $strategy;

	/**
	 * @param array $values
	 */
	public function __construct(array $values) {
		if (isset($values['value'])) {
			$this->strategy = $values['value'];
		}
	}

}
?>