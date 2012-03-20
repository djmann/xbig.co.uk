<?php
// Simple script to read date-sorted information on XBIG reviews
require '../initialise.php';

// Simple access control: we wipe the existing session (if any) before beginning processing
$session_obj = new session_manager(TRUE);
$session_obj->destroy();

$loginid	= sanitise_str (read_form_input('loginid', FALSE));
$password	= sanitise_str (read_form_input('password', FALSE));
$validated	= FALSE;

$r_list = FALSE;
$rs = FALSE;
$qu = '';

if ($loginid && $password)
{
	try
	{
		// 1: Prep the database connection - with autocommit disbled
		$dbconn = xbig_dbconn ();

		$user_obj = new user_template ($dbconn);

		$user_id = $user_obj->check_loginid ($loginid);

		if ($user_id !== FALSE)
			$user_obj->read_from_db ($user_id);
		else
			throw new Exception ("Unknown userid");

		if ($user_obj->check_password ($password) === TRUE && $user_obj->check_status() == TRUE)
		{
			$validated = TRUE;

			// Set session info and redirect to the main page
			$session_obj = new session_manager(TRUE);
			$session_obj->set ('user_id', $user_obj->get('id'));
			$session_obj->finish();

			// Log that the user has accessed the system
			$timestamp = $user_obj->write_login_timestamp();
			error_log ("User [$loginid] logged into the system");
		}

		$dbconn->close();
	}
	catch (Exception $e_obj)
	{
		throw ($e_obj);
	}
}

// Push them to the appropriate page if validation has been completed
if ($validated == TRUE)
	redirect ('view_content_lists.php');

?>
<html>
<head>
<title>XBIG game manager: login</title>
</head>

<body>

<form method='POST' action='index.php'>
<center>
<table style='border: solid black 1px;'>
<tr>
	<th align='left'>User Loginid</th>
	<td><input name='loginid' type='text'></td>
</tr>
<tr>
	<th align='left'>Password</th>
	<td><input name='password' type='password'></td>
</tr>
</table>

<p>
<input type='submit' value='Login'>
</p>

<?php
	if ($loginid && $validated == FALSE)
		print "<p><font color='#FF0000'>Login failed!</font></p>\n";
?>
</center>
</body>
</html>
