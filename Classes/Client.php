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
 *
 * @scope prototype
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

	/**
	 * Create a new CouchDB client
	 *
	 * @param string $dataSourceName The CouchDB connection parameters as URL, e.g. http://user:pass@127.0.0.1:5984
	 * @param array $options Additional connection options for the HttpConnector
	 * @return \F3\CouchDB\Client
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
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
	 *
	 * @return array
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function listDatabases() {
		return $this->connector->get('/_all_dbs');
	}

	/**
	 * Create a database
	 *
	 * @param string $database
	 * @return boolean
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function createDatabase($database) {
		$response = $this->connector->put('/' . urlencode($database));
		return $response instanceof \F3\CouchDB\Client\StatusResponse && $response->isSuccess();
	}

	/**
	 * Delete a database
	 *
	 * @param string $database
	 * @return boolean
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function deleteDatabase($database) {
		$response = $this->connector->delete('/' . urlencode($database));
		return $response instanceof \F3\CouchDB\Client\StatusResponse && $response->isSuccess();
	}

	/**
	 * Get information about a database
	 *
	 * @param string $database The database name
	 * @return object
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function databaseInformation($database) {
		return $this->connector->get('/' . urlencode($database));
	}

	/**
	 * Get all documents in the database
	 *
	 * @param array $query Additional query options (e.g. limit or include_docs)
	 * @return object
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function listDocuments($query = NULL) {
		return $this->connector->get('/' . urlencode($this->getDatabase()) . '/_all_docs', $query);
	}

	/**
	 * Get a single document by id
	 *
	 * @param string id The document id
	 * @param array $query Additional query options (e.g. revs or rev)
	 * @return object
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function getDocument($id, $query = NULL) {
		return $this->connector->get('/' . urlencode($this->getDatabase()) . '/' . $this->getEncodedId($id), $query);
	}

	/**
	 * Get multiple documents by id.
	 *
	 * Use include_docs => TRUE as query option to fetch the documents.
	 *
	 * @param array $ids The document ids as array
	 * @param array $query Additional query options (e.g. include_docs)
	 * @return object
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function getDocuments($ids, $query = NULL) {
		return $this->connector->post('/' . urlencode($this->getDatabase()) . '/_all_docs', $query, json_encode(array('keys' => $ids)));
	}

	/**
	 * Create a document either with a specified id, or by assigning a UUID
	 * through CouchDB.
	 *
	 * @param mixed $idOrDocument Either the document id or the document itself as an string, array or object
	 * @param array $documentOrNull The document or nothing if no id was given
	 * @return \F3\CouchDB\Client\StatusResponse The creation response
	 * @author Christopher Hlubek <hlubek@networkteam.com>
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
	 * Update a document
	 *
	 * @param string $id The document id
	 * @param mixed $document The document data as string or array / object
	 * @return \F3\CouchDB\Client\StatusResponse The update response
	 */
	public function updateDocument($id, $document) {
		if (!is_string($document)) {
			$document = json_encode($document);
		}
		return $this->connector->put('/' . urlencode($this->getDatabase()) . '/' . $this->getEncodedId($id), NULL, $document);
	}

	/**
	 * Delete a document
	 *
	 * @param string $id The document id
	 * @param string $revision The document revision
	 * @return boolean TRUE if the deletion was successful
	 */
	public function deleteDocument($id, $revision) {
		$response = $this->connector->delete('/' . urlencode($this->getDatabase()) . '/' . $this->getEncodedId($id), array('rev' => $revision));
		return is_object($response) && $response->ok === TRUE;
	}

	/**
	 * Query a view
	 * 
	 * In addition to the default view query options (key, startkey, endkey, ...)
	 * the query parameter "keys" can be specified to do multi-key lookups.
	 *
	 * @param string $design The design document name
	 * @param string $view The view name
	 * @param array $query Query options
	 * @return mixed
	 * @author Christopher Hlubek <hlubek@networkteam.com>
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
	 * Encode a document id and preserve slashes for design documents
	 *
	 * @param string $id
	 * @return string
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	protected function getEncodedId($id) {
		if (strpos($id, '_design/') === 0) {
			return '_design/' . urlencode(substr($id, strlen('_design/')));
		} else {
			return urlencode($id);
		}
	}

	/**
	 * @return \F3\CouchDB\Client\HttpConnector
	 */
	public function getConnector() {
		return $this->connector;
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

	/**
	 * @param string $database
	 */
	public function setDatabase($database) {
		$this->database = $database;
	}
}

?>