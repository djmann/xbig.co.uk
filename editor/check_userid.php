<?php
// Simple script to check if the user is still logged in or not
require_once '../initialise.php';

// 1: Check the user's login status
$session_obj 	= new session_manager(TRUE);
$user_id 	= $session_obj->get ('user_id', -1);
$session_obj->finish();

header('Content-type: text/xml');
print "<?xml version='1.0' encoding='ISO-8859-1'?>\n\n";
print "<user_id>$user_id</user_id>\n";
?>
