<?php
namespace TYPO3\CouchDB\Persistence;

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
 * A special lucene query
 *
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class LuceneQuery extends \TYPO3\FLOW3\Persistence\Generic\Query {

	/**
	 * @var \TYPO3\CouchDB\Domain\Index\LuceneIndex
	 */
	protected $index;

	/**
	 * @param \TYPO3\CouchDB\Domain\Index\LuceneIndex $index
	 * @param \TYPO3\FLOW3\Reflection\ReflectionService $reflectionService
	 */
	public function __construct(\TYPO3\CouchDB\Domain\Index\LuceneIndex $index, \TYPO3\FLOW3\Reflection\ReflectionService $reflectionService) {
		$this->setIndex($index);
		parent::__construct($index->getIndexName(), $reflectionService);
	}

	/**
	 * @param \TYPO3\CouchDB\Domain\Index\LuceneIndex $index
	 * @return void
	 */
	public function setIndex(\TYPO3\CouchDB\Domain\Index\LuceneIndex $index) {
		$this->index = $index;
	}

	/**
	 * @return \TYPO3\CouchDB\Domain\Index\LuceneIndex
	 */
	public function getIndex() {
		return $this->index;
	}

}
?>