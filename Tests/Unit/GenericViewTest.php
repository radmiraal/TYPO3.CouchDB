<?php
declare(ENCODING = 'utf-8');
namespace TYPO3\CouchDB\Tests\Unit;

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
 * A test for creating and updating of generic views
 *
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class GenericViewTest extends \TYPO3\FLOW3\Tests\UnitTestCase {

	/**
	 * @var \TYPO3\CouchDB\Client
	 */
	protected $client;

	/**
	 * Setup a mock CouchDB client
	 */
	public function setUp() {
		$this->client = $this->getMock('TYPO3\CouchDB\Client', array(), array(), '', FALSE);
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function designDocumentNameIsInferredFromClassName() {
		$design = new Fixtures\Design\CompanyDesign();
		$design->setClient($this->client);

		$this->client->expects($this->atLeastOnce())->method('queryView')->with('company', 'totalPurchases', array(
			'key' => '123456789',
			'reduce' => TRUE
		));

		$design->totalPurchasesAmount('123456789');
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function designIsSynchronizedOnNotFound() {
		$mockDesignName = 'mock' . uniqid();
		$design = $this->getAccessibleMock('TYPO3\CouchDB\Tests\Unit\Fixtures\Design\CompanyDesign', array('synchronize'), array(), ucfirst($mockDesignName) . 'Design');
		$design->setClient($this->client);

		$this->client->expects($this->at(0))->method('queryView')->with($mockDesignName, 'totalPurchases', array(
			'key' => '123456789',
			'reduce' => TRUE
		))->will($this->throwException(new \TYPO3\CouchDB\Client\NotFoundException('{"error":"not_found","reason":"missing_named_view"}')));

		$this->client->expects($this->at(1))->method('queryView')->with($mockDesignName, 'totalPurchases', array(
			'key' => '123456789',
			'reduce' => TRUE
		))->will($this->returnValue('abc'));

		$design->expects($this->once())->method('synchronize');

		$amount = $design->totalPurchasesAmount('123456789');
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function synchronizeWithNoDesignDocumentCollectsDeclarationsAndCreatesDesignDocument() {
		$mockReflectionService = $this->getMock('TYPO3\FLOW3\Reflection\ReflectionService');

		$design = new Fixtures\Design\CompanyDesign();
		$design->setClient($this->client);
		$design->injectReflectionService($mockReflectionService);

		$mockReflectionService->expects($this->any())->method('isMethodStatic')->will($this->returnCallback(function($className, $methodName) {
			if ($methodName === 'totalPurchasesDeclaration') {
				return TRUE;
			}
			return FALSE;
		}));

		$this->client->expects($this->any())->method('getDocument')->with('_design/company')->will($this->throwException(new \TYPO3\CouchDB\Client\NotFoundException('{"error":"not_found","reason":"missing"}')));

		$this->client->expects($this->once())->method('createDocument')->with(array(
			'_id' => '_design/company',
			'language' => 'javascript',
			'views' => array(
				'totalPurchases' => array(
					'map' => 'function(doc) { if (doc.Type == "purchase") { emit(doc.Customer, doc.Amount); } }',
					'reduce' => 'function(keys, values) { return sum(values) }'
				)
			)
		));

		$design->synchronize();
	}

}
?>