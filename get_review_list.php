<?
require 'initialise.php';

$cache_filename = 'cache/review_list.xml';
$cache_valid	= FALSE;
$str		= '';
$start_time	= time();
$end_time	= FALSE;

$use_cache	= read_form_input('use_cache', 'TRUE');
$use_cache	= ($use_cache === 'TRUE' ? TRUE: FALSE);

try
{
	$dbconn	= xbig_dbconn ();

	// We check to see if a) we have a cached copy of the data and b) if it's still valid
	// Hopefully this should reduce the processing overheads for most requests!
	if ($use_cache)
		$cache_valid	= xbig_check_cache ($dbconn, $cache_filename);

	if ($cache_valid === TRUE)
	{
		$str = file_get_contents ($cache_filename);
	}
	else
	{
		try
		{
			$str = 	"<?xml version='1.0' encoding='ISO-8859-1'?>\n";
			$str .=	"<reviews><games>\n";

			// 1: load a list of game ids and then parse the games on a per-title basis
			$qu = "select id from games";
			$rs = get_all_resultsets ($dbconn, $qu);

			for ($i = 0; $i < sizeof ($rs); $i++)
			{
				$game_id = $rs[$i]['id'];

				$game_obj = new game_template ($dbconn);
				$game_obj->load_from_db ($game_id);

				$str .= $game_obj->generate_xml_str(FALSE, FALSE, 0);
			}
			$str .=	"</games>\n";
			$str .=	"</reviews>\n";

			file_put_contents ($cache_filename, $str);
		}
		catch (Exception $e_obj)
		{
			throw $e_obj;
		}
	}

	# 5: Close the database connection
	$dbconn->close();

	header('Content-type: text/xml');
	print $str;

	$end_time = time();
	print "\n<!-- source: " . ($cache_valid ? 'CACHE' : 'DB') . "; time taken: " . ($end_time - $start_time) . "-->\n";
}
catch (Exception $e_obj)
{
	if ($dbconn->connect_errno == 0)
	{
		$dbconn->rollback();
		$dbconn->close();
	}

	print "Main: " . $e_obj->getMessage() . "\n";
	print $dbconn->error . "\n";
}
?>
