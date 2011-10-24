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

use Doctrine\ORM\Mapping as ORM;
use TYPO3\FLOW3\Annotations as FLOW3;

/**
 * A HTTP connector for the CouchDB client
 *
 * Some code borrowed from phpillow project.
 *
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class HttpConnector {

	/**
	 * CouchDB connection options
	 *
	 * @var array
	 */
	protected $options = array(
		'host' => '127.0.0.1',
		'port' => 5984,
		'timeout' => 0.01,
		'retry' => 3,
		'keep-alive' => TRUE,
		'username' => NULL,
		'password' => NULL
	);

	/**
	 * Array containing the list of allowed HTTP methods to interact with
	 * CouchDB server.
	 *
	 * @var array
	 */
	protected $allowedMethods = array(
		'DELETE' => TRUE,
		'GET' => TRUE,
		'POST' => TRUE,
		'PUT' => TRUE,
		'HEAD' => TRUE
	);

	/**
	 * @var array
	 */
	protected $unencodedQueryParameters = array(
		'rev' => TRUE,
		'q' => TRUE
	);

	/**
	 * Connection pointer for connections
	 *
	 * @var resource
	 */
	protected $connection;

	/**
	 * Construct a couch DB connection from basic connection parameters for one
	 * given database.
	 *
	 * @param string $host
	 * @param integer $port
	 * @param string $username
	 * @param string $password
	 * @param array $options
	 */
	public function __construct($host, $port = 5984, $username = NULL, $password = NULL, array $options = array()) {
		$this->options['host'] = (string)$host;
		$this->options['port'] = (int)$port;
		$this->options['username'] = $username;
		$this->options['password'] = $password;

		$this->options = \TYPO3\FLOW3\Utility\Arrays::arrayMergeRecursiveOverrule($this->options, $options, TRUE);
	}

	/**
	 */
	public function  shutdownObject() {
		if (is_resource($this->connection)) {
			fclose($this->connection);
		}
		$this->connection = NULL;
	}

	/**
	 * HTTP method request wrapper
	 *
	 * Wraps the HTTP method requests to interact with the couch server. The
	 * supported methods are:
	 *  - GET
	 *  - DELETE
	 *  - POST
	 *  - PUT
	 *
	 * Each request takes the full request path as the first parameter and
	 * optionally data as the second parameter. The path must include the
	 * database name, if the request should operate on a specific database.
	 *
	 * The requests will return a object with the decoded server response
	 *
	 * @param string $method
	 * @param array $params
	 * @return mixed An object if the response is decoded or the response as a string if requestOptions['raw'] === TRUE
	 */
	public function __call($method, array $params) {
		$method = strtoupper($method);
		if (!isset($this->allowedMethods[$method])) {
			throw new \RuntimeException('Unsupported request method: ' . $method, 1287339276);
		}

		$path = $params[0];
		$query = isset($params[1]) ? $params[1] : NULL;
		$data = isset($params[2]) ? $params[2] : NULL;
		$requestOptions = isset($params[3]) ? $params[3] : NULL;

		return $this->request($method, $path, $query, $data, $requestOptions);
	}

	/**
	 * Check for server connection
	 *
	 * Checks if the connection already has been established, or tries to
	 * establish the connection, if not done yet.
	 *
	 * @return void
	 */
	protected function checkConnection() {
			// If the connection could not be established, fsockopen sadly does not
			// only return false (as documented), but also always issues a warning.
		if (($this->connection === NULL)
				&& (($this->connection = fsockopen($this->options['host'], $this->options['port'], $errno, $errstr)) === FALSE)) {
			$this->connection = NULL;
			throw new \RuntimeException('Could not connect to server at ' . $this->options['host'] . ':' . $this->options['port'] . ' ' . $errno . ': "' . $errstr . '"', 1287339586);
		}
	}

	/**
	 * Build a HTTP 1.1 request
	 *
	 * Build the HTTP 1.1 request headers from the given input.
	 *
	 * @param string $method
	 * @param string $path
	 * @param array $query
	 * @param string $data
	 * @return string
	 */
	protected function buildRequest($method, $path, $query, $data) {
		if ($query !== NULL && count($query) > 0) {
			if (strpos($path, '?') === FALSE) {
				$path .= '?';
			} else {
				$path .= '&';
			}
			foreach ($query as $parameter => $value) {
				if (!isset($this->unencodedQueryParameters[$parameter])) {
					$query[$parameter] = json_encode($value);
				}
			}
			$path .= http_build_query($query);
		}

			// Create basic request headers
		$request = $method . ' ' . $path . " HTTP/1.1\r\n";
		$request .= "Host: " . $this->options['host'] . ":" . $this->options['port'] . "\r\n";

			// Add basic auth if set
		if ($this->options['username']) {
			$request .= sprintf("Authorization: Basic %s\r\n",
				base64_encode($this->options['username'] . ':' . $this->options['password'])
			);
		}

			// Set keep-alive header, which helps to keep to connection
			// initilization costs low, especially when the database server is not
			// available in the locale net.
		$request .= 'Connection: ' . ($this->options['keep-alive'] ? 'Keep-Alive' : 'Close') . "\r\n";

			// Also add headers and request body if data should be sent to the
			// server. Otherwise just add the closing mark for the header section
			// of the request.
		if ($data !== NULL) {
			$request .= "Content-type: application/json\r\n";
			$request .= "Content-Length: " . strlen($data) . "\r\n\r\n";
			$request .= $data;
		} else {
			$request .= "\r\n";
		}

		return $request;
	}

	/**
	 * Perform a request to the server and return the result
	 *
	 * Perform a request to the server and return the result converted as
	 * decoded JSON or a response object. If you do not expect a JSON structure,
	 * which could be converted in such a response object, set the raw request
	 * option to true, and you get a response object returned, containing
	 * the raw body.
	 *
	 * @param string $method
	 * @param string $path
	 * @param array $query
	 * @param string $data
	 * @param array $requestOptions
	 * @param integer $reconnectAttempt
	 * @return mixed
	 */
	protected function request($method, $path, array $query = NULL, $data = NULL, $requestOptions = NULL, $reconnectAttempt = 0) {
		$returnRawResponse = $requestOptions !== NULL && isset($requestOptions['raw']) && $requestOptions['raw'] === TRUE;
		$decodeAssociativeArray = $requestOptions !== NULL && isset($requestOptions['decodeAssociativeArray']) && $requestOptions['decodeAssociativeArray'] === TRUE;

		if ($reconnectAttempt > $this->options['retry']) {
			throw new \RuntimeException('Too many connection retries', 1287341261);
		}

		$this->checkConnection();

		$request = $this->buildRequest($method, $path, $query, $data);
		if (fwrite($this->connection, $request) === FALSE) {
			fclose($this->connection);
			$this->connection = NULL;
			return $this->request($method, $path, $query, $data, $requestOptions, $reconnectAttempt + 1);
		}

			// Read server response headers
		$rawHeaders = '';
		$headers = array(
			'connection' => ($this->options['keep-alive'] ? 'Keep-Alive' : 'Close'),
		);

			// Remove leading newlines, should not accur at all, actually.
		while (($line = fgets($this->connection)) !== FALSE && ($lineContent = rtrim($line)) === '');

			// Thow exception, if connection has been aborted by the server, and
			// leave handling to the user for now.
		if ($line === FALSE) {
				// Reestablish which seems to have been aborted
				//
				// An aborted connection seems to happen here on long running
				// requests, which cause a connection timeout at server side.
			fclose($this->connection);
			$this->connection = NULL;
			return $this->request($method, $path, $query, $data, $requestOptions, $reconnectAttempt + 1);
		}

		do {
				// Also store raw headers for later logging
			$rawHeaders .= $lineContent . "\n";

			// Extract header values
			if (preg_match('(^HTTP/(?P<version>\d+\.\d+)\s+(?P<status>\d+))S', $lineContent, $match)) {
				$headers['version'] = $match['version'];
				$headers['status'] = (int)$match['status'];
			} else {
				list($key, $value) = explode(':', $lineContent, 2);
				$headers[strtolower( $key )] = ltrim($value);
			}
		} while ((($line = fgets($this->connection)) !== FALSE) && (($lineContent = rtrim($line)) !== ''));

			// Read response body
		$body = '';
		if ($method !== 'HEAD') {
			if (!isset($headers['transfer-encoding']) || ($headers['transfer-encoding'] !== 'chunked')) {
					// HTTP 1.1 supports chunked transfer encoding, if the according
					// header is not set, just read the specified amount of bytes.
				$bytesToRead = (int)(isset($headers['content-length']) ? $headers['content-length'] : 0);

					// Read body only as specified by chunk sizes, everything else
					// are just footnotes, which are not relevant for us.
				while ($bytesToRead > 0) {
					$body .= $read = fgets($this->connection, $bytesToRead + 1);
					$bytesToRead -= strlen($read);
				}
			} else {
					// When transfer-encoding=chunked has been specified in the
					// response headers, read all chunks and sum them up to the body,
					// until the server has finished. Ignore all additional HTTP
					// options after that.
				do {
					$line = rtrim(fgets($this->connection));

						// Get bytes to read, with option appending comment
					if (preg_match('(^([0-9a-f]+)(?:;.*)?$)', $line, $match)) {
						$bytesToRead = hexdec($match[1]);

							// Read body only as specified by chunk sizes, everything else
							// are just footnotes, which are not relevant for us.
							//
							// Read 2 more bytes, since chunks end with CRLF
						$bytesLeft = $bytesToRead + 2;
						$chunk = '';
						while ($bytesLeft > 0) {
							$currentChunk = fread($this->connection, $bytesLeft);
							$bytesLeft -= strlen($currentChunk);
							$chunk .= $currentChunk;
						}
						$body .= substr($chunk, 0, -2);
					}
				} while ($bytesToRead > 0);
			}
		}

			// Reset the connection if the server asks for it.
		if ($headers['connection'] !== 'Keep-Alive') {
			fclose($this->connection);
			$this->connection = NULL;
		}

			// Handle some response state as special cases
		switch ($headers['status']) {
			case 301:
			case 302:
			case 303:
			case 307:
				$path = parse_url($headers['location'], PHP_URL_PATH);
				return $this->request('GET', $path, $query, $data, $requestOptions);
		}

		switch ($headers['status']) {
			case 200:
					// The HTTP status code 200 - OK indicates, that we got a document
					// or a set of documents as return value.
					//
					// To check wheather we received a set of documents or a single
					// document we can check for the document properties _id or
					// _rev, which are always available for documents and are only
					// available for documents.
				if (!$returnRawResponse) {
					$result = json_decode($body, $decodeAssociativeArray);
					if ($result === NULL && json_last_error() !== JSON_ERROR_NONE) {
						throw new \TYPO3\CouchDB\Client\InvalidResultException('Invalid document (JSON error code ' . json_last_error() . ')', 1317037277);
					}
					return $result;
				} else {
					return new \TYPO3\CouchDB\Client\RawResponse($headers, $body);
				}
			case 201:
			case 202:
					// The following status codes are given for status responses
					// depending on the request type - which does not matter here any
					// more.
				return new \TYPO3\CouchDB\Client\StatusResponse($body);
			case 404:
					// The 404 and 409 (412) errors are using custom exceptions
					// extending the base error exception, because they are often
					// required to be handled in a special way by the application.
				throw new \TYPO3\CouchDB\Client\NotFoundException($body, 1287395956);
			case 409: // Conflict
			case 412: // Precondition Failed - we just consider this as a conflict.
				throw new \TYPO3\CouchDB\Client\ConflictException($body, 1287395905);
			case 400:
				throw new \TYPO3\CouchDB\Client\ClientException($body, 1301496145);
			default:
					// All other unhandled HTTP codes are for now handled as an error.
					// This may not be true, as lots of other status code may be used
					// for valid repsonses.
				throw new \RuntimeException('Unknown response status: ' . $headers['status'], 1287343089);
		}
	}
}

?>