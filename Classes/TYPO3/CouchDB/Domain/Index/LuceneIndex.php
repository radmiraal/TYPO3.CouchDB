<?php
namespace TYPO3\CouchDB\Domain\Index;

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
 * A CouchDB Lucene index for advanced queries
 * @see https://github.com/rnewson/couchdb-lucene
 *
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 * @FLOW3\Scope("singleton")
 */
abstract class LuceneIndex {

	/**
	 * @var string Index name (for multiple indices per entity)
	 */
	protected $indexName;

	/**
	 * @var string Class name of the entity being indexed
	 */
	protected $type;

	/**
	 * @var array
	 */
	protected $indexStatements = array();

	/**
	 * @var boolean
	 */
	protected $configured = FALSE;

	/**
	 * @FLOW3\Inject
	 * @var \TYPO3\CouchDB\Persistence\LuceneQueryFactory
	 */
	protected $luceneQueryFactory;

	/**
	 * Configure the index for an entity
	 *
	 * @param string $className
	 * @return \TYPO3\CouchDB\Domain\Index\LuceneIndex This object for building the configuration
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	protected function forEntity($className) {
		$this->indexName = 'lucene_' . str_replace('\\', '_', $className);
		$this->type = $className;
		return $this;
	}

	/**
	 * Configure the index to index a property
	 *
	 * @param string $propertyName The property name, accepts also nested properties (e.g. "category.name") for single-valued properties
	 * @param array $options Indexing options
	 * @return \TYPO3\CouchDB\Domain\Index\LuceneIndex This object for building the configuration
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	protected function indexProperty($propertyName, $options = array()) {
		$checkParts = array();
		$pathParts = array();
		$indexStatement = '';

		$propertyPathParts = explode('.', $propertyName);
		foreach ($propertyPathParts as $propertyPathPart) {
			$pathParts[] = 'properties.' . $propertyPathPart . '.value';
			$checkParts[] = 'doc.' . implode('.', $pathParts);
		}
		$checkParts = array_slice($checkParts, 0, -1);

		if (count($checkParts) > 0) {
			$indexStatement .= 'if(' . implode('&&', $checkParts) . ')';
		}

		$options['field'] = str_replace('.', '__', $propertyName);
		$indexStatement .= 'result.add(doc.' . implode('.', $pathParts) . ', ' . json_encode($options) . ');';

		$this->indexStatements[] = $indexStatement;
		return $this;
	}

	/**
	 * @return void
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	protected function ensureConfigured() {
		if (!$this->configured) {
			$this->configure();
			$this->configured = TRUE;
		}
	}

	/**
	 *
	 * @param \TYPO3\FLOW3\Persistence\Generic\Qom\Constraint $constraint
	 * @return mixed
	 * @author Felix Oertel <oertel@networkteam.com>
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	protected function buildStatementForConstraint(\TYPO3\FLOW3\Persistence\Generic\Qom\Constraint $constraint) {
		if ($constraint instanceof \TYPO3\FLOW3\Persistence\Generic\Qom\Comparison) {
			if ($constraint->getOperator() === \TYPO3\FLOW3\Persistence\QueryInterface::OPERATOR_LIKE ||
				$constraint->getOperator() === \TYPO3\FLOW3\Persistence\QueryInterface::OPERATOR_EQUAL_TO) {
				$operandValue = $this->buildKeyForOperand($constraint->getOperand2());
				if (strpos($operandValue, ' ') !== FALSE) {
					$operandValue = $this->phrase($operandValue);
				} else {
					$allowWildcard = ($constraint->getOperator() === \TYPO3\FLOW3\Persistence\QueryInterface::OPERATOR_LIKE);
					$operandValue = $this->escape($operandValue, $allowWildcard);
				}
				return $this->buildNameForOperand($constraint->getOperand1()) . ':' . $operandValue;
			} else {
				throw new \InvalidArgumentException('Comparison operator ' . get_class($constraint->getOperator()) . ' is not supported by CouchDB QueryIndex', 1300895208);
			}
		} elseif ($constraint instanceof \TYPO3\FLOW3\Persistence\Generic\Qom\LogicalAnd) {
			return '(' . $this->buildStatementForConstraint($constraint->getConstraint1()) . ' AND ' . $this->buildStatementForConstraint($constraint->getConstraint2()) . ')';
		} elseif ($constraint instanceof \TYPO3\FLOW3\Persistence\Generic\Qom\LogicalOr) {
			return '(' . $this->buildStatementForConstraint($constraint->getConstraint1()) . ' OR ' . $this->buildStatementForConstraint($constraint->getConstraint2()) . ')';
		} elseif ($constraint instanceof \TYPO3\FLOW3\Persistence\Generic\Qom\LogicalNot) {
			return '(NOT ' . $this->buildStatementForConstraint($constraint->getConstraint()) . ')';
		} else {
			throw new \InvalidArgumentException('Constraint ' . get_class($constraint) . ' is not supported by CouchDB QueryIndex', 1299689061);
		}
		return NULL;
	}

	/**
	 *
	 * @param \TYPO3\FLOW3\Persistence\Generic\Qom\PropertyValue $operand
	 * @return string
	 */
	protected function buildNameForOperand($operand) {
		if ($operand instanceof \TYPO3\FLOW3\Persistence\Generic\Qom\PropertyValue) {
			return str_replace('.', '__', $operand->getPropertyName());
		} else {
			throw new \InvalidArgumentException('Operand ' . get_class($operand) . ' has to be of type PropertyValue.', 1299690265);
		}
	}

