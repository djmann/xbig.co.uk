<?php
/**
 * A simple autoloader mechanism
 *
 * @author David Mann <david.mann@djmann.co.uk>
 * @copyright David Mann 2012
 */

/**
 * Simple container for the autoloader function
 *
 */

class Xblig_autoloader
{
	public static function autoload($className)
	{
		// All class names have a "Xblig_" prefix, which needs to be removed to get the filename
		// qnd hack...
		$prefix = 'Xblig_';
		if (strpos ($className, 'Xblig_') === 0) {
			$filename = substr($className, strlen($prefix));
		
			$baseDir = getBasePhpDirectory();

			// All classes should reside in the "classes" subdirectory
			// and should have the ".class.php" extension
			$filename = "$baseDir/classes/$filename.class.php";

			if (file_exists ($filename) == TRUE) {
				require_once ($filename);	

				if (class_exists ($className) == FALSE) {
					throw new Exception ("Class [$className] does not exist in file [$filename]\n");
				}
			} else {
				// If we can't find classes, we throw a generic exception...
				throw new Exception ("Unable to find class [$className] in file [$filename]");
			}
		} else {
			// If we can't find classes, we throw a generic exception...
			throw new Exception ("Class $className is not an Xblig class: unable to auto-load");
		}
	}
}

