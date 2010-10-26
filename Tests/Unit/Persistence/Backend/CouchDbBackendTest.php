<?php
declare(ENCODING = 'utf-8');
namespace F3\CouchDB\Tests\Unit\Persistence\Backend;

/*                                                                        *
 * This script belongs to the FLOW3 package "CouchDB".                    *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License as published by the Free   *
 * Software Foundation, either version 3 of the License, or (at your      *
 * option) any later version.                                             *
 *                                                                        *
 * This script is distributed in the hope that it will be useful, but     *
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
 * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General      *
 * Public License for more details.                                       *
 *                                                                        *
 * You should have received a copy of the GNU General Public License      *
 * along with the script.                                                 *
 * If not, see http://www.gnu.org/licenses/gpl.html                       *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

/**
 * Unit test for the CouchDB persistence backend
 *
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class CouchDbBackendTest extends \F3\Testing\BaseTestCase {
	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function initializeIgnoresOptionsWithNullValue() {
		$mockReflectionService = $this->getMock('F3\FLOW3\Reflection\ReflectionService');
		$mockReflectionService->expects($this->any())->method('getClassSchemata');

		$backend = $this->getMock('F3\CouchDB\Persistence\Backend\CouchDbBackend', array('connect'));
		$backend->expects($this->any())->method('connect');
		$backend->injectReflectionService($mockReflectionService);
		$backend->initialize(array('noOptionLikeThis' => NULL, 'dataSourceName' => 'http://1.2.3.4:5678'));
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function initializeConnectsToCouchDb() {
		$mockReflectionService = $this->getMock('F3\FLOW3\Reflection\ReflectionService');
		$mockReflectionService->expects($this->any())->method('getClassSchemata');

		$backend = $this->getMock('F3\CouchDB\Persistence\Backend\CouchDbBackend', array('connect'));
		$backend->expects($this->once())->method('connect');
		$backend->injectReflectionService($mockReflectionService);

		$backend->initialize(array('database' => 'foo', 'dataSourceName' => 'http://1.2.3.4:5678'));

	}

}
?>