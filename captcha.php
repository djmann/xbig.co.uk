<?php
require 'initialise.php';

// Simple script to provide some security around comment management
// Note that this is intended to provide a low-level of security only: image-based captchas are easily defeated!

// 1: Extract information from client and initialise the session
$info_hash			= array();

// Used to validate requests
$info_hash['user_agent'] 	= $_SERVER['HTTP_USER_AGENT'];
$info_hash['user_ip']		= $_SERVER['REMOTE_ADDR'];
$info_hash['timestamp']		= time ();
// Standard info required for both image generation and comment parsing
$info_hash['req_type']		= read_form_input('req_type',		FALSE);
$info_hash['blog_id'] 		= read_form_input('blog_id',		FALSE);
// Information specific to comment parsing
$info_hash['captcha_value'] 	= read_form_input('captcha_value', 	FALSE);
$info_hash['name'] 		= read_form_input('name', 		FALSE);
$info_hash['email'] 		= read_form_input('email', 		FALSE);
$info_hash['comment'] 		= read_form_input('comment', 		FALSE);
$info_hash['callee'] 		= read_form_input('callee', 		FALSE);

// 2: process request
try
{
	// The session ID will be automatically
	$session = new session_manager();

	$dbconn = xbig_dbconn ();

	switch ($info_hash['req_type'])
	{
		case 'image':
			try
			{	
				// 1: Check to ensure that the default parameters are all alphanumeric only
				$regexp = "[^A-Za-z0-9_]";
				if (ereg($regexp, $info_hash['req_type']) || ereg($regexp, $info_hash['blog_id']))
					throw new Exception ("image: Invalid data passed to script");

				// 2: Get the game details
				// An exception will be thrown if no results are found
				$qu = "select id from games where blog_id='{$info_hash['blog_id']}'";
				$cur = get_single_resultset ($dbconn, $qu, TRUE);
				$info_hash['game_id'] = $cur['id'];

				// 3: choose a (randomly selected) image from the database
				// An exception will be thrown if no results are found
				$qu = "select * from comments_captcha order by rand() limit 1";
				$cur = get_single_resultset ($dbconn, $qu, TRUE);
				$info_hash['captcha_value'] = $cur['value'];

				// 4: store information about the client.  We manage this via standard PHP sessions
				$session->set ('info_hash', $info_hash);

				// 5: return image
				// For now, all images are assumed to have the mime type image/gif
				header('Content-type: image/gif');
				print $cur['src'];
			}
			catch (Exception $e)
			{
				throw $e;
			}
			break;

		case 'comment':
			try
			{
				// We need to validate the request prior to storing anything

				// 1: Make sure all mandatory fields are populated
				if ($info_hash['name'] == false || $info_hash['comment'] == false)
					throw new Exception ("comment: Invalid data passed to script (1)");

				// 2: Remove any HTML tags
				$info_hash['comment'] 	= strip_tags ($info_hash['comment']);
				$info_hash['name'] 	= strip_tags ($info_hash['name']);
				$info_hash['email'] 	= strip_tags ($info_hash['email']);

				// 3: ensure that the data provided doesn't exceed the field limits
				if (strlen($info_hash['name']) > 128 || strlen($info_hash['email']) > 128 || strlen($info_hash['comment']) > 1024)
					throw new Exception ("comment: Invalid data passed to script (2)");

				// 4: Validate the data passed to the script
				// For now, we DON'T enforce a timeout limit
				$old_info_hash = $session->get ('info_hash', FALSE);
				if ($old_info_hash == FALSE)
					throw new Exception ("comment: Old session doesn't exist");
		
				$list = array ('user_agent', 'user_ip', 'blog_id', 'captcha_value');
				foreach ($list as $info_key)
				{
					$old_str = $old_info_hash[$info_key];
					$new_str = $info_hash[$info_key];

					if (strcmp ($old_str, $new_str) != 0)
						throw new Exception ("comment: information mis-match");
				}

				// 5: Request has passed all checks: insert the comment into the database and destroy the session to prevent replay attacks
				$info_hash['game_id'] = $old_info_hash['game_id'];

				// The ID, status and created fields are automatically handled by the database
				$c_obj = new comment_template ($dbconn);
				$c_obj->set ('game_id', 	$info_hash['game_id']);
				$c_obj->set ('guest_name', 	$info_hash['guest_name']);
				$c_obj->set ('guest_email', 	$info_hash['guest_email']);
				$c_obj->set ('content', 	$info_hash['content']);

				$c_obj->write_to_db ();
				/*
				$qu = sprintf ("insert into guest_comments (game_id, guest_name, guest_email, content)" .
                                        " values (%d, '%s', '%s', '%s')",
                                                $info_hash['game_id'],
                                                $dbconn->real_escape_string($info_hash['name']),
                                                $dbconn->real_escape_string($info_hash['email']),
                                                $dbconn->real_escape_string($info_hash['comment'])
					);
				execute_query ($dbconn, $qu);
				 */

				// 6: Commit the updates and destroy the session to prevent replay attacks
				$dbconn->commit ();
				$session->destroy();

				// 7: Redirect the user as appropriate
				header ("Location: {$info_hash['callee']}");
			}
			catch (Exception $e)
			{
				$dbconn->rollback ();

				// We destroy the session on failure, to prevent replay attacks.
				$session->destroy();
				throw new Exception ("Failed to update database: " . $e->getMessage());
			}
			break;

		default:
			throw new Exception ("Unknown request type");
			break;
	}

	$session->finish ();
	$dbconn->close ();
}
catch (Exception $e)
{
	// Something went wrong...
	header('Content-type: text/html');
	print 
		"<html>" .
		"We were unable to process your comment: the CAPTCHA response may have been incorrect.  To return to the" .
		" previous page, please click Back on your browser or click <a href='{$info_hash['caller']}'>here</a>.<br>" .
		" If the problem continues to occur, please send a report to the email address listed in the contact section.<br>" .
		"</html>";
}

