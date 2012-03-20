<?
require 'initialise.php';

$cache_filename = 'cache/filter_list.xml';
$str		= '';
$t_str		= '';
$cache_valid	= FALSE;
$start_time	= time();
$end_time	= FALSE;

$use_cache	= read_form_input('use_cache', 'TRUE');
$use_cache	= ($use_cache === 'TRUE' ? TRUE: FALSE);

try
{
	# 1: Prep the database connection - with autocommit disbled
	$dbconn = xbig_dbconn ();

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
	 		$str =	"<?xml version='1.0' encoding='ISO-8859-1'?>\n\n";
			$str .=	"<reviews>\n";
			$str .=	"<filters>\n";

			$qu = "select tag_name from filter_attributes where attribute_name='filter_position' order by value";
			$rs = get_all_resultsets ($dbconn, $qu);

			for ($i = 0; $i < sizeof ($rs); $i++)
			{
				// 1: get the attributes for the filter
				$tag_name = $rs[$i]['tag_name'];
				$qu = "select attribute_name, value from filter_attributes where tag_name = '$tag_name'";
				$rs2 = get_all_resultsets ($dbconn, $qu);

				$filter_attributes = array();
				for ($j = 0; $j < sizeof($rs2); $j++)
				{
					$fn = $rs2[$j]['attribute_name'];
					$fv = $rs2[$j]['value'];
					$filter_attributes[$fn] = $fv;
				}

				// 2: get each of the tags associated with the filter
				// the "safe_name" is automatically generated via a call to sanitise_str()
				$qu = "select value, count(*) tag_count from game_tags where type='tags' and name='$tag_name' and value <> 'TBC' group by value";
				$rs2 = get_all_resultsets ($dbconn, $qu);

				$t_str = '';
				for ($j = 0; $j < sizeof($rs2); $j++)
				{
					// We may need to modify the tag's name for viewing, so we keep the safe_name separate
					$tag_attributes = array();
					$tv 		= $rs2[$j]['value'];
					$tv_safe 	= $tv;
					$tc 		= $rs2[$j]['tag_count'];

					if ($tag_name = 'recommendation')
					{
						// HACK/SPECIAL CASE: we need to give the recommendation a numeric value, for ranking
						switch ($tv)
						{
							case 'Recommended':
								$tv = '[5/5] Recommended';
								break;
							case 'Favorite':
								$tv = '[4/5] Favorite';
								break;
							case 'Play':
								$tv = '[3/5] Play';
								break;
							case 'Try':
								$tv = '[2/5] Try';
								break;
							case 'Avoid':
								$tv = '[1/5] Avoid';
								break;
						}
					}

					$tag_attributes['name'] = $tv;
					$tag_attributes['safe_name'] = sanitise_str($tv_safe);

					$t_str .= generate_xml_node_str ('tag', $tc, $tag_attributes) . "\n";
				}

				// 3: write out the <filter> tag with all the <tag> nodes underneath it
				$str .= generate_xml_node_str ('filter', "\n" . $t_str, $filter_attributes);
			}

			$str .=	"</filters>\n";
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
	print "\n<!-- time: $start_time.  Source: " . ($cache_valid ? 'CACHE' : 'DB') . "; time taken: " . ($end_time - $start_time) . "-->\n";
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