	/**
	 *
	 * @param mixed $operand The operand as string or object
	 */
	protected function buildKeyForOperand($operand) {
		if (is_string($operand)) {
			return $operand;
		} else {
			throw new \InvalidArgumentException('Non-string operand value of type ' . get_class($operand) . ' is not supported by CouchDB QueryIndex', 1299689062);
		}
	}

	/**
	 * @return string
	 */
	public function getIndexName() {
		$this->ensureConfigured();
		return $this->indexName;
	}

	/**
	 * Get type of the index. "fulltext" with lucene.
	 *
	 * @return string
	 */
	public function getIndexType() {
		return 'fulltext';
	}

	/**
	 * Get CouchDB query parameters by arguments
	 *
	 * @param array $arguments
	 * @return array CouchDB view query parameters
	 * @author Felix Oertel <oertel@networkteam.com>
	 * @todo Move query statement out of the parameters
	 */
	public function buildIndexParameters(array $arguments) {
		if (isset($arguments['query']) && $arguments['query'] instanceof \TYPO3\FLOW3\Persistence\QueryInterface) {
			$query = $arguments['query'];

			if (isset($arguments['count']) && $arguments['count'] === TRUE) {
				$parameters = array();
			} else {
				$parameters = array(
					'include_docs' => TRUE
				);
			}

			if ($query->getLimit() !== NULL) {
				$parameters['limit'] = $query->getLimit();
			}
			if ($query->getOffset() !== NULL) {
				$parameters['skip'] = $query->getOffset();
			}

			$constraint = $query->getConstraint();
			if ($constraint !== NULL) {
				$parameters['q'] = $this->buildStatementForConstraint($constraint);
			} else {
				throw new \TYPO3\FLOW3\Exception('Call without constraint is not supported by CouchDB QueryIndex', 1299689063);
			}

			return $parameters;
		} else {
			throw new \TYPO3\FLOW3\Exception('query argument for QueryIndex must implement QueryInterface', 1299689063);
		}
	}

	/**
	 * Get the index function for the design document as JavaScript source
	 *
	 * @return string The function source code
	 * @author Felix Oertel <oertel@networkteam.com>
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function getIndexFunctionSource() {
		$this->ensureConfigured();

		return 'function(doc){if(' . $this->buildClassnameConstraint() . '){' .
			'var result = new Document();'.
			implode('', $this->indexStatements) .
			'return result;}}';
	}

	/**
	 * Build a constraint to index only documents with the correct type
	 *
	 * @return string The classname constraint
	 * @author Felix Oertel <oertel@networkteam.com>
	 */
	protected function buildClassnameConstraint() {
		return 'doc.classname=="' . addslashes($this->type) . '"';
	}

	/**
	 * Escape a value for special query characters such as ':', '(', ')', '*', '?', etc.
	 *
	 * NOTE: inside a phrase fewer characters need escaped, use {@link Apache_Solr_Service::escapePhrase()} instead
	 *
	 * @param string $value
	 * @return string
	 */
	protected function escape($value, $allowWildcard = FALSE) {
		//list taken from http://lucene.apache.org/java/docs/queryparsersyntax.html#Escaping%20Special%20Characters
		$pattern = '/(\+|-|&&|\|\||!|\(|\)|\{|}|\[|]|\^|"|~' . ($allowWildcard ? '' : '|\*|\?') . '|:|\\\)/';
		$replace = '\\\$1';

		return preg_replace($pattern, $replace, $value);
	}

	/**
	 * Escape a value meant to be contained in a phrase for special query characters
	 *
	 * @param string $value
	 * @return string
	 */
	protected function escapePhrase($value) {
		$pattern = '/("|\\\)/';
		$replace = '\\\$1';

		return preg_replace($pattern, $replace, $value);
	}

	/**
	 * Convenience function for creating phrase syntax from a value
	 *
	 * @param string $value
	 * @return string
	 */
	protected function phrase($value) {
		return '"' . $this->escapePhrase($value) . '"';
	}

	/**
	 * Create a query using this index
	 *
	 * @return \TYPO3\CouchDB\Persistence\LuceneQuery
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function createQuery() {
		return $this->luceneQueryFactory->create($this);
	}

}
?>