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
 * A raw response object
 *
 * Some code borrowed from phpillow project.
 *
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class RawResponse {

	/**
	 * @var string
	 */
	protected $contentType;

	/**
	 * @var string
	 */
	protected $data;

	/**
	 * @var string
	 */
	protected $revision;

	/**
	 * @param array $headers
	 * @param string $body
	 */
	public function __construct(array $headers, $body) {
		$this->contentType = $headers['content-type'];
		if (isset($headers['etag'])) {
			$this->revision = trim($headers['etag'], '"');
		}
		$this->data = $body;
	}

	/**
	 * @return string
	 */
	public function getContentType() {
		return $this->contentType;
	}

	/**
	 * @return string
	 */
	public function getData() {
		return $this->data;
	}

	/**
	 * Get the revision / etag of the response (if sent)
	 * @return string
	 */
	public function getRevision() {
		return $this->revision;
	}

}
?>