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
 * A CouchDB design document to specify views
 *
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class DesignDocument {

	/**
	 * @var \TYPO3\FLOW3\Reflection\ReflectionService
	 */
	private $reflectionService;

	/**
	 * Design document name, set to NULL for reflected name
	 * @var string
	 */
	protected $name = NULL;

	/**
	 * Design document language, defaults to "javascript"
	 * @var string
	 */
	protected $language = 'javascript';

	/**
	 * Client connection
	 * @var \TYPO3\CouchDB\Client
	 */
	protected $client;

	/**
	 *
	 * @param string $dataSourceName
	 * @param string $databaseName
	 */
	public function __construct($dataSourceName = '', $databaseName = '') {
		if ($dataSourceName !== '') {
			$this->client = new Client($dataSourceName);
			if ($databaseName !== '') {
				$this->client->setDatabaseName($databaseName);
			}
		}
	}

	/**
	 *
	 * @param string $viewName
	 * @param array $queryOptions
	 * @return mixed
	 */
	protected function queryView($viewName, $queryOptions = array()) {
		try {
			return $this->client->queryView($this->getDesignDocumentName(), $viewName, $queryOptions);
		} catch(\TYPO3\CouchDB\Client\NotFoundException $notFoundException) {
			$this->synchronize();
			return $this->client->queryView($this->getDesignDocumentName(), $viewName, $queryOptions);
		}
	}

	protected function reducedValue($viewName, $queryOptions = array()) {
		$queryOptions = array_merge($queryOptions, array(
			'reduce' => TRUE,
			'decodeAssociativeArray' => TRUE)
		);
		$result = $this->queryView($viewName, $queryOptions);
		if (isset($result['rows'][0]['value'])) {
			return $result['rows'][0]['value'];
		} else {
			return NULL;
		}
	}

	/**
	 * Get the design document name
	 *
	 * Either the explicit name or the inferred name from the class name.
	 *
	 * @return string
	 */
	protected function getDesignDocumentName() {
		if ($this->name !== NULL) {
			return $this->name;
		} else {
			$className = get_class($this);
			if (preg_match('/([^\\\\]*)Design$/', $className, $matches)) {
				return lcfirst($matches[1]);
			} else {
				return NULL;
			}
		}
	}

	/**
	 * @return void
	 */
	public function synchronize() {
		try {
			$designDocument = $this->client->getDocument('_design/' . $this->getDesignDocumentName(), array('decodeAssociativeArray' => TRUE));
		} catch(\TYPO3\CouchDB\Client\NotFoundException $notFoundException) {
			$information = $notFoundException->getInformation();
			if ($information['reason'] === 'no_db_file') {
				$this->client->createDatabase();
			}
			if ($information['reason'] === 'no_db_file' || $information['reason'] === 'missing') {
				$designDocument = array(
					'_id' => '_design/' . $this->getDesignDocumentName(),
					'language' => $this->language
				);
			} else {
				throw $notFoundException;
			}
		}
		$declarations = $this->getDeclarations();
		foreach ($declarations as $declaration) {
			$viewDeclaration = call_user_func(get_class($this) . '::' . $declaration . 'Declaration');
			$designDocument['views'][$declaration] = $viewDeclaration;
		}
		$this->client->createDocument($designDocument);
	}

	protected function getDeclarations() {
		$declarations = array();
		$methodNames = get_class_methods(get_class($this));
		foreach ($methodNames as $methodName) {
			if (substr($methodName, -11, 11) === 'Declaration'
				&& $this->reflectionService->isMethodStatic(get_class($this), $methodName)) {
				$declarations[] = substr($methodName, 0, -11);
			}
		}
		return $declarations;
	}

	/**
	 *
	 * @param string $propertyPath
	 * @param string $defaultValue
	 * @return string
	 */
	static protected function propertyValue($propertyPath, $defaultValue = 'null') {
		$propertyPathParts = explode('.', $propertyPath);
		$root = $propertyPathParts[0];
		$pathParts = array();
		foreach (array_slice($propertyPathParts, 1) as $propertyPathPart) {
			$checkParts[] = $root . (count($pathParts) > 0 ? '.' : '') . implode('.', $pathParts) . '.properties["' . $propertyPathPart . '"]';
			$pathParts[] = 'properties["' . $propertyPathPart . '"].value';
		}
		return  '(' . implode('&&', $checkParts) . '?' . $root . '.' . implode('.', $pathParts) . ':' . $defaultValue . ')';
	}

	/**
	 *
	 * @param string $propertyPath
	 * @param mixed $body
	 * @return string
	 */
	static protected function propertyGuard($propertyPath, $body) {
		$propertyPathParts = explode('.', $propertyPath);
		$root = $propertyPathParts[0];
		$pathParts = array();
		foreach (array_slice($propertyPathParts, 1) as $propertyPathPart) {
			$checkParts[] = $root . (count($pathParts) > 0 ? '.' : '') . implode('.', $pathParts) . '.properties["' . $propertyPathPart . '"]';
			$pathParts[] = 'properties["' . $propertyPathPart . '"].value';
		}
		return 'if(' . implode('&&', $checkParts) . '){' . self::evalBody($body) . '}';
	}

	/**
	 *
	 * Note: Subclasses have to be specified separately
	 *
	 * @param string $className
	 * @param mixed $body
	 * @return string
	 */
	static protected function mapClass($className, $body) {
		return 'function(doc){if(!doc._deleted&&doc.classname==="' . addslashes($className) . '"){' . self::evalBody($body) . '}}';
	}

	/**
	 *
	 * @param string $propertyPath
	 * @param string $asVariableName
	 * @param string $body
	 * @return string
	 */
	static protected function eachProperty($propertyPath, $asVariableName, $body) {
		return self::propertyGuard($propertyPath, self::propertyValue($propertyPath, '[]') . '.forEach(function(' . $asVariableName . 'Item){' . $asVariableName . '=' . $asVariableName . 'Item.value;' . self::evalBody($body) . '});');
	}

	/**
	 * Returns an emit call which evals the key and body
	 *
	 * @param string $keyBody
	 * @param string $valueBody
	 * @return string
	 */
	static protected function emit($keyBody, $valueBody) {
		return 'emit(' . self::evalBody($keyBody) . ',' . self::evalBody($valueBody) . ')';
	}

	/**
	 * Eval a value to a JavaScript string
	 *
	 * Accepts arrays, objects or strings. Arrays will be compiled
	 * to multiple statements, objects to JSON notation. Strings
	 * are emitted as is.
	 *
	 * @param mixed $body
	 * @return string
	 */
	static protected function evalBody($body) {
		$statements = '';
		if (is_array($body)) {
			foreach ($body as $statement) {
				$statements .= self::evalBody($statement) . ';';
			}
			return $statements;
		} if (is_object($body)) {
			$objectProperties = array();
			foreach ((array)$body as $propertyName => $propertyBody) {
				$objectProperties[] = '"' . $propertyName . '":' . self::evalBody($propertyBody);
			}
			return '{' . implode(',', $objectProperties) . '}';
		} elseif (is_string($body)) {
			return $body;
		} else {
			return 'null';
		}
	}

	/**
	 *
	 * @param \TYPO3\CouchDB\Client $client
	 * @return void
	 */
	public function setClient(\TYPO3\CouchDB\Client $client) {
		$this->client = $client;
	}

	/**
	 *
	 * @param \TYPO3\FLOW3\Reflection\ReflectionService $reflectionService
	 * @return void
	 */
	public function injectReflectionService(\TYPO3\FLOW3\Reflection\ReflectionService $reflectionService) {
		$this->reflectionService = $reflectionService;
	}

}
?>