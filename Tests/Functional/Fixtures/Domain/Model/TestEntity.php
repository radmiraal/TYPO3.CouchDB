<?php
namespace TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model;

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
 * A test entity for functional tests
 *
 * @FLOW3\Entity
 */
class TestEntity {

	/**
	 * @var string
	 */
	protected $name;

	/**
	 * @var \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestValueObjectWithReference
	 */
	protected $relatedValueObjectWithReference;

	/**
	 * @var \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestValueObject
	 */
	protected $relatedValueObject;

	/**
	 * @var array<\TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestValueObject>
	 */
	protected $relatedValueObjects;

	/**
	 * @var \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity
	 */
	protected $relatedEntity;

	/**
	 * @var \Doctrine\Common\Collections\ArrayCollection<\TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity>
	 */
	protected $relatedEntities;

	/**
	 * @var \Doctrine\Common\Collections\ArrayCollection<\TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestNonRootEntity>
	 */
	protected $relatedNonRootEntities;

	/**
	 * @var \Doctrine\Common\Collections\ArrayCollection<\TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestLazyNonRootEntity>
	 * @FLOW3\Lazy
	 */
	protected $relatedLazyNonRootEntities;

	/**
	 * @var \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestLazyNonRootEntity
	 * @FLOW3\Lazy
	 */
	protected $relatedLazyNonRootEntity;

	/**
	 * @var array
	 */
	protected $valueArray;

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
	 * @return \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity
	 */
	public function getRelatedEntity() {
		return $this->relatedEntity;
	}

	/**
	 * @param \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity $relatedEntity
	 */
	public function setRelatedEntity(TestEntity $relatedEntity = NULL) {
		$this->relatedEntity = $relatedEntity;
	}

	/**
	 *
	 * @return \Doctrine\Common\Collections\ArrayCollection<\TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity>
	 */
	public function getRelatedEntities() {
		return $this->relatedEntities;
	}

	/**
	 *
	 * @param \Doctrine\Common\Collections\ArrayCollection<\TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestEntity>
	 */
	public function setRelatedEntities(\Doctrine\Common\Collections\ArrayCollection $relatedEntities) {
		$this->relatedEntities = $relatedEntities;
	}

	/**
	 *
	 * @return \Doctrine\Common\Collections\ArrayCollection<\TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestNonRootEntity>
	 */
	public function getRelatedNonRootEntities() {
		return $this->relatedNonRootEntities;
	}

	/**
	 *
	 * @param \Doctrine\Common\Collections\ArrayCollection<\TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestNonRootEntity>
	 */
	public function setRelatedNonRootEntities($relatedNonRootEntities) {
		$this->relatedNonRootEntities = $relatedNonRootEntities;
	}

	/**
	 * @return \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestLazyNonRootEntity
	 */
	public function getRelatedLazyNonRootEntity() {
		return $this->relatedLazyNonRootEntity;
	}

	/**
	 * @param \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestLazyNonRootEntity $relatedLazyNonRootEntity
	 */
	public function setRelatedLazyNonRootEntity(TestLazyNonRootEntity $relatedLazyNonRootEntity = NULL) {
		$this->relatedLazyNonRootEntity = $relatedLazyNonRootEntity;
	}

	/**
	 *
	 * @return \Doctrine\Common\Collections\ArrayCollection<\TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestLazyNonRootEntity>
	 */
	public function getRelatedLazyNonRootEntities() {
		return $this->relatedLazyNonRootEntities;
	}

	/**
	 *
	 * @param \Doctrine\Common\Collections\ArrayCollection<\TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestLazyNonRootEntity>
	 */
	public function setRelatedLazyNonRootEntities($relatedLazyNonRootEntities) {
		$this->relatedLazyNonRootEntities = $relatedLazyNonRootEntities;
	}

	/**
	 *
	 * @return \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestValueObject
	 */
	public function getRelatedValueObject() {
		return $this->relatedValueObject;
	}

	/**
	 *
	 * @param \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestValueObject $relatedValueObject
	 */
	public function setRelatedValueObject($relatedValueObject) {
		$this->relatedValueObject = $relatedValueObject;
	}

	/**
	 *
	 * @return array<\TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestValueObject>
	 */
	public function getRelatedValueObjects() {
		return $this->relatedValueObjects;
	}

	/**
	 *
	 * @param array<\TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestValueObject> $relatedValueObjects
	 */
	public function setRelatedValueObjects($relatedValueObjects) {
		$this->relatedValueObjects = $relatedValueObjects;
	}

	/**
	 * @return \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestValueObjectWithReference
	 */
	public function getRelatedValueObjectWithReference() {
		return $this->relatedValueObjectWithReference;
	}

	/**
	 * @param \TYPO3\CouchDB\Tests\Functional\Fixtures\Domain\Model\TestValueObjectWithReference $relatedValueObjectWithReference
	 */
	public function setRelatedValueObjectWithReference($relatedValueObjectWithReference) {
		$this->relatedValueObjectWithReference = $relatedValueObjectWithReference;
	}

	/**
	 * @return array
	 */
	public function getValueArray() {
		return $this->valueArray;
	}

	/**
	 * @param array $valueArray
	 * @return void
	 */
	public function setValueArray($valueArray) {
		$this->valueArray = $valueArray;
	}

}
?>