<?php
// Simple script to read date-sorted information on XBIG reviews
require 'initialise.php';

$graph_type		= read_form_input('graph_type', 'cost');
$output_format		= read_form_input('format', 'js');

try
{
	/*
	 * Javascript file:
	 * $graph_data = [[x_axis, my_count], ...];
	 */

	$bool 	= TRUE;
	$str	= '';

	if ($graph_type != FALSE)
	{
		// The query needs to return two fields:
		//	x_axis: 	the value being measured
		//	my_count:	count of the value
		switch ($graph_type)
		{
			// We skip timestamps of zero, as this indicates a game we don't have a release date for
			case 'release_count':
				$qu = "select " .
					"date_format(str_to_date(value, '%D %b %Y'), 'Week %V %X') csv_date, " .
					"unix_timestamp(str_to_date(date_format(str_to_date(value, '%D %b %Y'), 'Sunday %V %X'), '%W %V %X')) x_axis, " .
					"count(*) my_count " .
					"from game_tags where name='release_date' and value <> '1st Jan 1970' " .
					"group by x_axis order by x_axis;";
				break;

			case 'cost':
				$qu = "select " .
					"date_format(str_to_date(gt1.value, '%D %b %Y'), 'Week %V %X') csv_date, " .
					"unix_timestamp(str_to_date (date_format(str_to_date(gt1.value, '%D %b %Y'), 'Sunday %V %X'), '%W %V %X')) x_axis, " .
					"truncate(avg(gt2.value), 2) my_count " .
					"from game_tags gt1, game_tags gt2 where gt1.game_id = gt2.game_id and gt1.name='release_date' and gt2.name='cost' " .
					"and gt1.value <> '1st Jan 1970' " .
					"group by x_axis;";
				break;

			case 'rating_count':
				$qu = "select " .
					"date_format(str_to_date(gt1.value, '%D %b %Y'), 'Week %V %X') csv_date, " .
					"unix_timestamp(str_to_date(date_format(str_to_date(gt1.value, '%D %b %Y'), 'Sunday %V %X'), '%W %V %X')) x_axis, " .
					"truncate(avg(gt2.value), 2) my_count " .
					"from game_tags gt1, game_tags gt2 where gt1.game_id = gt2.game_id and gt1.name='release_date' and gt2.name='rating_count' " .
					"and gt1.value <> '1st Jan 1970' " .
					"group by x_axis;";
				break;

			case 'rating':
				$qu = "select " .
					"date_format(str_to_date(gt1.value, '%D %b %Y'), 'Week %V %X') csv_date, " .
					"unix_timestamp(str_to_date(date_format(str_to_date(gt1.value, '%D %b %Y'), 'Sunday %V %X'), '%W %V %X')) x_axis, " .
					"truncate(avg(gt2.value), 2) my_count " .
					"from game_tags gt1, game_tags gt2 where gt1.game_id = gt2.game_id and gt1.name='release_date' and gt2.name='rating' " .
					"and gt1.value <> '1st Jan 1970' " .
					"group by x_axis;";
				break;
	
			default:
				$bool = FALSE;
		}

	        $dbconn = xbig_dbconn ();
		$rs_list = get_all_resultsets ($dbconn, $qu);
		$dbconn->close();

		// We want to strip the first and last data entries, as they may be for incomplete weeks
		array_pop ($rs_list);
		array_shift ($rs_list);

		switch ($output_format)
		{
			case 'js':
				header('Content-type: text/javascript');

				$str = '$graph_data = [';

				// The timestamp needs to be converted from seconds to milliseconds
				foreach ($rs_list as $rs)
					$str .= "[{$rs['x_axis']}000, {$rs['my_count']}],";
		
				// It'd be nice to have a C-style '\0' hack to speed this up ;)
				$str = substr ($str, 0, -1);
				$str .= '];';
				break;

			case 'csv':
				header('Content-type: text/csv');
				$str = "'Date', '$graph_type (average/aggregate)'\n";
				foreach ($rs_list as $rs)
					$str .= "'{$rs['csv_date']}',{$rs['my_count']}\n";
				break;
		}

	}
	else
	{
		$bool = FALSE;
	}

	if ($bool == FALSE)
		$str = "Invalid graph request: $graph_type";

	print "$str\n";

}
catch (Exception $e_obj)
{
	throw ($e_obj);
}
?>
