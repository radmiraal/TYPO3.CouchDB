<?php
namespace F3\CouchDB\Testing\Domain\Model;

/**
 * @scope prototype
 * @entity
 */
class TestEntity {
	/**
	 *
	 * @var string
	 */
    protected $name;

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
}
?>