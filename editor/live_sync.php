<?php
require_once ('../initialise.php');

$action		= sanitise_str (read_form_input('action', 'list'));
$filename	= read_form_input('filename', '');

// Filenames should always be in the format "abc.srz"
if ($filename != '')
{
	if (preg_match('/^\w+\.srz$/', $filename) === 0)
	{
		error_log ("Invalid filename [$filename]");
		print "ERROR";
		exit;
	}
}


switch ($action)
{
	// This is a simple system: we write out the filenames to STDOUT and use file_get_contents to retrieve them
	case 'list':
		// For some reason, readdir() isn't actually able to reliably see all of the files.  glob() however, is...
		// (it's possibly something to do with the fact that the original code called readdir() twice on the same directory
		// But I'm not sure why this would result in an incomplete file list...

		foreach (glob("*.srz") as $filename) 
			print "$filename\n";

		break;
/*
		$cwd		= opendir ('.');
		$file_list	= readdir ($cwd);

		while (false !== ($filename = readdir($cwd)))
		{
			if (preg_match ("/\.srz$/", $filename) != 0)
				print "$filename\n";
		}
 */

	case 'read':
		if (file_exists ("./$filename") === TRUE)
		{
			$str = file_get_contents ("./$filename");
			print $str;
		}
		else
		{
			error_log ("Unable to find file [$filename]");
			print "ERROR";
		}

		break;

	case 'delete':
		if (file_exists ("./$filename") === TRUE)
		{
			if (unlink ("./$filename") === TRUE)
			{
				error_log ("Deleted filename [$filename]");
				print "SUCCESS";
			}
			else
			{
				error_log ("Unable to delete [$filename]");
				print "ERROR";
			}
		}
		else
		{
			error_log ("Unable to find file [$filename]");
			print "ERROR";
		}

		break;

	case 'debug':

		$i = 0;
		foreach (glob("*.srz") as $filename) 
		{
			print "$i: $filename<br>\n";
			$i++;
		}

		print "--<br>\n";

		$i = 0;
		$cwd		= opendir ('.');
		while (FALSE !== ($filename = readdir($cwd)))
		{
			print "$i: $filename<br>\n";
			$i++;
		}
		break;

	default:
		print "ERROR";
		break;
}

?>
