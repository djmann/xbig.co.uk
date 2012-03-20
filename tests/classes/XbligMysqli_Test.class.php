<?php

require_once ("/var/www/php/classes/XbligMysqli.class.php");

class XbligMysqli_Test extends PHPUnit_Framework_TestCase
{
	public function testConstructor ()
	{
		$conn = new XbligMysqli ();

		$this->assertInstanceOf ('XbligMysqli', $conn);
	}

	// TODO: write test cases for Execute
	// TODO: write test cases for getResultSets
}

?>
