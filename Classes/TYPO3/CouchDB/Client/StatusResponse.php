<?php
namespace TYPO3\CouchDB\Client;

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
 * A status response object
 *
 * Some code borrowed from phpillow project.
 *
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class StatusResponse {

	/**
	 * @var array
	 */
	protected $data;

	/**
	 * @param string $body JSON-encoded data
	 */
	public function __construct($body) {
		$this->data = json_decode($body, TRUE);
	}

	/**
	 * @return array|mixed
	 */
	public function getData() {
		return $this->data;
	}

	/**
	 * @return bool
	 */
	public function isSuccess() {
		return isset($this->data['ok']) && $this->data['ok'] === TRUE;
	}

	/**
	 * @return string|mixed
	 */
	public function getId() {
		return isset($this->data['id']) ? $this->data['id'] : NULL;
	}

	/**
	 * @return string|mixed
	 */
	public function getRevision() {
		return isset($this->data['rev']) ? $this->data['rev'] : NULL;
	}
}

?>