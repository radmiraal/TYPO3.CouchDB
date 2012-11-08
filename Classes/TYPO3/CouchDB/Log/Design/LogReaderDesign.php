<?php
namespace TYPO3\CouchDB\Log\Design;

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

use TYPO3\FLOW3\Annotations as FLOW3;

/**
 * A CouchDB log reader design
 */
class LogReaderDesign extends \TYPO3\CouchDB\DesignDocument {

	/**
	 * @return array
	 */
	static public function greaterEqualDebugDeclaration() {
		return self::getDeclarationForSeverity(LOG_DEBUG);
	}

	/**
	 * @return array
	 */
	static public function greaterEqualInfoDeclaration() {
		return self::getDeclarationForSeverity(LOG_INFO);
	}

	/**
	 * @return array
	 */
	static public function greaterEqualNoticeDeclaration() {
		return self::getDeclarationForSeverity(LOG_NOTICE);
	}

	/**
	 * @return array
	 */
	static public function greaterEqualWarningDeclaration() {
		return self::getDeclarationForSeverity(LOG_WARNING);
	}

	/**
	 * @return array
	 */
	static public function greaterEqualErrorDeclaration() {
		return self::getDeclarationForSeverity(LOG_ERR);
	}

	/**
	 * @return array
	 */
	static public function greaterEqualCritDeclaration() {
		return self::getDeclarationForSeverity(LOG_CRIT);
	}

	/**
	 * @return array
	 */
	static public function greaterEqualAlertDeclaration() {
		return self::getDeclarationForSeverity(LOG_ALERT);
	}

	/**
	 * @return array
	 */
	static public function greaterEqualEmergDeclaration() {
		return self::getDeclarationForSeverity(LOG_EMERG);
	}

	/**
	 *
	 * @param integer $severity
	 * @return array
	 */
	static protected function getDeclarationForSeverity($severity) {
		return array(
			'map' => 'function(doc){if(doc.severity<=' . $severity . '){emit(doc.timestamp,null);}}',
			'reduce' => '_count'
		);
	}

	/**
	 * Read messages from the log, filtered by constraints
	 *
	 * Initializes the database or view lazily.
	 *
	 * @param integer $offset
	 * @param integer $limit
	 * @param string $severityThreshold
	 * @param integer $fromTimestamp
	 * @param integer $toTimestamp
	 * @return array Log entries as array
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function read($offset = 0, $limit = 100, $severityThreshold = 'debug', $fromTimestamp = NULL, $toTimestamp = NULL) {
		$viewName = 'greaterEqual' . ucfirst($severityThreshold);
		$queryOptions = array('reduce' => FALSE, 'include_docs' => TRUE, 'descending' => TRUE, 'skip' => $offset, 'limit' => $limit, 'decodeAssociativeArray' => TRUE);
		if ($fromTimestamp !== NULL) {
			$queryOptions['endkey'] = intval($fromTimestamp);
		}
		if ($toTimestamp !== NULL) {
			$queryOptions['startkey'] = intval($toTimestamp);
		}
		$result = $this->queryView($viewName, $queryOptions);
		return array_map(function($row) { return $row['doc']; }, $result['rows']);
	}

	/**
	 * Count log entries, filtered by constraints
	 *
	 * @param string $severityThreshold
	 * @param integer $fromTimestamp
	 * @param integer $toTimestamp
	 * @return integer Count
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function count($severityThreshold = 'debug', $fromTimestamp = NULL, $toTimestamp = NULL) {
		$viewName = 'greaterEqual' . ucfirst($severityThreshold);
		$queryOptions = array('reduce' => TRUE, 'descending' => TRUE);
		if ($fromTimestamp !== NULL) {
			$queryOptions['endkey'] = intval($fromTimestamp);
		}
		if ($toTimestamp !== NULL) {
			$queryOptions['startkey'] = intval($toTimestamp);
		}
		return $this->reducedValue($viewName, $queryOptions);
	}

	/**
	 * @param string $designName The design document name
	 */
	public function setDesignName($designName) {
		$this->name = $designName;
	}

}
?>