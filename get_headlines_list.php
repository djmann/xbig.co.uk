<?php
// Simple script to read date-sorted information on XBIG reviews
require 'initialise.php';

$review_offset		= (int) read_form_input('review_offset', 0);
$review_count 		= (int) read_form_input('review_count', 10);
$news_count		= (int) read_form_input('news_count', 10);
$base_path		= '../..';
$max_content_length 	= 500;

//0: print boilerplate
header('Content-type: text/xml');
print "<?xml version='1.0' encoding='ISO-8859-1'?>\n\n";

try
{
        // 1: Prep the database connection - with autocommit disbled
        $dbconn	= xbig_dbconn ();

	print "<news>\n";

	$qu = "select count(*) com_count, g.name, g.blog_id from games g, guest_comments gc where g.id = gc.game_id  and gc.status = 'published'" .
		" group by g.id order by gc.created desc limit $news_count";
	$rs_list = get_all_resultsets ($dbconn, $qu);

	$str 	= '';
	foreach ($rs_list as $rs)
		$str .= "\t<item blog_id='{$rs['blog_id']}' count='{$rs['com_count']}'><![CDATA[{$rs['name']}]]></item>\n";

	print "<new_comments expanded='yes'>\n";
	print $str;
	print "</new_comments>\n";

	// 2: get a list of recent reviews - 0..$news_count
	// All we need for these is the name and blog id
	$qu = "select g.blog_id, g.name from game_tags gt, games g where g.id = gt.game_id and gt.type='metadata' and gt.name='review_date' " .
		"and gt.value <> '' order by str_to_date(gt.value, '%D %b %Y') desc limit $news_count";
	
	$rs_list = get_all_resultsets ($dbconn, $qu);

	$str = '';
	foreach ($rs_list as $rs)
		$str .= "\t<item blog_id='{$rs['blog_id']}'><![CDATA[{$rs['name']}]]></item>\n";

	print "<new_reviews expanded='yes'>\n";
	print $str;
	print "</new_reviews>\n";

	// 2: get a list of recent releases - 0..$news_count
	// All we need for these is the name and blog id
	$qu = "select g.blog_id, g.name from game_tags gt, games g where g.id = gt.game_id and gt.type='metadata' and gt.name='release_date' " .
		"order by str_to_date(gt.value, '%D %b %Y') desc limit $news_count";
	
	$rs_list = get_all_resultsets ($dbconn, $qu);

	$str = '';
	foreach ($rs_list as $rs)
		$str .= "\t<item blog_id='{$rs['blog_id']}'><![CDATA[{$rs['name']}]]></item>\n";

	print "<new_releases expanded='yes'>\n";
	print $str;
	print "</new_releases>\n";

	// Semi-hack: to reduce processing overheads, we pull in the links XML and embed it in this document
	// Links are static and currently not stored in the database; instead, they're stuck in a simple XML file
	$str = file_get_contents ('../data/links.xml');
	print $str;
	print "</news>\n";

	$dbconn->close();
}
catch (Exception $e_obj)
{
	throw ($e_obj);
}
?>
