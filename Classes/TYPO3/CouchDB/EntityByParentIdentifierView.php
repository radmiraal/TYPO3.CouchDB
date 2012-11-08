<?php
namespace TYPO3\CouchDB;

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
 * A static CouchDB view for entities by parent identifer
 *
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class EntityByParentIdentifierView implements \TYPO3\CouchDB\ViewInterface {

	/**
	 * Get the design name where the view is defined. This is FLOW3 as a
	 * default for this view.
	 *
	 * @return string
	 */
	public function getDesignName() {
		return 'FLOW3';
	}

	/**
	 * Get the name of the view, this identifies the view and has to be
	 * unique inside one design document.
	 *
	 * @return string
	 */
	public function getViewName() {
		return 'entityByParentIdentifier';
	}

	/**
	 * Get CouchDB query parameters by arguments
	 *
	 * @param array $arguments
	 * @return array CouchDB view query parameters
	 */
	public function buildViewParameters(array $arguments) {
		if (isset($arguments['parentIdentifier'])) {
			$parentIdentifier = $arguments['parentIdentifier'];

			$parameters = array(
				'key' => $parentIdentifier
			);
			return $parameters;
		} else {
			throw new \Exception('EntityByParentIdentifier view needs parentIdentifier argument', 1286893991);
		}
	}

	/**
	 * Get the map function for the view as JavaScript source
	 * @return string The function source code or null if no map function defined
	 */
	public function getMapFunctionSource() {
		return 'function(doc) { if (doc.parentIdentifier) { emit(doc.parentIdentifier, null); } }';
	}

	/**
	 * Get the reduce function for the view as JavaScript source
	 * @return string The function source code or null if no map function defined
	 */
	public function getReduceFunctionSource() {
		return NULL;
	}
}

?>