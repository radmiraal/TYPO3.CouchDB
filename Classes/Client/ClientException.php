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
 * A CouchDB client exception
 *
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class ClientException extends \TYPO3\FLOW3\Persistence\Exception {

	/**
	 * @var array
	 */
	protected $information;

	/**
	 * @var string
	 */
	protected $message = 'CouchDB client exception';

	/**
	 * @param string $body JSON-encoded data
	 * @param int $code
	 * @param \Exception $previous
	 */
	public function __construct($body, $code = 0, \Exception $previous = NULL) {
		$this->information = json_decode($body, TRUE);
		parent::__construct(isset($this->information['reason']) ? $this->information['reason'] : $this->message, $code, $previous);
	}

	/**
	 * @return array
	 */
	public function getInformation() {
		return $this->information;
	}
}

?>