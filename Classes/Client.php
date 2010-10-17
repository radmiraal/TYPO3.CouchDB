<?php
declare(ENCODING = 'utf-8');
namespace F3\CouchDB;

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
 * A CouchDB client
 *
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class Client {
	/**
	 *
	 * @var \F3\CouchDB\Client\HttpConnector
	 */
	protected $connector;

	/**
	 * @var string
	 */
	protected $database;

	public function __construct($dataSourceName, $options = array()) {
		if (($urlParts = @parse_url($dataSourceName)) === FALSE) {
			throw new \F3\FLOW3\Exception('Invalid data source name: ' . $dataSourceName, 1287346792);
		}
		$host = isset($urlParts['host']) ? $urlParts['host'] : NULL;
		$port = isset($urlParts['port']) ? $urlParts['port'] : NULL;
		$username = isset($urlParts['user']) ? $urlParts['user'] : NULL;
		$password = isset($urlParts['pass']) ? $urlParts['pass'] : NULL;
		$this->connector = new \F3\CouchDB\Client\HttpConnector($host, $port, $username, $password, $options);
	}

	/**
	 * List all databases
	 * @return array
	 */
	public function listDatabases() {
		return $this->connector->get('/_all_dbs');
	}

	/**
	 * @param string $database
	 * @return boolean
	 */
	public function createDatabase($database) {
		$response = $this->connector->put('/' . urlencode($database));
		return $response instanceof \F3\CouchDB\Client\StatusResponse && $response->isSuccess();
	}

	/**
	 * @param string $database
	 * @return boolean
	 */
	public function deleteDatabase($database) {
		$response = $this->connector->delete('/' . urlencode($database));
		return $response instanceof \F3\CouchDB\Client\StatusResponse && $response->isSuccess();
	}

	/**
	 * @param string $database
	 * @return object
	 */
	public function databaseInformation($database) {
		return $this->connector->get('/' . urlencode($database));
	}

	/**
	 * @param array $query
	 */
	public function listDocuments($query = NULL) {
		return $this->connector->get('/' . urlencode($this->getDatabase()) . '/_all_docs', $query);
	}

	/**
	 * @param string id
	 * @param array $query
	 */
	public function getDocument($id, $query = NULL) {
		return $this->connector->get('/' . urlencode($this->getDatabase()) . '/' . $this->getEncodedId($id), $query);
	}

	protected function getEncodedId($id) {
		if (strpos($id, '_design/') === 0) {
			return '_design/' . urlencode(substr($id, strlen('_design/')));
		} else {
			return urlencode($id);
		}
	}

	/**
	 * @param array $keys
	 * @param array $query
	 */
	public function getDocuments($keys, $query = NULL) {
		return $this->connector->post('/' . urlencode($this->getDatabase()) . '/_all_docs', $query, json_encode(array('keys' => $keys)));
	}

	/**
	 *
	 * @param mixed $idOrDocument Either the document id or the document itself as an array
	 * @param array $documentOrNull The document or nothing if no id was given
	 */
	public function createDocument($idOrDocument, $documentOrNull = NULL) {
		if (is_string($idOrDocument)) {
			if (!is_string($documentOrNull)) {
				$document = json_encode($documentOrNull);
			}
			return $this->connector->put('/' . urlencode($this->getDatabase()) . '/' . $this->getEncodedId($idOrDocument), NULL, $document);
		} else {
			if (!is_string($idOrDocument)) {
				$document = json_encode($idOrDocument);
			}
			return $this->connector->post('/' . urlencode($this->getDatabase()), NULL, $document);
		}
	}

	/**
	 *
	 * @param string $idEither The document id
	 * @param array $document The document
	 */
	public function updateDocument($id, $document) {
		if (!is_string($document)) {
			$document = json_encode($document);
		}
		return $this->connector->put('/' . urlencode($this->getDatabase()) . '/' . $this->getEncodedId($id), NULL, $document);
	}

	/**
	 *
	 * @param string $id
	 * @param string $revision
	 */
	public function deleteDocument($id, $revision) {
		$response = $this->connector->delete('/' . urlencode($this->getDatabase()) . '/' . $this->getEncodedId($id), array('rev' => $revision));
		return is_object($response) && $response->ok === TRUE;
	}

	/**
	 *
	 * @param string $design
	 * @param string $view
	 * @param array $query
	 */
	public function queryView($design, $view, $query = NULL) {
		$path = '/' . urlencode($this->getDatabase()) . '/_design/' . urlencode($design) . '/_view/' . urlencode($view);
		if ($query === NULL || !isset($query['keys'])) {
			return $this->connector->get($path, $query);
		} else {
			$keys = $query['keys'];
			unset($query['keys']);
			return $this->connector->post($path, $query, json_encode(array('keys' => $keys)));
		}
	}

	/**
	 * @return \F3\CouchDB\Client\HttpConnector
	 */
	public function getConnector() {
		return $this->connector;
	}

	/**
	 * @param string $database
	 */
	public function setDatabase($database) {
		$this->database = $database;
	}

	/**
	 * @return string
	 */
	public function getDatabase() {
		if ($this->database === NULL) {
			throw new \F3\FLOW3\Exception('No database set', 1287349160);
		}
		return $this->database;
	}
}

?>