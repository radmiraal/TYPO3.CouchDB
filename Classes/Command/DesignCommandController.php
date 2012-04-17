<?php
namespace TYPO3\CouchDB\Command;

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
 * A command controller to synchronize design documents
 *
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class DesignCommandController extends \TYPO3\FLOW3\Cli\CommandController {

	/**
	 * @FLOW3\Inject
	 * @var \TYPO3\FLOW3\Reflection\ReflectionService
	 */
	protected $reflectionService;

	/**
	 * @FLOW3\Inject
	 * @var \TYPO3\FLOW3\Object\ObjectManagerInterface
	 */
	protected $objectManager;

	/**
	 * Synchronize designs from FLOW3 declarations to CouchDB documents
	 *
	 * @return void
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function synchronizeCommand() {
		$designDocumentClassNames = $this->reflectionService->getAllSubClassNamesForClass('TYPO3\CouchDB\DesignDocument');
		foreach ($designDocumentClassNames as $objectName) {
			if ($this->objectManager->getScope($objectName) === \TYPO3\FLOW3\Object\Configuration\Configuration::SCOPE_SINGLETON) {
				$designDocument = $this->objectManager->get($objectName);
				$designDocument->synchronize();
				$this->outputLine($objectName . ' synchronized.');
			} else {
				$this->outputLine($objectName . ' skipped.');
			}
		}
	}

}
?>