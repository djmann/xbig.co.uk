<?php
require_once ('../../classes/Init.php');

class Xblig_DbFactory_Test extends PHPUnit_Framework_TestCase
{
	/*
	 * @covers getConnection
	 *
 	 */
	public function testGetConnection()
	{
		$className = 'Xblig_DbConnection';

		$conn = Xblig_DbFactory::getConnection();

		$this->assertInstanceOf($className, $conn);
	}

	/*
	 * @covers getConnection
	 *
 	 */
	public function testGetConnectionSingleton()
	{
		$className = 'Xblig_DbConnection';

		$conn1 = Xblig_DbFactory::getConnection();
		$conn2 = Xblig_DbFactory::getConnection();

		$this->assertSame ($conn1, $conn2);
	}
}
