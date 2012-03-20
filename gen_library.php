<?php
/* 
 * A set of standardised library functions for use across the website.
 * This includes:
 * 	session_manager
 * 	redirect()
 *	read_directory_files ()
 *	read_form_input ()
 *	extract_text ()
 *	sanitise_str ()
 *	generate_xml_node_str ()
 *
 */

class session_manager
{
	public $active = FALSE;

	public function __construct ($init = TRUE) {
		$this->start();
	}

	public function start ($regen_id = TRUE) {
		if ($this->active == TRUE)
			throw new Exception ("start: Session already active");
			
		if (!session_start())
			throw new Exception ("start: Session start failed");

		if ($regen_id) {
			if (!session_regenerate_id ())
				throw new Exception ("start: Session id regeneration failed");
		}

		$this->active = TRUE;
	}

	public function finish () {
		if ($this->active == FALSE)
			throw new Exception ("finish: No session active");

		session_write_close ();
	}

	public function destroy () {
		if ($this->active == FALSE)
			throw new Exception ("destroy: No session active");

		$_SESSION = array();
		session_destroy ();
	}

	public function set ($name, $value) {
		if ($this->active == FALSE)
			throw new Exception ("set: No session active");

		$_SESSION [$name] = $value;
	}

	public function get ($name, $default_value) {
		if ($this->active == FALSE)
			throw new Exception ("get: No session active");

		$ret_val = $default_value;
		if (array_key_exists ($name, $_SESSION))
			$ret_val = $_SESSION[$name];

		return $ret_val;
	}

	public function delete ($name) {
		if ($this->active == FALSE)
			throw new Exception ("unset: No session active");

		unset ($_SESSION[$name]);
	}
}

function redirect ($url)
{
	header ("Location: $url");
	flush ();
}

function read_directory_files ($file_pattern, $date_sort = FALSE)
{
	$files = glob($file_pattern);

	// Sort by date, newest first...
	if ($date_sort) 
		array_multisort (array_map('filemtime', $files), SORT_DESC, $files);

	return $files;
}
                
function read_form_input ($key_name, $default_value = '', $post_only = false)
{
	$retval = $default_value;
        
	if (!$post_only && !empty($_GET[$key_name]))
		$retval = $_GET[$key_name];
	elseif (!empty($_POST[$key_name]))
		$retval = $_POST[$key_name];

	// We may need to strip escaped characters
	if (is_string ($retval))
		$retval = stripslashes($retval);

	return $retval;
}

function extract_text (&$str, $regexp, $minimise_ws = FALSE)
{
	$m_list = array();
	$match  = '';

	// We only take the first match: anything else is thrown away!
	if (preg_match ($regexp, $str, $m_list) > 0)
		$match  = $m_list[1];
	else
		throw new Exception ("extract_text: failed to find '$regexp' in " . substr ($str, 0, 1024));

	// Strip out "internal" whitespace before returning
	// This is treated separately to stripping any pre/post whitespace
	if ($minimise_ws == TRUE)
		$match = preg_replace ('/\s+/', ' ', $match);

	// Trim pre/post white space
	return trim($match);
}

function sanitise_str ($str)
{
	// Remove any HTML tags/entities before removing non-alphanumeric characters
	$str = strip_tags($str);
	$str = preg_replace ("/&[^;\s]+;/", '', $str);
	$str = preg_replace("/[^a-zA-Z0-9_]/", '', $str);
	$str = strtolower($str);

	return $str;
}

function html_to_text (&$str, $max_length = FALSE)
{
	// Strip and truncate the content as appropriate
	$content_str = strip_tags ($str);
	$content_str = preg_replace ('/\s+/', ' ', $content_str);

	if ($max_length !== FALSE && (strlen($content_str) > $max_length))
	{
		// Make sure we don't truncate in the middle of a word!
		$content_str = substr ($content_str, 0, $max_length);
		$j = strrpos ($content_str, ' ');
		$content_str = substr ($content_str, 0, $j) . "...";
	}

	return $content_str;
}


function generate_xml_node_str ($node_name, $node_value = '', $attributes = FALSE, $escape_cdata = FALSE)
{
	$self_closed = FALSE;
	if ($node_value == '')
		$self_closed = TRUE;

	// Quick hack...
	$str = "<$node_name";

	if ($attributes)
	{
		foreach ($attributes as $key => $value)
		{
			// BASIC sanity proofing of the XML
			$value = str_replace (' & ', ' &amp; ', $value);
			$str .= " $key=\"$value\"";
		}
	}

	if ($self_closed == TRUE)
		$str .= " /";

	$str .= ">";

	if ($self_closed == FALSE)
	{
		if ($node_value != '')
		{
			if ($escape_cdata)
				$str .= "<![CDATA[$node_value]]>";
			else
				$str .= $node_value;
		}
		$str .= "</$node_name>";
	}

	return $str;
}

?>
