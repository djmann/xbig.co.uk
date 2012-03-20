<?php
// Simple script to read date-sorted information on XBIG reviews
require 'initialise.php';

header('Content-type: text/xml');

$news_id		= sanitise_str (read_form_input('news_id', 0));
$base_path		= '../..';

$session_obj    = new session_manager(TRUE);
$user_id        = $session_obj->get ('user_id', FALSE);
$session_obj->finish();

$news_obj = FALSE;
$user_obj = FALSE;

try
{
        // 1: Prep the database connection - with autocommit disbled
        $dbconn = xbig_dbconn ();

	// The user_obj is (optionally) used to verify the user's level of access...
	if ($user_id)
		$user_obj = new user_template ($dbconn, $user_id);

	$news_obj = new news_template ($dbconn, $news_id, $user_obj);

	// Generate the XML: for now, we assume that we always want to include the xbox.com info
	print "<?xml version='1.0' encoding='ISO-8859-1'?>\n\n";
	print "<test>" . $news_obj->render_to_xml() . "</test>";

	$dbconn->close();
}
catch (Exception $e_obj)
{
	throw ($e_obj);
}
?>
