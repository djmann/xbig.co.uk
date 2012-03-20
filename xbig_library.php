<?
function xbig_dbconn ($autocommit = FALSE)
{
        $dbconn = new mysqli(MYSQL_SERVER, MYSQL_USER, MYSQL_PASSWD, MYSQL_DB);
	
        if (!$dbconn || $dbconn->connect_errno > 0)
                throw new Exception ("xbig_dbconn: failed to open database connection");

        $dbconn->autocommit ($autocommit);

        return $dbconn;
}

function execute_query (&$dbconn, $qu, $return_id = FALSE)
{
	$record_id = -1;

	if ($dbconn->query($qu) == FALSE) {
		throw new Exception ("execute_query: Unable to process \"$qu\": {$dbconn->error}");
	}

	if ($return_id)
		$record_id = $dbconn->insert_id;

	return $record_id;
}

function get_single_resultset (&$dbconn, &$qu, $flag_no_records = FALSE)
{
	// For reasons best known to the mysqli developers, you can only have one active cursor/statement open at a time.
	// We therefore have to reset() statements and close cursors after use.  Fortunately, the resultset from a cursor isn't
	// affected when the cursor is closed (and similar applies to bound variables after a statement reset)!

	$cursor	= $dbconn->query ($qu);
	$rs	= FALSE;

	if ($cursor == FALSE)
		throw new Exception ("get_single_resultset: Unable to process \"$qu\"");

	if ($cursor->num_rows > 0)
		$rs = $cursor->fetch_assoc();

	$cursor->close();

	if ($flag_no_records && $rs == FALSE)
		throw new Exception ("get_single_resultset: No records returned for \"$qu\"");

	return $rs;
}

function get_all_resultsets (&$dbconn, &$qu, $flag_no_records = FALSE)
{
	$cursor	= $dbconn->query ($qu);
	$rs	= FALSE;
	$all_rs	= array();

	if ($cursor == FALSE)
		throw new Exception ("get_all_resultsets: Unable to process \"$qu\"");

	if ($cursor->num_rows > 0)
	{
		while ($rs = $cursor->fetch_assoc())
			$all_rs[] = $rs;
	}

	$cursor->close();

	if ($flag_no_records && $rs == FALSE)
		throw new Exception ("get_all_resultsets: No records returned for \"$qu\"");

	return $all_rs;
}

function get_game_id ($dbconn, $xbig_id = FALSE, $blog_id = FALSE)
{
	$game_id 	= FALSE;
	$qu 		= '';
	
	if ($xbig_id != FALSE)
		$qu = "select id from games where xbig_id = '$xbig_id'";
	elseif ($blog_id != FALSE)
		$qu = "select id from games where blog_id = '$blog_id'";
	else
		throw new Exception ("get_game_id: key not specified");

	$rs = get_single_resultset ($dbconn, $qu);

	if ($rs)
		$game_id = $rs['id'];

	return $game_id;
}

function xbig_check_cache ($dbconn, $cache_filename)
{
	$str		= '';
	$qu		= '';
	$rs		= FALSE;
	$stat_list	= FALSE;
	$cache_valid	= FALSE;
	$mtime		= 0;
	$ctime		= 0;
	$exp_time  = (24*60*60);	// We auto-expire the cache after 24 hours

	if (file_exists ($cache_filename))
	{
		$stat_list	= stat ($cache_filename);
		$mtime		= $stat_list['mtime'];

		$ctime		= time();


		// We auto-purge caches after 24 hours; if the cache is still time-valid, then we check the database for updates
		if (($mtime + $exp_time) > $ctime)
		{
			// Simple check: the global_config.last_updated field holds a (unix) timestamp indicating the most recent
			// update to the database
			$qu = "select count(*) my_count from global_config where name='last_updated' and config_value > $mtime";

			$rs = get_single_resultset ($dbconn, $qu);

			// If we get a result, then the cache is out of date...
			if ($rs != FALSE && $rs['my_count'] != 1)
				$cache_valid = TRUE;
		}
	}

	return $cache_valid;
}

function write_log ($dbconn, $entry)
{
	// The timestamp will be auto-set by the database
	$qu = sprintf ("insert into audit_trail (entry) values ('%s'), $dbconn->real_escape_string($entry)");
	execute_query ($dbconn, $qu);
}

function get_url_contents ($url, &$string)
{
        // For some reason, PHP's internal URL-parsing functions aren't behaving reliably.  We therefore fall back to wget
        $buffer = FALSE;
        $result = 0;
        $url = escapeshellarg ($url);
        exec ("wget --quiet -O - $url", $buffer, $result);

        if ($result !== 0)
                throw new Exception ("get_url_contents: unable to return data from [$url]");

        $string = implode ("\n", $buffer);
}
?>
