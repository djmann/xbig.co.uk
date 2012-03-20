<?php
require_once('initialise.php');
require_once('nusoap/nusoap.php');

// Create the client instance
$client = new nusoap_client ('http://www.xboxindiegames.co.uk/php/soap_server.php');
//$client = new nusoap_client ('http://localhost/php/soap_server.php');

try
{
	$dbconn = xbig_dbconn ();

	$game_id = get_game_id ($dbconn, FALSE, 'platformancecastlepain');

	if ($game_id == FALSE)
		throw new Exception ("Blog ID 'platformancecastlepain' not found");

	$game_obj = new game_template ($dbconn);
	$game_obj->load_from_db ($game_id);
	$game_obj->import_review_data ();

	$str = base64_encode (serialize ($game_obj));

	// Call the SOAP method
	$result = $client->call('update_game', array('game_obj_str' => $str));
	print "update_game:\n$result\n{$client->response}\n";

	/*
	print "==============================================\n";

	$result = $client->call('validate_media', array('game_obj_str' => $str));
	print "validate_media: $result; {$client->response}\n";
	print "{$client->response}\n";
	die;
	 */
}
catch (Exception $e)
{
	print "Error submitting update_game SOAP request\n";
}

/*
try
{
	$filename 	= "/tmp/test.jpg";
	$f_str 		= file_get_contents ($filename);
	$f_str_64	= base64_encode ($f_str);
	$result = $client->call('add_image', array(
						'source' => "images/test.jpg",
						'img_str' => $f_str_64
					)
				);

	// Display the result
	print "add_image: $result\n";
}
catch (Exception $e)
{
	print "Error submitting add_image SOAP request\n";
}
*/

?>

