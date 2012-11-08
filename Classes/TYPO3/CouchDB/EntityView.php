<?php
namespace TYPO3\CouchDB;

/*                                                                        *
 * This script belongs to the Flow package "CouchDB".                     *
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
use TYPO3\Flow\Annotations as Flow;

/**
 * A CouchDB view interface
 *
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class EntityView implements \TYPO3\CouchDB\ViewInterface {

	/**
	 * TODO Make configurable
	 *
	 * @return string
	 */
	public function getDesignName() {
		return 'Flow';
	}

	/**
	 * Get the name of the view, this identifies the view and has to be
	 * unique inside one design document.
	 *
	 * @return string
	 */
	public function getViewName() {
		return "entitiesByClassname";
	}

	/**
	 * Get CouchDB query parameters by arguments
	 * @param array $arguments
	 * @return array CouchDB view query parameters
	 */
	public function buildViewParameters(array $arguments) {
		return array(
			'startkey' => array($arguments['classname']),
			'endkey' => array($arguments['classname'], '')
		);
	}

	/**
	 * Get the map function for the view as JavaScript source
	 * @return string The function source code or null if no map function defined
	 */
	public function getMapFunctionSource() {
		return "function(doc) { emit([doc.classname, doc._id]); }";
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