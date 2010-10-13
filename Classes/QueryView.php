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
 * A CouchDB view for queries
 *
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 * @scope prototype
 */
class QueryView implements \F3\CouchDB\ViewInterface {
	/**
	 *
	 * @var array
	 */
	protected $emits = array();

	/**
	 * @var string
	 */
	protected $name;

	/**
	 * @var string
	 */
	protected $type;

	/**
	 *
	 * @param QueryInterface $query
	 */
	public function __construct(\F3\FLOW3\Persistence\QueryInterface $query) {
		$constraint = $query->getConstraint();
		$this->type = $query->getType();
		if ($constraint !== NULL) {
			$this->emits = $this->buildEmitsForConstraint($constraint);
		}
		$constraintName = $this->buildNameForConstraint($constraint);
		$this->name = $this->type . (($constraintName !== '') ? '_' . $constraintName : '');
	}

	/**
	 *
	 * @param \F3\FLOW3\Persistence\Qom\Constraint $constraint
	 */
	protected function buildEmitsForConstraint(\F3\FLOW3\Persistence\Qom\Constraint $constraint) {
		$emits = array();
		if ($constraint instanceof \F3\FLOW3\Persistence\Qom\Comparison) {
			if ($constraint->getOperator() === \F3\FLOW3\Persistence\QueryInterface::OPERATOR_EQUAL_TO) {
				$emits[] = $this->buildEmitForOperand($constraint->getOperand1());
			} else {
				throw new \InvalidArgumentException('Operator ' . $constraint->getOperator() . ' is not supported by CouchDB QueryView', 1286466452);
			}
		} elseif($constraint instanceof \F3\FLOW3\Persistence\Qom\LogicalAnd) {
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

	protected function buildEmitForOperand(\F3\FLOW3\Persistence\Qom\Operand $operand) {
		$emit = new \stdClass();
		if ($operand instanceof \F3\FLOW3\Persistence\Qom\PropertyValue) {
			$emit->type = 'property';
			$emit->property = $operand->getPropertyName();
		}
		return $emit;
	}

	protected function buildNameForConstraint($constraint) {
		if ($constraint instanceof \F3\FLOW3\Persistence\Qom\Comparison) {
			if ($constraint->getOperator() === \F3\FLOW3\Persistence\QueryInterface::OPERATOR_EQUAL_TO) {
				if ($constraint->getOperand1() instanceof \F3\FLOW3\Persistence\Qom\PropertyValue) {
					return 'equals<' . $constraint->getOperand1()->getPropertyName() . '>';
				}
			}
		} elseif ($constraint instanceof \F3\FLOW3\Persistence\Qom\LogicalAnd) {
			return 'and<' . $this->buildNameForConstraint($constraint->getConstraint1()) . ',' . $this->buildNameForConstraint($constraint->getConstraint2()) . '>';
		}
		return '';
	}

	/**
	 *
	 * @param \F3\FLOW3\Persistence\Qom\Constraint $constraint
	 */
	protected function buildKeyForConstraint(\F3\FLOW3\Persistence\Qom\Constraint $constraint) {
		if ($constraint instanceof \F3\FLOW3\Persistence\Qom\Comparison) {
			if ($constraint->getOperator() === \F3\FLOW3\Persistence\QueryInterface::OPERATOR_EQUAL_TO) {
				// TODO Suport other operands?
				return (string)$constraint->getOperand2();
			}
		} elseif($constraint instanceof \F3\FLOW3\Persistence\Qom\LogicalAnd) {
			return array(
				$this->buildKeyForConstraint($constraint->getConstraint1()),
				$this->buildKeyForConstraint($constraint->getConstraint2())
			);
		}
		return NULL;
	}

	/**
	 * Get the design name where the view is defined. This is FLOW3 as a
	 * default for a QueryView.
	 *
	 * @return string
	 */
	public function getDesignName() {
		return 'FLOW3';
	}

	/**
	 * Get the name of the view, this identifies the view and has to be
	 * unique inside one design document.
	 *
	 * @return string
	 */
	public function getViewName() {
		return 'query_' . $this->name;
	}

	/**
	 * Get CouchDB query parameters by arguments
	 *
	 * @param array $arguments
	 * @return array CouchDB view query parameters
	 */
	public function getViewParameters($arguments) {
		if (isset($arguments['query']) && $arguments['query'] instanceof \F3\FLOW3\Persistence\QueryInterface) {
			$query = $arguments['query'];

			$parameters = array(
				'include_docs' => 'true'
			);

			if ($query->getLimit() !== NULL) {
				$parameters['limit'] = $query->getLimit();
			}
			if ($query->getOffset() !== NULL) {
				$parameters['skip'] = $query->getOffset();
			}

			$constraint = $query->getConstraint();
			if ($constraint !== NULL) {
				$key = $this->buildKeyForConstraint($constraint);
				$parameters['key'] = json_encode($key);
			}

			return $parameters;

		} else {
			throw new Exception('query argument for QueryView must implement QueryInterface', 1286369598);
		}
	}

	/**
	 * Get the map function for the view as JavaScript source
	 * @return string The function source code or null if no map function defined
	 */
	public function getMapFunctionSource() {
		return 'function(doc) { if (doc.classname == "' . addslashes($this->type) . '") {' . $this->getEmitStatements() .  '} }';
	}

	/**
	 * @return string
	 */
	protected function getEmitStatements() {
		$statements = array();
		if (count($this->emits) > 0) {
			foreach ($this->emits as $emit) {
				$statements[] = 'emit(' . $this->buildEmitIndex($emit) . ', null);';
			}
		} else {
			$statements[] = 'emit(doc._id, null);';
		}
		return implode(chr(10), $statements);
	}

	protected function buildEmitIndex($emit) {
		if (is_object($emit)) {
			if ($emit->type === 'property') {
				return 'doc.properties["' . $emit->property . '"].value';
			} elseif ($emit->type === 'and') {
				return '[' . $this->buildEmitIndex($emit->constraints[0]) . ', ' . $this->buildEmitIndex($emit->constraints[1]) . ']';
			}
		}
	}

	/**
	 * Get the reduce function for the view as JavaScript source
	 * @return string The function source code or null if no map function defined
	 */
	public function getReduceFunctionSource() {
		return '_count';
	}
}

?>