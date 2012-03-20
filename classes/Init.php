<?php
/**
 * Basic configuration/initialisation of the PHP system
 *
 * @author David Mann <david.mann@djmann.co.uk>
 * @copyright David Mann 2012
 */

ini_set ('zlib.output_compression', 'On');

$baseDir = getBasePhpDirectory();

require_once ("$baseDir/classes/Autoloader.class.php");
$autoloader = array('Xblig_autoloader', 'autoload');
spl_autoload_register($autoloader, true);

require_once ("$baseDir/../.dbconfig.php");

require_once ("$baseDir/gen_library.php");
require_once ("$baseDir/xbig_library.php");

/*
 * Returns the base directory for the PHP code
 * 
 * @access public
 * @return string
 */
function getBasePhpDirectory()
{
	// Mild hack to get around the issue of relative filepaths
	// since the live server has a different path layout to the dev box.
	// All php code should be relative to the "php" subdirectory

	$cwd		= getcwd();
	$baseDir	= '';

	$i = strpos($cwd, "/php");

	if ($i !== FALSE) {
		$baseDir = substr ($cwd, 0, $i + 4);
	} else {
		// Note that this throws a generic exception - if we can't find the
		// base dir, we won't be able to find the Xblig_Exception!
		throw new Exception ("Unable to determine base directory");
	}

	return $baseDir;
}
