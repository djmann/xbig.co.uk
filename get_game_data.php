<?php
// Simple script to read date-sorted information on XBIG reviews
require 'initialise.php';

header('Content-type: text/xml');

$blog_id		= sanitise_str (read_form_input('blog_id', 'dogfightsketch'));
$base_path		= '../..';

try
{
        // 1: Prep the database connection - with autocommit disbled
        $dbconn = xbig_dbconn ();

	$game_id = get_game_id ($dbconn, FALSE, $blog_id);

	if ($game_id == FALSE)
		throw new Exception ("Blog ID $blog_id not found");

	$game_obj = new game_template ($dbconn);
	$game_obj->load_from_db ($game_id);
	$game_obj->import_review_data ();

	// Generate the XML: for now, we assume that we always want to include the xbox.com info
	print "<?xml version='1.0' encoding='ISO-8859-1'?>\n\n";
	print $game_obj->generate_xml_str(TRUE, TRUE);

	$dbconn->close();
}
catch (Exception $e_obj)
{
	throw ($e_obj);
}
?>
