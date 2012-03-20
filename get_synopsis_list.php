<?php
// Simple script to read date-sorted information on XBIG reviews
require 'initialise.php';

$page_offset		= (int) read_form_input('page_offset', 0);
$item_count 		= (int) read_form_input('item_count', 10);
$syn_type 		= read_form_input('syn_type', 'reviews');
$base_path		= '../..';
$max_content_length 	= 500;

//0: print boilerplate
header('Content-type: text/xml');
print "<?xml version='1.0' encoding='ISO-8859-1'?>\n\n";

try
{
        // 1: Prep the database connection - with autocommit disbled
        $dbconn = xbig_dbconn ();

	// 2a: get a count of all the games which fit the given criteria
	if ($syn_type == 'reviews')
		$qu = "select count(*) total_items from game_tags where type='metadata' and name='review_date' and value <> ''";
	else
		$qu = "select count(*) total_items from game_tags where type='metadata' and name='release_date' and value <> ''";

	$rs = get_single_resultset ($dbconn, $qu);
	$total_items = $rs['total_items'];

	// 2b: Grab all of the games which fit the given criteria/offset - the range is $page_offset..$item_count
	if ($syn_type == 'reviews')
	{
		$qu = "select game_id from game_reviews where status='published' order by created desc limit $page_offset, $item_count";
	}
	else
	{
		$qu = "select game_id from game_tags where type='metadata' and name='release_date' and value <> ''" .
			"order by str_to_date(value, '%D %b %Y') desc limit $page_offset, $item_count";
	}

	$str = '';

	$rs_list = get_all_resultsets ($dbconn, $qu);
	foreach ($rs_list as $rs)
	{
		$game_obj = new game_template ($dbconn);
		$game_obj->load_from_db ($rs['game_id']);

		// We also need to include a (truncated) copy of the review text
		// TODO: store review information in the database...
		$game_obj->import_review_data ($max_content_length);

		$str .= $game_obj->generate_xml_str(TRUE, TRUE, 1);
	}

	// Reuse the file array to get a total review count
	print "<synopsis type='$syn_type'>\n";
	print "\t<items total='$total_items'>\n";
	print $str;
	print "\t</items>\n";
	print "</synopsis>\n";

	$dbconn->close();
}
catch (Exception $e_obj)
{
	throw ($e_obj);
}
?>
