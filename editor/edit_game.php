<?php
// Simple script to read/write game-specific data on the XBIG database
require_once '../initialise.php';
require_once '../nusoap/nusoap.php';

$result_code 		= 0;
$result_str 		= '';
$result			= ''; 
$blog_id		= FALSE;
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

	// 2: process the request.  We may need to either display the game data (via blog_id) or 
	// save the data (via old_blog_id)

	$old_blog_id	= sanitise_str (read_form_input('old_blog_id', FALSE));
	$blog_id	= sanitise_str (read_form_input('blog_id', FALSE));

	if ($old_blog_id != FALSE)
	{
		$dbconn = xbig_dbconn();
		try
		{
			$game_id = get_game_id ($dbconn, FALSE, $old_blog_id);

			if (!$game_id)
				throw new Exception ('Game id not found');

			$game_obj = new game_template ($dbconn);
			$game_obj->load_from_db ($game_id);

			// Users can only edit their own reviews - we could add superuser permissions, but for the moment, direct access to
			// the DB will suffice...
			//$game_obj->get_review_data ($loginid);
			$game_obj->import_review_data ();

			// a: manually update the (potentially) altered fields
			// TODO?: implement regex-based form validation (data is automatically escaped by write_to_db anyway...)
			$game_obj->id_list['blog_id'] 		= read_form_input ('blog_id');

			$game_obj->name 			= read_form_input ('name');
			$game_obj->comment 			= read_form_input ('comment');

			$game_obj->tags_list['reviewed'] 	= read_form_input ('reviewed');
			$game_obj->tags_list['recommendation'] 	= read_form_input ('recommendation');
			$game_obj->tags_list['genre'] 		= read_form_input ('genre');
			$game_obj->tags_list['subgenre'] 	= read_form_input ('subgenre');

			$game_obj->metadata_list['inspired_by']	= read_form_input ('inspired_by');

			$str = read_form_input ('review_content');
			//if ($game_obj->review_content != $str)
			$game_obj->review_content = $str;

			$set_review_date 			= read_form_input ('update_review_date', FALSE);
			if ($set_review_date !== FALSE)
				$set_review_date = TRUE;

			// Push the update to the live server
			$str = serialize ($game_obj);
			if (!file_exists ('../.live'))
			{
				$client 	= new nusoap_client ($soap_server_url);
				$str 		= base64_encode ($str);
				$result 	= $client->call('update_game', array('game_obj_str' => $str, 'set_review_date' => $set_review_date));
				$result_str 	= "Home: data transmitted to live system via SOAP.  Result: $result";
			}
			else
			{
				$fn = $game_obj->id_list['blog_id'] . ".srz";

				// fpc returns the number of bytes written
				$result		= file_put_contents ($fn, $str);
				$result_str 	= "Live: serialized object written out to [$fn].  Result: $result bytes written";
			}

			// Set the last_updated timestamp and make sure the review content is committed...
			$game_obj->write_to_db (FALSE, TRUE, $set_review_date);
			$dbconn->commit();

			$result_code	= 1;
		}
		catch (Exception $e)
		{
			$dbconn->rollback();

			$result_code	= -1;
			$result_str = 	"Error processing request: " . $e->getMessage();

			$blog_id = $old_blog_id;
		}
		$dbconn->close();
	}

}
catch (Exception $e)
{
	$validated = FALSE;
	redirect ('index.php');
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

	xml_obj = new jxte ('../get_game_data.php?blog_id=<?php print $blog_id;?>');
	xml_obj.insert_node ('result_code', result_code);
	xml_obj.insert_node ('result_str', result_str);

	xml_obj.render_with_xsl_to_div ('form_div', '../../xsl/php_edit_game.xsl?test');
}

function form_submit ()
{
	// Check to see if we're still logged in or not.
	xml_obj = new jxte ('/php/editor/check_userid.php');

	if (xml_obj.get_node_value() == -1)
		alert ("The page has timed out: you need to log in before submitting this update!");
	else
		document.forms[0].submit();
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
