<?php
// Simple script to read/write game-specific data on the XBIG database
require_once '../initialise.php';
require_once '../nusoap/nusoap.php';

$result_code 		= 0;
$result_str 		= '';
$result			= ''; 
$user_id		= FALSE;
$old_blog_id		= FALSE;
$validated		= TRUE;
//$soap_server_url	= 'http://localhost/php/soap_server.php';
$soap_server_url	= 'http://www.xboxindiegames.co.uk/php/soap_server.php';

try
{
	// 1: verify that the user is valid
	$session_obj 	= new session_manager(TRUE);
	$user_id 	= $session_obj->get ('user_id', FALSE);
	$session_obj->finish();

	if (!$user_id)
		redirect ('index.php');

	// Process the request
	$news_id	= sanitise_str (read_form_input('news_id', FALSE));

	// 1: get the existing news data OR a blank news object
	$dbconn = xbig_dbconn();
	$user_obj = new user_template ($dbconn, $user_id);
	$news_obj = new news_template ($dbconn, $news_id, $user_obj);

	// 2: perform any updates which are required
	//$game_obj->id_list['blog_id'] 		= read_form_input ('blog_id');

	$dbconn->close();
}
catch (Exception $e)
{
	$validated = FALSE;
	error_log ("Processing failure: {$e->getMessage()}");
	redirect ('view_content_lists.php');
}
?>

<?php
if ($validated == TRUE)
{
?>
<html>
<head>
<title>XBIG game manager: edit game</title>
<script src='/js/jxte.js'></script>

<script>
var gbl_timer = 0;

<?php
print "var result_code = $result_code;\n";
print "var result_str = '$result_str';\n";
?>

function page_load ()
{
	gbl_timer = Math.round(new Date().getTime() / 1000);

	xml_obj = new jxte ('../php/get_news_data.php?news_id=<?php print $news_id;?>');
	xml_obj.insert_node ('result_code', result_code);
	xml_obj.insert_node ('result_str', result_str);

	xml_obj.render_with_xsl_to_div ('form_div', '../xsl/php_edit_game.xsl?test');
}

function form_submit ()
{
	var my_time = Math.round(new Date().getTime() / 1000);
	if (my_time - gbl_timer > (15*60))
	{
		alert ("The page has probably timed out... make sure you're logged in before saving!");
		// And if you try again, it's on your head...
		gbl_timer = my_time;
	}
	else
	{
		document.forms[0].submit();
	}
}

</script>
</head>

<body onload='page_load()'>
<div id='form_div'>
</div>

</body>
</html>
<?php
}
?>
