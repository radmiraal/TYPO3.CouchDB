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

/**
 * An invalid result exception: CouchDB returned an result, but it contained
 * not the expected information.
 *
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class InvalidResultException extends \TYPO3\FLOW3\Exception {

	/**
	 * @var object
	 */
	protected $result;

	/**
	 * @param string $message
	 * @param int $code
	 * @param \Exception $previous
	 * @param object $result CouchDB result
	 */
	public function __construct($message, $code = 0, \Exception $previous = NULL, $result = NULL) {
		$this->result = $result;
		parent::__construct($message, $code, $previous);
	}

	/**
	 * @return object
	 */
	public function getResult() {
		return $this->result;
	}
}

?>