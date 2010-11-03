<?php
declare(ENCODING = 'utf-8');
namespace F3\CouchDB\Tests\Functional\Fixtures\Domain\Model;

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
 * A test entity for functional tests
 *
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 * @scope prototype
 * @entity
 */
class TestEntity {

	/**
	 * @var string
	 */
    protected $name;

	/**
	 *
	 * @var \F3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestValueObject
	 */
	protected $relatedValueObject;

	/**
	 *
	 * @var array<\F3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestValueObject>
	 */
	protected $relatedValueObjects;

	/**
	 * @var \F3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity
	 */
	protected $relatedEntity;

	/**
	 * @var \SplObjectStorage<\F3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity>
	 */
	protected $relatedEntities;

	/**
	 *
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 *
	 * @param string $name
	 */
	public function setName($name) {
		$this->name = $name;
	}

	/**
	 * @return \F3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity
	 */
	public function getRelatedEntity() {
		return $this->relatedEntity;
	}

	/**
	 * @param \F3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity $relatedEntity
	 */
	public function setRelatedEntity($relatedEntity) {
		$this->relatedEntity = $relatedEntity;
	}

	/**
	 *
	 * @return \SplObjectStorage<\F3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity>
	 */
	public function getRelatedEntities() {
		return $this->relatedEntities;
	}

	/**
	 *
	 * @param \SplObjectStorage<\F3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity> $relatedEntities
	 */
	public function setRelatedEntities($relatedEntities) {
		$this->relatedEntities = $relatedEntities;
	}

	/**
	 *
	 * @return \F3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestValueObject
	 */
	public function getRelatedValueObject() {
		return $this->relatedValueObject;
	}

	/**
	 *
	 * @param \F3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestValueObject $relatedValueObject
	 */
	public function setRelatedValueObject($relatedValueObject) {
		$this->relatedValueObject = $relatedValueObject;
	}

	/**
	 *
	 * @return array<\F3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestValueObject>
	 */
	public function getRelatedValueObjects() {
		return $this->relatedValueObjects;
	}

	/**
	 *
	 * @param array<\F3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestValueObject> $relatedValueObjects
	 */
	public function setRelatedValueObjects($relatedValueObjects) {
		$this->relatedValueObjects = $relatedValueObjects;
	}

}
?>