<?php
// Simple script to read date-sorted information on XBIG reviews
require 'initialise.php';

$blog_id		= read_form_input('blog_id', 'evacuation');

//0: print boilerplate
header('Content-type: text/xml');
print "<?xml version='1.0' encoding='ISO-8859-1'?>\n\n";

try
{
	/*
	 * XML structure
		<comments>
			<comment blog_id=''>
				<guest_name></guest_name>
				<created></created>
				<content></content>
			</comment>
		</comments>
	 *
	 */

	$str = '';

	if ($blog_id != FALSE) {
	        $dbconn = xbig_dbconn ();

		$blog_id = $dbconn->real_escape_string($blog_id);
		$qu = "select gc.id from games g, guest_comments gc where g.id = gc.game_id and gc.status='published' and g.blog_id = '$blog_id' " .
			"order by gc.created";

		$rs_list = get_all_resultsets ($dbconn, $qu);
		foreach ($rs_list as $rs) {
			$c_obj = new comment_template ($dbconn, $rs['id']);
			$str .= $c_obj->render_to_xml () . "\n";
		}

		$dbconn->close();
	}

	print "<comments>\n$str</comments>\n";

} catch (Exception $e_obj) {
	throw ($e_obj);
}
?>
