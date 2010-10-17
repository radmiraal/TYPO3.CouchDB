<?php
namespace F3\CouchDB\Testing;

require_once(__DIR__ . '/../../../../Framework/FLOW3/Classes/Core/Bootstrap.php');

class BaseFunctionalTestCase extends \PHPUnit_Framework_TestCase {
	/**
	 *
	 * @var \F3\FLOW3\Object\ObjectManagerInterface
	 */
	protected static $objectManager;

	public static function setUpBeforeClass() {
		define('FLOW3_PATH_ROOT', __DIR__ . '/../../../../../');
		$_SERVER['FLOW3_WEBPATH'] = __DIR__ . '/../../../../../Web/';
		\F3\FLOW3\Core\Bootstrap::defineConstants();
		$flow3 = new \F3\FLOW3\Core\Bootstrap('Testing');
		$flow3->initialize();
		self::$objectManager = $flow3->getObjectManager();
	}

	/**
	 * @return \F3\FLOW3\Object\ObjectManagerInterface
	 */
	protected function getObjectManager() {
		return self::$objectManager;
	}
}
?>
