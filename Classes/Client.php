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
 * A CouchDB client using an HTTP connector
 *
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class Client {

	/**
	 * Regexp pattern for valid document ids
	 */
	const PATTERN_DOCUMENT_ID = '/^[0-9a-zA-Z-_]/';

	/**
	 * @var \TYPO3\CouchDB\Client\HttpConnector
	 */
	protected $connector;

	/**
	 * @var string
	 */
	protected $databaseName;

	/**
	 * @FLOW3\Inject
	 * @var \TYPO3\CouchDB\Persistence\QueryLoggerInterface
	 */
	protected $queryLogger;

	/**
	 * @var boolean
	 */
	protected $logSlowQueries = FALSE;

	/**
	 * Default slow query threshold is 0.5 seconds
	 * @var float
	 */
	protected $slowQueryThreshold = 0.5;

	/**
	 * Create a new CouchDB client
	 *
	 * Pass a path after the host and port to set a database automatically
	 * (e.g. http://127.0.0.1:5984/mydb).
	 *
	 * @param string $dataSourceName The CouchDB connection parameters as URL, e.g. http://user:pass@127.0.0.1:5984
	 * @param array $options Additional connection options for the HttpConnector
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 */
	public function __construct($dataSourceName, array $options = array()) {
		$this->dataSourceName = $dataSourceName;
		$this->options = $options;
		if (isset($this->options['logSlowQueries'])) {
			$this->logSlowQueries = (boolean)$this->options['logSlowQueries'];
			unset($this->options['logSlowQueries']);
		}
		if (isset($this->options['slowQueryThreshold'])) {
			$this->slowQueryThreshold = (float)$this->options['slowQueryThreshold'];
			unset($this->options['slowQueryThreshold']);
		}
	}

	/**
	 * @return void
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function initializeObject() {
		if (($urlParts = parse_url($this->dataSourceName)) === FALSE) {
			throw new \InvalidArgumentException('Invalid data source name: ' . $this->dataSourceName, 1287346792);
		}
		$host = isset($urlParts['host']) ? $urlParts['host'] : NULL;
		$port = isset($urlParts['port']) ? $urlParts['port'] : NULL;
		$username = isset($urlParts['user']) ? $urlParts['user'] : NULL;
		$password = isset($urlParts['pass']) ? $urlParts['pass'] : NULL;
		if (isset($urlParts['path'])) {
			$this->setDatabaseName(ltrim($urlParts['path'], '/'));
		}
		$this->connector = new \TYPO3\CouchDB\Client\HttpConnector($host, $port, $username, $password, $this->options);
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
	 * @param string $databaseName The database name or NULL to create the database set in databaseName
	 * @return boolean
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function createDatabase($databaseName = NULL) {
		if ($databaseName === NULL) {
			$databaseName = $this->getDatabaseName();
		}
		$response = $this->connector->put('/' . urlencode($databaseName));
		return $response instanceof \TYPO3\CouchDB\Client\StatusResponse && $response->isSuccess();
	}

	/**
	 * Delete a database
	 *
	 * @param string $databaseName The database name
	 * @return boolean
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function deleteDatabase($databaseName = NULL) {
		if ($databaseName === NULL) {
			$databaseName = $this->getDatabaseName();
		}
		$response = $this->connector->delete('/' . urlencode($databaseName));
		return $response instanceof \TYPO3\CouchDB\Client\StatusResponse && $response->isSuccess();
	}

	/**
	 * Get information about a database
	 *
	 * @param string $databaseName The database name
	 * @return object
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function databaseInformation($databaseName) {
		return $this->connector->get('/' . urlencode($databaseName));
	}

	/**
	 * Check if a database exists
	 *
	 * @param string $databaseName The database name
	 * @return boolean TRUE if the database exists
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function databaseExists($databaseName = NULL) {
		if ($databaseName === NULL) {
			$databaseName = $this->getDatabaseName();
		}
		try {
			$information = $this->databaseInformation($databaseName);
			return is_object($information) && $information->db_name === $databaseName;
		} catch(\TYPO3\CouchDB\Client\NotFoundException $e) {
			return FALSE;
		}
	}

	/**
	 * Get all documents in the database
	 *
	 * @param array $queryOptions Additional query options (e.g. limit or include_docs)
	 * @return object
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function listDocuments(array $queryOptions = NULL) {
		$requestOptions = $this->extractRequestOptions($queryOptions);
		return $this->connector->get('/' . urlencode($this->getDatabaseName()) . '/_all_docs', $queryOptions, NULL, $requestOptions);
	}

	/**
	 * Get a single document by id
	 *
	 * @param string id The document id
	 * @param array $queryOptions Additional query options (e.g. revs or rev)
	 * @return object
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function getDocument($id, array $queryOptions = NULL) {
		$this->checkDocumentId($id);
		$requestOptions = $this->extractRequestOptions($queryOptions);
		return $this->connector->get('/' . urlencode($this->getDatabaseName()) . '/' . $this->encodeId($id), $queryOptions, NULL, $requestOptions);
	}

	/**
	 * Get information about a document (HEAD)
	 *
	 * @param string $id
	 * @return \TYPO3\CouchDB\Client\RawResponse
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function getDocumentInformation($id) {
		$this->checkDocumentId($id);
		return $this->connector->head('/' . urlencode($this->getDatabaseName()) . '/' . $this->encodeId($id), NULL, NULL, array('raw' => TRUE));
	}

	/**
	 * Get multiple documents by id.
	 *
	 * Use include_docs => TRUE as query option to fetch the documents.
	 *
	 * @param array $ids The document ids as array
	 * @param array $queryOptions Additional query options (e.g. include_docs)
	 * @return object
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function getDocuments(array $ids, array $queryOptions = NULL) {
		$requestOptions = $this->extractRequestOptions($queryOptions);
		$ids = array_map(function($id) {
				return (string)$id;
			}, $ids);
		return $this->connector->post('/' . urlencode($this->getDatabaseName()) . '/_all_docs', $queryOptions, json_encode(array('keys' => $ids)), $requestOptions);
	}

	/**
	 * Create a document either with a specified id, or by assigning a UUID
	 * through CouchDB.
	 *
	 * @param mixed $document The document as a string, array or object
	 * @param mixed $id An optional id to use for the document
	 * @return \TYPO3\CouchDB\Client\StatusResponse The creation response
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 */
	public function createDocument($document, $id = NULL) {
		if (!is_string($document)) {
			$document = json_encode($document);
		}
		if ($id === NULL) {
			return $this->connector->post('/' . urlencode($this->getDatabaseName()), NULL, $document);
		} else {
			$this->checkDocumentId($id);
			return $this->connector->put('/' . urlencode($this->getDatabaseName()) . '/' . $this->encodeId($id), NULL, $document);
		}
	}

	/**
	 * Update a document
	 *
	 * @param mixed $document The document as a string, array or object
	 * @param string $id The document id
	 * @return \TYPO3\CouchDB\Client\StatusResponse The update response
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 */
	public function updateDocument($document, $id) {
		$this->checkDocumentId($id);
		if (!is_string($document)) {
			$document = json_encode($document);
		}
		return $this->connector->put('/' . urlencode($this->getDatabaseName()) . '/' . $this->encodeId($id), NULL, $document);
	}

	/**
	 * Update (delete) multiple documents
	 *
	 * @param array $documents The documents as array
	 * @return object
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function updateDocuments(array $documents, array $queryOptions = NULL) {
		$requestOptions = $this->extractRequestOptions($queryOptions);
		return $this->connector->post('/' . urlencode($this->getDatabaseName()) . '/_bulk_docs', $queryOptions, json_encode(array('docs' => $documents)), $requestOptions);
	}

	/**
	 * Delete a document
	 *
	 * @param string $id The document id
	 * @param string $revision The document revision
	 * @return boolean TRUE if the deletion was successful
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function deleteDocument($id, $revision) {
		$this->checkDocumentId($id);
		$response = $this->connector->delete('/' . urlencode($this->getDatabaseName()) . '/' . $this->encodeId($id), array('rev' => $revision));
		return is_object($response) && $response->ok === TRUE;
	}

	/**
	 * Query a view
	 *
	 * In addition to the default view query options (key, startkey, endkey, ...)
	 * the query parameter "keys" can be specified to do multi-key lookups.
	 *
	 * @param string $designDocumentName The design document name
	 * @param string $viewName The view name
	 * @param array $queryOptions Query options
	 * @return mixed
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function queryView($designDocumentName, $viewName, array $queryOptions = NULL) {
		$requestOptions = $this->extractRequestOptions($queryOptions);
		$path = '/' . urlencode($this->getDatabaseName()) . '/_design/' . urlencode($designDocumentName) . '/_view/' . urlencode($viewName);
		if ($this->logSlowQueries) {
			$startTime = microtime(TRUE);
		}
		if ($queryOptions === NULL || !isset($queryOptions['keys'])) {
			$result = $this->connector->get($path, $queryOptions, NULL, $requestOptions);
		} else {
			$keys = $queryOptions['keys'];
			unset($queryOptions['keys']);
			$result = $this->connector->post($path, $queryOptions, json_encode(array('keys' => $keys)), $requestOptions);
		}
		if ($this->logSlowQueries) {
			$endTime = microtime(TRUE);
			$totalTime = $endTime - $startTime;
			if ($totalTime > $this->slowQueryThreshold) {
				$this->queryLogger->log('Slow query for view: ' . $viewName, LOG_WARNING, array('time' =>  $totalTime, 'path' => $path, 'queryOptions' => $queryOptions, 'requestOptions' => $requestOptions));
			}
		}
		return $result;
	}

	/**
	 * Query a lucene index
	 *
	 * @param string $underscoredIndexName The design document name of the index
	 * @param string $indexType Type of the index to query
	 * @param array $queryOptions
	 * @return mixed
	 * @author Felix Oertel <oertel@networkteam.com>
	 */
	public function queryIndex($underscoredIndexName, $indexType, array $queryOptions = NULL) {
		$requestOptions = $this->extractRequestOptions($queryOptions);
		$path = '/_fti/local/' . urlencode($this->getDatabaseName()) . '/_design/' . urlencode($underscoredIndexName) . '/search';
		return $this->connector->get($path, $queryOptions, NULL, $requestOptions);
	}

	/**
	 * Encode a document id and preserve slashes for design documents
	 *
	 * @param string $id
	 * @return string
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	protected function encodeId($id) {
		if (strpos($id, '_design/') === 0) {
			return '_design/' . urlencode(substr($id, strlen('_design/')));
		} else {
			return urlencode($id);
		}
	}

	/**
	 * Extract request options for the connector and unset them from
	 * the provided query options
	 *
	 * @param array &$queryOptions
	 * @return array
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	protected function extractRequestOptions(array &$queryOptions = NULL) {
		if ($queryOptions === NULL) return NULL;
		$requestOptions = array();
		if (isset($queryOptions['raw'])) {
			$requestOptions['raw'] = $queryOptions['raw'];
			unset($queryOptions['raw']);
		}
		if (isset($queryOptions['decodeAssociativeArray'])) {
			$requestOptions['decodeAssociativeArray'] = $queryOptions['decodeAssociativeArray'];
			unset($queryOptions['decodeAssociativeArray']);
		}
		return $requestOptions;
	}

	/**
	 * Test the given id for a valid document id. Throws
	 * an exception if the id did not match the pattern
	 * defined in PATTERN_DOCUMENT_ID.
	 *
	 * @param string $id The document id
	 * @return void
	 */
	protected function checkDocumentId($id) {
		if (!preg_match(self::PATTERN_DOCUMENT_ID, $id)) {
			throw new \TYPO3\FLOW3\Persistence\Exception('Invalid document id: [' . $id . ']', 1317125883);
		}
	}

	/**
	 * @return \TYPO3\CouchDB\Client\HttpConnector
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function getConnector() {
		return $this->connector;
	}

	/**
	 * @return string
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function getDatabaseName() {
		if ($this->databaseName === NULL) {
			throw new \TYPO3\FLOW3\Persistence\Exception('No database name set, call setDatabaseName() or provide database in dataSourceName', 1287349160);
		}
		return $this->databaseName;
	}

	/**
	 * @param string $databaseName
	 * @return void
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function setDatabaseName($databaseName) {
		$this->databaseName = $databaseName;
	}
}

?>