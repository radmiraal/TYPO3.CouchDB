<?php
declare(ENCODING = 'utf-8');
namespace F3\CouchDB\Tests\Functional\Fixtures\Domain\Repository;

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
 * A test repository for functional tests
 *
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 * @scope singleton
 */
class TestEntityRepository extends \F3\FLOW3\Persistence\Repository {

	/**
	 * @inject
	 * @var \F3\CouchDB\Tests\Functional\Fixtures\Domain\Index\TestIndex
	 */
	protected $testIndex;

	/**
	 * @param string $name
	 * @return \F3\FLOW3\Persistence\QueryResultInterface
	 */
	public function findByNameLike($name) {
		$query = $this->testIndex->createQuery();
		$query->matching(
			$query->like('name', $name)
		);
		return $query->execute();
	}

	/**
	 * @param string $color
	 * @return \F3\FLOW3\Persistence\QueryResultInterface
	 */
	public function findByColor($color) {
		$query = $this->testIndex->createQuery();
		$query->matching(
			$query->equals('relatedValueObject.color', $color)
		);
		return $query->execute();
	}

	/**
	 * @param string $name
	 * @param string $color
	 * @return \F3\FLOW3\Persistence\QueryResultInterface
	 */
	public function findByNameOrColor($name, $color) {
		$query = $this->testIndex->createQuery();
		$query->matching(
			$query->logicalOr(
				$query->like('name', $name),
				$query->equals('relatedValueObject.color', $color)
			)
		);
		return $query->execute();
	}

}
?>