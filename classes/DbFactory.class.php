<?php
/**
 * Very simple "singleton" factory class for XBLIG database connections
 * @author David Mann <david.mann@djmann.co.uk>
 * @copyright David Mann 2012
 */

/**
 * Very simple "singleton" factory class for XBLIG database connections
 */
class Xblig_DbFactory
{
    /**
     * Holds the single instance of the database connection 
     *
     * @var Xblig_DbConnection
     */
	private static $instance;

    /**
     * Returns a database connection (after creating one if necessary)
     *
     * @param boolean $autocommit Used to set the default autocommit state of the database connection
     * @return Xblig_DbConnection
     */
	public static function getConnection($autocommit = FALSE)
	{
		if (!isset(self::$instance)) {
			self::$instance = new Xblig_DbConnection (MYSQL_SERVER, MYSQL_USER, MYSQL_PASSWD, MYSQL_DB);
            self::$instance->autocommit($autocommit);
		}

		return self::$instance;
	}

    /**
     * Simple placeholder to prevent cloning of the factory
     *
     * @return void
     */
	public function __clone()
	{
		throw new Xblig_Exception ('Clone is not allowed.', E_USER_ERROR);
	}

    /**
     * Simple placeholder to prevent unserialization of the factory
     *
     * @return void
     */
	public function __wakeup()
	{
		throw new Xblig_Exception ('Unserializing is not allowed.', E_USER_ERROR);
	}

    /**
     * Simple placeholder to prevent serialization of the factory
     *
     * @return void
     */
	public function __sleep()
	{
		throw new Xblig_Exception ('Serializing is not allowed.', E_USER_ERROR);
	}
}
?>
