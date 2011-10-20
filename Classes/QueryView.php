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
 * A CouchDB view for queries
 *
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class QueryView implements \TYPO3\CouchDB\ViewInterface {

	/**
	 * @var \TYPO3\FLOW3\Reflection\ReflectionService
	 * @FLOW3\Inject
	 */
	protected $reflectionService;

	/**
	 *
	 * @var array
	 */
	protected $emits = array();

	/**
	 * @var string
	 */
	protected $viewName;

	/**
	 * @var string
	 */
	protected $type;

	/**
	 * @var string
	 */
	protected $designName;

	/**
	 * @var string
	 */
	protected $queryIdentifier;

	/**
	 * @var array
	 */
	protected $settings;

	/**
	 * Inject package settings
	 *
	 * @param array $settings
	 * @return void
	 */
	public function injectSettings(array $settings) {
		$this->settings = $settings;
		if (isset($this->settings['queries'][$this->queryIdentifier])) {
			if (isset($this->settings['queries'][$this->queryIdentifier]['viewName'])) {
				$this->viewName = $this->settings['queries'][$this->queryIdentifier]['viewName'];
			}
			if (isset($this->settings['queries'][$this->queryIdentifier]['designName'])) {
				$this->designName = $this->settings['queries'][$this->queryIdentifier]['designName'];
			}
		}
	}

	/**
	 *
	 * @param \TYPO3\FLOW3\Persistence\QueryInterface $query
	 */
	public function __construct(\TYPO3\FLOW3\Persistence\QueryInterface $query) {
		$constraint = $query->getConstraint();
		$this->type = $query->getType();
		if ($constraint !== NULL) {
			$this->emits = $this->buildEmitsForConstraint($constraint);
			$constraintName = '_' . $this->buildNameForConstraint($constraint);
		} else {
			$constraintName = '';
		}
		$this->queryIdentifier = strtr($this->type, '\\', '_') . $constraintName;
		$this->viewName = 'entities';
		$this->designName = 'query_' . $this->queryIdentifier;
	}

	/**
	 *
	 * @param \TYPO3\FLOW3\Persistence\Generic\Qom\Constraint $constraint
	 * @return array
	 */
	protected function buildEmitsForConstraint(\TYPO3\FLOW3\Persistence\Generic\Qom\Constraint $constraint) {
		$emits = array();
		if ($constraint instanceof \TYPO3\FLOW3\Persistence\Generic\Qom\Comparison) {
			if ($constraint->getOperator() === \TYPO3\FLOW3\Persistence\QueryInterface::OPERATOR_EQUAL_TO) {
				$emits[] = $this->buildEmitForOperand($constraint->getOperand1());
			} else {
				throw new \InvalidArgumentException('Operator ' . $constraint->getOperator() . ' is not supported by CouchDB QueryView', 1286466452);
			}
		} elseif($constraint instanceof \TYPO3\FLOW3\Persistence\Generic\Qom\LogicalAnd) {
			$emit = new \stdClass();
			$emit->type = 'and';
			$emit->constraints = array_merge(
				$this->buildEmitsForConstraint($constraint->getConstraint1()),
				$this->buildEmitsForConstraint($constraint->getConstraint2())
			);
			$emits[] = $emit;
		} else {
			throw new \InvalidArgumentException('Constraint ' . get_class($constraint) . ' is not supported by CouchDB QueryView', 1286466489);
		}
		return $emits;
	}

	/**
	 *
	 * @param \TYPO3\FLOW3\Persistence\Generic\Qom\Operand $operand
	 * @return \stdClass
	 */
	protected function buildEmitForOperand(\TYPO3\FLOW3\Persistence\Generic\Qom\Operand $operand) {
		$emit = new \stdClass();
		if ($operand instanceof \TYPO3\FLOW3\Persistence\Generic\Qom\PropertyValue) {
			$emit->type = 'property';
			$emit->property = $operand->getPropertyName();
		} else {
			throw new \InvalidArgumentException('Operand ' . get_class($operand) . ' is not supported by CouchDB QueryView', 1288606014);
		}
		return $emit;
	}

	/**
	 *
	 * @param \TYPO3\FLOW3\Persistence\Generic\Qom\Constraint $constraint
	 * @return string
	 */
	protected function buildNameForConstraint(\TYPO3\FLOW3\Persistence\Generic\Qom\Constraint $constraint) {
		if ($constraint instanceof \TYPO3\FLOW3\Persistence\Generic\Qom\Comparison) {
			if ($constraint->getOperator() === \TYPO3\FLOW3\Persistence\QueryInterface::OPERATOR_EQUAL_TO) {
				if ($constraint->getOperand1() instanceof \TYPO3\FLOW3\Persistence\Generic\Qom\PropertyValue) {
					return 'equals<' . $constraint->getOperand1()->getPropertyName() . '>';
				}
			}
		} elseif ($constraint instanceof \TYPO3\FLOW3\Persistence\Generic\Qom\LogicalAnd) {
			return 'and<' . $this->buildNameForConstraint($constraint->getConstraint1()) . ',' . $this->buildNameForConstraint($constraint->getConstraint2()) . '>';
		}
		return '';
	}

	/**
	 *
	 * @param \TYPO3\FLOW3\Persistence\Generic\Qom\Constraint $constraint
	 * @return mixed
	 */
	protected function buildKeyForConstraint(\TYPO3\FLOW3\Persistence\Generic\Qom\Constraint $constraint) {
		if ($constraint instanceof \TYPO3\FLOW3\Persistence\Generic\Qom\Comparison) {
			if ($constraint->getOperator() === \TYPO3\FLOW3\Persistence\QueryInterface::OPERATOR_EQUAL_TO) {
				return $this->buildKeyForOperand($constraint->getOperand2());
			}
		} elseif($constraint instanceof \TYPO3\FLOW3\Persistence\Generic\Qom\LogicalAnd) {
			return array(
				$this->buildKeyForConstraint($constraint->getConstraint1()),
				$this->buildKeyForConstraint($constraint->getConstraint2())
			);
		} else {
			throw new \InvalidArgumentException('Constraint ' . get_class($constraint) . ' is not supported by CouchDB QueryView', 1288606305);
		}
		return NULL;
	}

	/**
	 *
	 * @param mixed $operand The operand as string or object
	 */
	protected function buildKeyForOperand($operand) {
		if (is_string($operand)) {
			return $operand;
		} else {
			throw new \InvalidArgumentException('Non-string operand value of type ' . get_class($operand) . ' is not supported by CouchDB QueryView', 1288606494);
		}
	}

	/**
	 * Get the design name where the view is defined. This defaults to FLOW3
	 * for a QueryView.
	 *
	 * @return string
	 */
	public function getDesignName() {
		return $this->designName;
	}

	/**
	 * Get the name of the view, this identifies the view and has to be
	 * unique inside one design document.
	 *
	 * @return string
	 */
	public function getViewName() {
		return $this->viewName;
	}

	/**
	 * Get CouchDB query parameters by arguments
	 *
	 * @param array $arguments
	 * @return array CouchDB view query parameters
	 */
	public function buildViewParameters(array $arguments) {
		if (isset($arguments['query']) && $arguments['query'] instanceof \TYPO3\FLOW3\Persistence\QueryInterface) {
			$query = $arguments['query'];

			if (isset($arguments['count']) && $arguments['count'] === TRUE) {
				$parameters = array(
					'reduce' => TRUE
				);
			} else {
				$parameters = array(
					'reduce' => FALSE,
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
				$key = $this->buildKeyForConstraint($constraint);
				$parameters['key'] = $key;
			}

			return $parameters;
		} else {
			throw new \InvalidArgumentException('The "query" argument for QueryView must implement QueryInterface', 1286369598);
		}
	}

	/**
	 * Get the map function for the view as JavaScript source
	 * @return string The function source code or null if no map function defined
	 */
	public function getMapFunctionSource() {
		return 'function(doc){if(!doc._deleted&&' . $this->buildClassnameConstraints() . '){' . $this->getEmitStatements() .  '}}';
	}

	/**
	 * @return string
	 */
	protected function buildClassnameConstraints() {
		$constraints = array('doc.classname=="' . addslashes($this->type) . '"');
		$subClassNames = $this->reflectionService->getAllSubClassNamesForClass($this->type);
		foreach ($subClassNames as $subClassName) {
			if (strpos($subClassName, 'AOPProxy') !== FALSE) continue;
			$constraints[] = 'doc.classname=="' . addslashes($subClassName) . '"';
		}
		return implode('||', $constraints);
	}

	/**
	 * @return string
	 */
	public function getEmitStatements() {
		$statements = array();
		if (count($this->emits) > 0) {
			foreach ($this->emits as $emit) {
				$statements[] = 'emit(' . $this->buildEmitIndex($emit) . ',null);';
			}
		} else {
			$statements[] = 'emit(doc._id,null);';
		}
		return implode(chr(10), $statements);
	}

	/**
	 * Get the index value (key) for an emit
	 *
	 * @return string
	 */
	protected function buildEmitIndex($emit) {
		if (is_object($emit)) {
			if ($emit->type === 'property') {
				$emitStatement = '';
				$propertyName = $emit->property;
				$propertyPathParts = explode('.', $propertyName);
				foreach ($propertyPathParts as $propertyPathPart) {
					$pathParts[] = 'properties["' . $propertyPathPart . '"].value';
					$checkParts[] = 'doc.' . implode('.', $pathParts);
				}
				$checkParts = array_slice($checkParts, 0, -1);

				if (count($checkParts) > 0) {
					$emitStatement .= '(' . implode('&&', $checkParts) . ')?';
				}
				$emitStatement .= 'doc.' . implode('.', $pathParts);
				if (count($checkParts) > 0) {
					$emitStatement .= ':null';
				}

				return $emitStatement;
			} elseif ($emit->type === 'and') {
				return '[' . $this->buildEmitIndex($emit->constraints[0]) . ',' . $this->buildEmitIndex($emit->constraints[1]) . ']';
			}
		}
	}

	/**
	 * Get the reduce function for the view as JavaScript source. The query
	 * view uses the builtin "_count" function to execute counts on queries
	 * efficiently.
	 *
	 * @return string The function source code or null if no map function defined
	 */
	public function getReduceFunctionSource() {
		return '_count';
	}
}

?>