//
// FUNCTIONS GO HERE
//

function update_comments (&$config_hash, &$info_hash)
{
	// 1: generate XML
	$header = "<?xml version='1.0' encoding='ISO-8859-1'?>\n<comments>\n";
	$footer = "</comments>\n";
	$bool = true;


	// Simple obsfucation: we base64 encode personal data to prevent casual abuse
	// THIS WILL NOT STOP ABUSE HOWEVER
	// TODO: implement more robust encryption technique...
	$str = "<comment>\n" .

		// PERSONAL DATA
		"<user_agent><![CDATA[" . base64_encode ($info_hash['user_agent']) . "]]></user_agent>\n" .
		"<user_ip><![CDATA[" . base64_encode ($info_hash['user_ip']) . "]]></user_ip>\n" .
		"<email><![CDATA[" . base64_encode ($info_hash['email']) . "]]></email>\n" .

		// END-USER VISIBLE DATA
		"<date>" . date("d/m/Y") . "</date>\n" .
		"<time>" . date("H:i:s") . "</time>\n" .
		"<name><![CDATA[{$info_hash['name']}]]></name>\n" .
		"<content><![CDATA[{$info_hash['comment']}]]></content>\n" .
		"</comment>\n";

	// 2: write to file: if it exists, we need to "splice" the data in
	// We use a simple comments/section/reviewee.xml structure
	$file = "../comments/{$info_hash['section']}/{$info_hash['reviewee']}.xml";

	if (file_exists ($file) == false)
	{
		// More brute force: create the file and chmod it to ensure it's readable
		$str = $header . $str . $footer;

		// PHP 4 used char flags; PHP 5 uses longs...
		//file_put_contents($file, $str, 'a');
		if (file_put_contents($file, $str, FILE_APPEND))
			chmod ($file, 0777);
		else
			$bool = false;
	}
	else
	{
		// File exists.  We know what the footer's length is, so we open the file, seek to
		// just before the footer and overwrite it with the new content+footer
		// append the new entry, bob == uncle
		// BRUTE FORCE HACK: slightly nicer than re-reading and parsing the file on each request...
		$str = $str . $footer;

		// Is there a better way to negate a number? Doesn't look like it!
		$seek_len = (0 - strlen($footer));

		$fh = fopen ($file, 'r+');
		if ($fh)
		{
			fseek ($fh, $seek_len, SEEK_END);
			fwrite ($fh, $str);
			fclose ($fh);
		}
		else
		{
			print "Failure opening $file for appending: $php_errormsg";
			$bool = false;
		}
	}

	return $bool;
}

function validate_client_response (&$config_hash, &$info_hash)
{
	$retval = false;

	$client_list	= read_client_list ($config_hash);
	$client_id	= $info_hash['client_id'];

	// QND...
	if (array_key_exists ($client_id, $client_list))
	{
		$stored 	= (int) $client_list[$client_id];
		$received 	= (int) $info_hash['captcha_response'];
		//print "Stored: $stored<br>";
		//print "Received: $received<br>";

		if ($stored == $received)
			$retval = true;
	}
	else
	{
		print "Key $client_id not found<br>";
	}

	return $retval;
}
?>
