<?php
require_once ('../initialise.php');

$action		= sanitise_str (read_form_input('action', 'update'));
$filename	= read_form_input('filename', '');

$url = 'http://www.xboxindiegames.co.uk/editor/live_sync.php';

// Filenames should always be in the format "abc.srz" *and* the file should always be 
// in the local directory
if ($filename != '') {
	if (preg_match('/^\w+\.srz$/', $filename) === 0 || basename ($filename) != $filename) {
		error_log ("Invalid filename [$filename]");
		print "ERROR F1";
		exit;
	}
}

switch ($action) {
	/**
	 * This is a deliberately simple client/server system.
	 * Server actions:
	 *		list: 				write out a list of available filenames to STDOUT, 
	 *							separated by newlines
	 *		read [filename]: 	write out the data held in the specified file (which 
	 *							should be a serialised php object
	 *		delete [filename]: 	delete the specified file
	 * Client actions:
	 *		update:				sends a "list" command to the server, requests the data 
	 *							from each available file, unserialises the object, calls 
	 *							commit() and then deletes the file
	 */

	case 'list':
		// For some reason, readdir() isn't able to reliably see all of the files in the 
		// current directory.  However, glob() is!
		foreach (glob("*.srz") as $filename)
			print "$filename\n";

		break;

	case 'read':
		if (file_exists ("./$filename") === TRUE) {
			$str = file_get_contents ("./$filename");
			print $str;
		} else {
			error_log ("Unable to find file [$filename]");
			print "ERROR R1";
		}

		break;

	case 'delete':
		if (file_exists ("./$filename") === TRUE) {
			if (unlink ("./$filename") === TRUE) {
				error_log ("Deleted filename [$filename]");
				print "SUCCESS";
			} else {
				error_log ("Unable to delete [$filename]");
				print "ERROR D1";
			}
		} else {
			error_log ("Unable to find file [$filename]");
			print "ERROR D2";
		}

		break;

	case 'update':
		$str = file_get_contents ($url . "?action=list");
		if (strpos ($str, "ERROR") === FALSE) {
			$file_list = preg_split ("/\n+/", $str);

			$dbconn = xbig_dbconn();
			foreach ($file_list as $filename) {
				try {
					$filename = trim ($filename);

					if ($filename != '') {
						print "Importing [$filename]...";
						$str = file_get_contents ("{$url}?action=read&filename={$filename}");

						if (strpos ($str, "ERROR") === FALSE) {
							update_game ($str, $dbconn);
							print "Imported.\n";
							$str = file_get_contents ($url . "?action=delete&filename=$filename");
							print "SRZ delete call made: $str\n";
						} else {
							throw new Exception ("READ failure: $str");
						}
					}
				} catch (Exception $e) {
					print "\nFailed to sync game: " . $e->getMessage();
				}
			}
		} else {
			print "LIST failure: $str";
		}
		break;

	default:
		print "Unknown action $action\n";
		break;

}

function update_game (&$game_obj_str, &$dbconn)
{
	$game_obj = unserialize ($game_obj_str);

	if ($game_obj == FALSE)
		throw new Exception ("Unable to unserialize\n$game_obj_str\n");

	// Rebuild the database connection for the object
	$game_obj->initialise_db ($dbconn);

	$set_ld = ($game_obj->metadata_list['review_date'] == '' ? TRUE : FALSE);

	// Run update - to keep things simple, we set the review date to "today" if the date is not defined
	$game_obj->write_to_db (FALSE, TRUE, $set_ld);
	$dbconn->commit();
}
?>
