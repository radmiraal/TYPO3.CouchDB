<?php
declare(ENCODING = 'utf-8');
namespace F3\CouchDB\Log\Backend;

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
 * A CouchDB log backend
 *
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 * @scope prototype
 */
class CouchDbBackend extends \F3\FLOW3\Log\Backend\AbstractBackend {

	/**
	 * @var string
	 */
	protected $dataSourceName;

	/**
	 * @var string
	 */
	protected $databaseName;

	/**
	 * @var \F3\CouchDB\Client
	 */
	protected $client;

	/**
	 * @var array
	 */
	protected $severityLabels;

	/**
	 * @var \F3\FLOW3\Object\ObjectManagerInterface
	 */
	protected $objectManager;

	/**
	 * @param \F3\FLOW3\Object\ObjectManagerInterface $objectManager
	 * @return void
	 */
	public function injectObjectManager(\F3\FLOW3\Object\ObjectManagerInterface $objectManager) {
		$this->objectManager = $objectManager;
	}

	/**
	 * Opens the CouchDB connection
	 *
	 * @return void
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 * @api
	 */
	public function open() {
		$this->severityLabels = array(
			LOG_EMERG   => 'emergency',
			LOG_ALERT   => 'alert',
			LOG_CRIT    => 'critical',
			LOG_ERR     => 'error',
			LOG_WARNING => 'warning',
			LOG_NOTICE  => 'notice',
			LOG_INFO    => 'info',
			LOG_DEBUG   => 'debug',
		);

		$this->client = $this->objectManager->create('F3\CouchDB\Client', $this->dataSourceName);
		$this->client->setDatabaseName($this->databaseName);
	}

	/**
	 * Creates a document for the given message and other information in the
	 * log database. If the database doesn't exist yet, it will be created.
	 *
	 * @param string $message The message to log
	 * @param integer $severity One of the LOG_* constants
	 * @param mixed $additionalData A variable containing more information about the event to be logged
	 * @param string $packageKey Key of the package triggering the log (determined automatically if not specified)
	 * @param string $className Name of the class triggering the log (determined automatically if not specified)
	 * @param string $methodName Name of the method triggering the log (determined automatically if not specified)
	 * @return void
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 * @api
	 */
	public function append($message, $severity = LOG_INFO, $additionalData = NULL, $packageKey = NULL, $className = NULL, $methodName = NULL) {
		if ($severity > $this->severityThreshold) {
			return;
		}
		if ($this->client === NULL) {
			return;
		}

		$document = array(
			'timestamp' => microtime(TRUE),
			'processId' => function_exists('posix_getpid') ? posix_getpid() : '',
			'ipAddress' => ($this->logIpAddress === TRUE) ? (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '') : '',
			'message' => $message,
			'severity' => $severity,
			'severityLabel' => $this->severityLabels[$severity],
			'additionalData' => $additionalData,
			'packageKey' => $packageKey,
			'className' => $className,
			'methodName' => $methodName
		);

		try {
			$this->client->createDocument($document);
		} catch(\F3\CouchDB\Client\NotFoundException $notFoundException) {
			$information = $notFoundException->getInformation();
			if ($information['reason'] === 'no_db_file') {
				$this->client->createDatabase($this->databaseName);
				$this->client->createDocument($document);
			}
		}
	}

	/**
	 * Closes the client
	 *
	 * @return void
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 * @api
	 */
	public function close() {
		$this->client = NULL;
	}

	/**
	 * Set the data source name (URL) for the CouchDB client
	 *
	 * @param string $dataSourceName
	 * @return void
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function setDataSourceName($dataSourceName) {
		$this->dataSourceName = $dataSourceName;
	}

	/**
	 * Set the database for the logger
	 *
	 * @param string $databaseName
	 * @return void
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function setDatabaseName($databaseName) {
		$this->databaseName = $databaseName;
	}
}
?>