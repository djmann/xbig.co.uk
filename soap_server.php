<?php
require_once ('initialise.php');
require_once ('nusoap/nusoap.php');

$server = new nusoap_server();

$server->register ('update_game');
$server->register ('validate_media');
$server->register ('upload_media_file');

function update_game ($game_obj_str, $set_review_date = FALSE)
{
	$retval = 'success';

	try
	{
		$game_obj = unserialize (base64_decode ($game_obj_str));
		if ($game_obj == FALSE)
			throw new Exception ("Unable to unserialize\n$game_obj_str\n");

		// Rebuild the database connection for the object
		$dbconn = xbig_dbconn ();
		$game_obj->initialise_db ($dbconn);

		// Run update
		$game_obj->write_to_db (FALSE, TRUE, $set_review_date);
		$dbconn->commit();
	}
	catch (Exception $e)
	{
		$retval = 'TEST: ' . $e->getMessage();
	}

	return $retval;
}

function validate_media ($game_obj_str)
{
	$retval = FALSE;

	// TODO: proper SOAP error handling...
	$game_obj = unserialize (base64_decode ($game_obj_str));
	if ($game_obj == FALSE)
		throw new Exception ("Unable to unserialize\n$game_obj_str\n");

	$base_dir = '..';
	$retval = $game_obj->validate_media_all ($base_dir, FALSE);

	return $retval;
}

function upload_media_file ($game_obj_str, $destination, $img_str)
{
	$retval = 'success';
	$root_dir = '..';

	$src_filename 	= '/tmp/uploaded.jpg';
	$dest_filename	= "$root_dir/$destination";

	try
	{
		// TODO: proper SOAP error handling...
		$game_obj = unserialize (base64_decode ($game_obj_str));

		// 1: write the file out to /tmp
		$f_str = base64_decode ($img_str);
		if (file_put_contents ($src_filename, $f_str) == 0)
			throw new Exception ("Unable to write image to $src_filename\n");

		// 2: call the download_media_file function - we assume any resampling has already been actioned
		$game_obj->download_media_file ($src_filename, $dest_filename, FALSE);

		// 3: unlink the temporary file
		unlink ($src_filename);
	}
	catch (Exception $e)
	{
		$retval = "failed: {$e->getMessage()}";
	}

	return $retval;
}

$HTTP_RAW_POST_DATA = (isset ($HTTP_RAW_POST_DATA) ? $HTTP_RAW_POST_DATA : '');
$server->service ($HTTP_RAW_POST_DATA);

?>
