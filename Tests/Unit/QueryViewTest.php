<?php
namespace TYPO3\CouchDB\Tests\Unit;

/*                                                                        *
 * This script belongs to the Flow package "CouchDB".                     *
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
 * QueryView unit test
 *
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class QueryViewTest extends \TYPO3\Flow\Tests\UnitTestCase {

	/**
	 * @test
	 */
	public function queryIdentifierIsTakenAsDesignName() {
		$mockQuery = $this->getMock('TYPO3\Flow\Persistence\Generic\Query', array(), array(), '', FALSE);
		$mockQuery->expects($this->any())->method('getType')->will($this->returnValue('TYPO3\CouchDB\\CouchDB\\Tests\\Unit\\TestEntity'));
		$mockQuery->expects($this->any())->method('getConstraint')->will($this->returnValue(NULL));

		$queryView = new \TYPO3\CouchDB\QueryView($mockQuery);

		$this->assertEquals('query_TYPO3_CouchDB_CouchDB_Tests_Unit_TestEntity', $queryView->getDesignName());
		$this->assertEquals('entities', $queryView->getViewName());
	}

	/**
	 * @test
	 */
	public function queryEmitsOnlyDocumentsWithClassnameOfType() {
		$mockQuery = $this->getMock('TYPO3\Flow\Persistence\Generic\Query', array(), array(), '', FALSE);
		$mockQuery->expects($this->any())->method('getType')->will($this->returnValue('TYPO3\CouchDB\\CouchDB\\Tests\\Unit\\TestEntity'));
		$mockQuery->expects($this->any())->method('getConstraint')->will($this->returnValue(NULL));

		$mockReflectionService = $this->getMock('TYPO3\Flow\Reflection\ReflectionService');
		$mockReflectionService->expects($this->any())->method('getAllSubClassNamesForClass')->will($this->returnValue(array()));

		$queryView = $this->getAccessibleMock('TYPO3\CouchDB\QueryView', array('dummy'), array($mockQuery));
		$queryView->_set('reflectionService', $mockReflectionService);

		$mapSource = $queryView->getMapFunctionSource();
		$this->assertRegExp('/^function\(doc\)\s?\{\s?if\s?\(!doc._deleted&&doc\.classname\s?==\s?"TYPO3\\\\\\\CouchDB\\\\\\\CouchDB\\\\\\\Tests\\\\\\\Unit\\\\\\\TestEntity"\)\s?\{.*\}\s?\}$/', $mapSource);
	}

	/**
	 * @test
	 */
	public function emptyQueryEmitsDocumentIdAndNull() {
		$mockQuery = $this->getMock('TYPO3\Flow\Persistence\Generic\Query', array(), array(), '', FALSE);
		$mockQuery->expects($this->any())->method('getType')->will($this->returnValue('TYPO3\CouchDB\\CouchDB\\Tests\\Unit\\TestEntity'));
		$mockQuery->expects($this->any())->method('getConstraint')->will($this->returnValue(NULL));

		$queryView = new \TYPO3\CouchDB\QueryView($mockQuery);

		$emitStatements = $queryView->getEmitStatements();
		$this->assertEquals('emit(doc._id,null);', $emitStatements);
	}

	/**
	 * @test
	 */
	public function viewNameDefaultsToEntities() {
		$mockQuery = $this->getMock('TYPO3\Flow\Persistence\Generic\Query', array(), array(), '', FALSE);
		$mockQuery->expects($this->any())->method('getType')->will($this->returnValue('TYPO3\CouchDB\\CouchDB\\Tests\\Unit\\TestEntity'));
		$mockQuery->expects($this->any())->method('getConstraint')->will($this->returnValue(NULL));

		$queryView = new \TYPO3\CouchDB\QueryView($mockQuery);

		$viewName = $queryView->getViewName();
		$this->assertEquals('entities', $viewName);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function viewNameAndDesignNameCanBeSpecifiedByQueryIdentifier() {
		$mockQuery = $this->getMock('TYPO3\Flow\Persistence\Generic\Query', array(), array(), '', FALSE);
		$mockQuery->expects($this->any())->method('getType')->will($this->returnValue('TYPO3\CouchDB\\CouchDB\\Tests\\Unit\\TestEntity'));
		$mockQuery->expects($this->any())->method('getConstraint')->will($this->returnValue(NULL));

		$queryView = new \TYPO3\CouchDB\QueryView($mockQuery);
		$queryView->injectSettings(array(
			'queries' => array(
				'TYPO3_CouchDB_CouchDB_Tests_Unit_TestEntity' => array(
					'designName' => 'foo',
					'viewName' => 'bar'
				)
			)
		));

		$this->assertEquals('foo', $queryView->getDesignName());
		$this->assertEquals('bar', $queryView->getViewName());
	}

	/**
	 * Test the emit of a simple query like:
	 *
	 * <code>
	 * $query->matching($query->equals('name', 'Test'))
	 * </code>
	 *
	 * @test
	 */
	public function singleEqualsConstraintEmitsPropertyValueAsKey() {
		$mockOperand = $this->getMock('TYPO3\Flow\Persistence\Generic\Qom\PropertyValue', array(), array(), '', FALSE);
		$mockOperand->expects($this->any())->method('getPropertyName')->will($this->returnValue('name'));

		$mockConstraint = $this->getMock('TYPO3\Flow\Persistence\Generic\Qom\Comparison', array(), array(), '', FALSE);
		$mockConstraint->expects($this->any())->method('getOperator')->will($this->returnValue(\TYPO3\Flow\Persistence\QueryInterface::OPERATOR_EQUAL_TO));
		$mockConstraint->expects($this->any())->method('getOperand1')->will($this->returnValue($mockOperand));

		$mockQuery = $this->getMock('TYPO3\Flow\Persistence\Generic\Query', array(), array(), '', FALSE);
		$mockQuery->expects($this->any())->method('getType')->will($this->returnValue('TYPO3\CouchDB\\CouchDB\\Tests\\Unit\\TestEntity'));
		$mockQuery->expects($this->any())->method('getConstraint')->will($this->returnValue($mockConstraint));

		$queryView = new \TYPO3\CouchDB\QueryView($mockQuery);

		$emitStatements = $queryView->getEmitStatements();
		$this->assertEquals('emit(doc.properties["name"].value,null);', $emitStatements);
	}

	/**
	 * Test the view parameters of a simple query like:
	 *
	 * <code>
	 * $query->matching($query->equals('name', 'Test'))
	 * </code>
	 *
	 * @test
	 */
	public function singleEqualsConstraintQueriesKey() {
		$mockOperand = $this->getMock('TYPO3\Flow\Persistence\Generic\Qom\PropertyValue', array(), array(), '', FALSE);
		$mockOperand->expects($this->any())->method('getPropertyName')->will($this->returnValue('name'));

		$mockConstraint = $this->getMock('TYPO3\Flow\Persistence\Generic\Qom\Comparison', array(), array(), '', FALSE);
		$mockConstraint->expects($this->any())->method('getOperator')->will($this->returnValue(\TYPO3\Flow\Persistence\QueryInterface::OPERATOR_EQUAL_TO));
		$mockConstraint->expects($this->any())->method('getOperand1')->will($this->returnValue($mockOperand));
		$mockConstraint->expects($this->any())->method('getOperand2')->will($this->returnValue('Test'));

		$mockQuery = $this->getMock('TYPO3\Flow\Persistence\Generic\Query', array(), array(), '', FALSE);
		$mockQuery->expects($this->any())->method('getType')->will($this->returnValue('TYPO3\CouchDB\\CouchDB\\Tests\\Unit\\TestEntity'));
		$mockQuery->expects($this->any())->method('getConstraint')->will($this->returnValue($mockConstraint));

		$queryView = new \TYPO3\CouchDB\QueryView($mockQuery);

		$viewParameters = $queryView->buildViewParameters(array('query' => $mockQuery));
		$expectedParameters = array(
			'include_docs' => TRUE,
			'key' => 'Test',
			'reduce' => FALSE
		);
		$this->assertEquals($expectedParameters, $viewParameters);
	}

	/**
	 * Test the emit of a query like:
	 *
	 * <code>
	 * $query->matching($query->logicalAnd($query->equals('name', 'Foo'), $query->equals('bar', 'Baz')))
	 * </code>
	 *
	 * @test
	 */
	public function logicalAndWithEqualConstraintsEmitsPropertyValuesAsArrayKey() {
		$mockOperand1 = $this->getMock('TYPO3\Flow\Persistence\Generic\Qom\PropertyValue', array(), array(), '', FALSE);
		$mockOperand1->expects($this->any())->method('getPropertyName')->will($this->returnValue('name'));

		$mockOperand2 = $this->getMock('TYPO3\Flow\Persistence\Generic\Qom\PropertyValue', array(), array(), '', FALSE);
		$mockOperand2->expects($this->any())->method('getPropertyName')->will($this->returnValue('bar'));

		$mockConstraint1 = $this->getMock('TYPO3\Flow\Persistence\Generic\Qom\Comparison', array(), array(), '', FALSE);
		$mockConstraint1->expects($this->any())->method('getOperator')->will($this->returnValue(\TYPO3\Flow\Persistence\QueryInterface::OPERATOR_EQUAL_TO));
		$mockConstraint1->expects($this->any())->method('getOperand1')->will($this->returnValue($mockOperand1));
		$mockConstraint1->expects($this->any())->method('getOperand2')->will($this->returnValue('Foo'));

		$mockConstraint2 = $this->getMock('TYPO3\Flow\Persistence\Generic\Qom\Comparison', array(), array(), '', FALSE);
		$mockConstraint2->expects($this->any())->method('getOperator')->will($this->returnValue(\TYPO3\Flow\Persistence\QueryInterface::OPERATOR_EQUAL_TO));
		$mockConstraint2->expects($this->any())->method('getOperand1')->will($this->returnValue($mockOperand2));
		$mockConstraint2->expects($this->any())->method('getOperand2')->will($this->returnValue('Baz'));

		$mockAndConstraint = $this->getMock('TYPO3\Flow\Persistence\Generic\Qom\LogicalAnd', array(), array(), '', FALSE);
		$mockAndConstraint->expects($this->any())->method('getConstraint1')->will($this->returnValue($mockConstraint1));
		$mockAndConstraint->expects($this->any())->method('getConstraint2')->will($this->returnValue($mockConstraint2));

		$mockQuery = $this->getMock('TYPO3\Flow\Persistence\Generic\Query', array(), array(), '', FALSE);
		$mockQuery->expects($this->any())->method('getType')->will($this->returnValue('TYPO3\CouchDB\\CouchDB\\Tests\\Unit\\TestEntity'));
		$mockQuery->expects($this->any())->method('getConstraint')->will($this->returnValue($mockAndConstraint));

		$queryView = new \TYPO3\CouchDB\QueryView($mockQuery);

		$emitStatements = $queryView->getEmitStatements();
		$this->assertEquals('emit([doc.properties["name"].value,doc.properties["bar"].value],null);', $emitStatements);
	}

	/**
	 * Test the view parameters of a query like:
	 *
	 * <code>
	 * $query->matching($query->logicalAnd($query->equals('name', 'Foo'), $query->equals('bar', 'Baz')))
	 * </code>
	 *
	 * @test
	 */
	public function logicalAndWithEqualConstraintsQueriesArrayKey() {
		$mockOperand1 = $this->getMock('TYPO3\Flow\Persistence\Generic\Qom\PropertyValue', array(), array(), '', FALSE);
		$mockOperand1->expects($this->any())->method('getPropertyName')->will($this->returnValue('name'));

		$mockOperand2 = $this->getMock('TYPO3\Flow\Persistence\Generic\Qom\PropertyValue', array(), array(), '', FALSE);
		$mockOperand2->expects($this->any())->method('getPropertyName')->will($this->returnValue('bar'));

		$mockConstraint1 = $this->getMock('TYPO3\Flow\Persistence\Generic\Qom\Comparison', array(), array(), '', FALSE);
		$mockConstraint1->expects($this->any())->method('getOperator')->will($this->returnValue(\TYPO3\Flow\Persistence\QueryInterface::OPERATOR_EQUAL_TO));
		$mockConstraint1->expects($this->any())->method('getOperand1')->will($this->returnValue($mockOperand1));
		$mockConstraint1->expects($this->any())->method('getOperand2')->will($this->returnValue('Foo'));

		$mockConstraint2 = $this->getMock('TYPO3\Flow\Persistence\Generic\Qom\Comparison', array(), array(), '', FALSE);
		$mockConstraint2->expects($this->any())->method('getOperator')->will($this->returnValue(\TYPO3\Flow\Persistence\QueryInterface::OPERATOR_EQUAL_TO));
		$mockConstraint2->expects($this->any())->method('getOperand1')->will($this->returnValue($mockOperand2));
		$mockConstraint2->expects($this->any())->method('getOperand2')->will($this->returnValue('Baz'));

		$mockAndConstraint = $this->getMock('TYPO3\Flow\Persistence\Generic\Qom\LogicalAnd', array(), array(), '', FALSE);
		$mockAndConstraint->expects($this->any())->method('getConstraint1')->will($this->returnValue($mockConstraint1));
		$mockAndConstraint->expects($this->any())->method('getConstraint2')->will($this->returnValue($mockConstraint2));

		$mockQuery = $this->getMock('TYPO3\Flow\Persistence\Generic\Query', array(), array(), '', FALSE);
		$mockQuery->expects($this->any())->method('getType')->will($this->returnValue('TYPO3\CouchDB\\CouchDB\\Tests\\Unit\\TestEntity'));
		$mockQuery->expects($this->any())->method('getConstraint')->will($this->returnValue($mockAndConstraint));

		$queryView = new \TYPO3\CouchDB\QueryView($mockQuery);

		$viewParameters = $queryView->buildViewParameters(array('query' => $mockQuery));
		$expectedParameters = array(
			'include_docs' => TRUE,
			'key' => array(
				'Foo',
				'Baz'
			),
			'reduce' => FALSE
		);
		$this->assertEquals($expectedParameters, $viewParameters);
	}

	/**
	 * @test
	 */
	public function reduceFunctionUsesBuiltinCountFunction() {
		$mockQuery = $this->getMock('TYPO3\Flow\Persistence\Generic\Query', array(), array(), '', FALSE);
		$mockQuery->expects($this->any())->method('getType')->will($this->returnValue('TYPO3\CouchDB\\CouchDB\\Tests\\Unit\\TestEntity'));
		$mockQuery->expects($this->any())->method('getConstraint')->will($this->returnValue(NULL));

		$queryView = new \TYPO3\CouchDB\QueryView($mockQuery);

		$this->assertEquals('_count', $queryView->getReduceFunctionSource());
	}

	/**
	 * @test
	 */
	public function countArgumentUsesReduceOnView() {
		$mockQuery = $this->getMock('TYPO3\Flow\Persistence\Generic\Query', array(), array(), '', FALSE);
		$mockQuery->expects($this->any())->method('getType')->will($this->returnValue('TYPO3\CouchDB\\CouchDB\\Tests\\Unit\\TestEntity'));
		$mockQuery->expects($this->any())->method('getConstraint')->will($this->returnValue(NULL));

		$queryView = new \TYPO3\CouchDB\QueryView($mockQuery);

		$viewParameters = $queryView->buildViewParameters(array('query' => $mockQuery, 'count' => TRUE));
		$expectedParameters = array(
			'reduce' => TRUE
		);
		$this->assertEquals($expectedParameters, $viewParameters);
	}
}
?>