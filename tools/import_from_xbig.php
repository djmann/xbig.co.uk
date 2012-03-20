<?
require_once '../initialise.php';
require_once('../nusoap/nusoap.php');

// Required for sending data to the live platform when performing a synchronous update
$soap_server_url = 'http://www.xboxindiegames.co.uk/php/soap_server.php';

$newonly = FALSE;
$localonly = FALSE;

if (sizeof($argv) > 1) {
	foreach ($argv as $action) {
		switch ($action) {
			case 'newonly':
				$newonly = TRUE;
				break;
			case 'localonly':
				$localonly = TRUE;
				break;
		}
	}
	print "Set newonly: $newonly; localonly: $localonly\n";
}

// Get a timestamp which can be used to track updates
// Any game which does not have this timestamp at the end of the updates is assumed to have been deleted
$last_updated = time ();
try {
	print "Starting: getting game count...\n";
	$str = '';
	// 1: identify how many pages/games there are
	get_xbig_page (1, $str);

	$m_list 	= array();
	preg_match ('/<div class="Coverage">1 \- (\d+) of ([\d,]+).*?</', $str, $m_list);
	$games_per_page	= $m_list[1];
	$total_games	= str_replace(',', '', $m_list[2]);
	$total_pages	= ceil ($total_games / $games_per_page);

	print "$total_games games found over $total_pages pages.\n";

	print <<<EOM
Update status identifiers guide:
N:	New game being added to system		E:	Existing game being updated
						+:	Game data uploaded to LIVE
M:	Media downloaded to HOME		m:	Media uploaded to LIVE
%:	SOAP game update timeout/failure	#:	SOAP media update timeout/failure
?:	Processing failure
EOM;

	$dbconn = xbig_dbconn ();
	$start 	= 1;
	$end	= ($total_pages + 1);

	# 3: extract all the game info, download associated media and write out to the database
	for ($i = $start; $i < $end; $i++) {
		print "Processing page $i:\n";

		try {
			$err_list = array ();

			# Skip re-extracting the first page of results
			if ($i != 1)
				get_xbig_page ($i, $str);

			$game_list = get_xbig_game_list ($str);

			if (sizeof ($game_list) == 0)
				throw new Exception ("No games found\n");

			$j = 0;

			foreach ($game_list as $xbig_id => $url) {
				// $pos is part of the error-checking system: it indicates how far through processing we got
				$pos = 0;

				// We commit on a per-game basis
				try {
					// Simple monitoring capability
					$game_id 	= get_game_id ($dbconn, $xbig_id);

					// We may want to quit after processing new games: there's little point in refreshing everything, every single time
					if ($game_id && $newonly == TRUE)
						break 2;
					else if ($game_id)
						print "E";
					else
						print "N";

					$pos++;

					// 1: Parse the data from xbox.com
					// 	load_from_xbig automatically checks and loads "old" database content before parsing the input
					$game_obj = new game_template ($dbconn);
					$game_obj->load_from_xbig ($url, $xbig_id);
					$pos++;

					// Load the review content (not done by default to reduce overheads!)
					if ($game_id)
						$game_obj->import_review_data();
					$pos++;

					// 2: Serialise the object before writing to the database (to avoid primary key issues when inserting new games)
					// The string needs to be base64 encoded before sending via SOAP, to avoid problems with special characters
					$game_obj_str = base64_encode (serialize ($game_obj));
					$pos++;

					// 3: "push" the game's details to the live server
					if ($localonly == FALSE) {
						$client 	= new nusoap_client ($soap_server_url);

						$retries = 0;
						while (true) {
							$result	= $client->call('update_game', 
								array('game_obj_str' => $game_obj_str, 'set_review_date' => FALSE));

							if ($result == 'success') {
								print "+";
								break;
							} else {
								// QND failure retry mechanism // Sleep 10 seconds and try again
								if ($i == 0) {
									print "!";
									$retries++;
									sleep (10);
								} else {
									$err_list[] = "$j: LIVE SOAP failure when processing " .
											"{$game_obj->id_list['blog_id']}: $result";
									$err_list[] = "\t$client->response\n";
									print "%";

									break;
								}
							}
						}
						$pos++;
					}

					// 4: check to see if the game's media needs to be updated and/or uploaded
					// We do this regardless of whether the SOAP update has worked or not - the media will still be needed when the data
					// is finally uploaded!
					$base_dir = "/var/www";
					$upload_media = FALSE;

					if ($game_obj->validate_media_all ($base_dir) == FALSE) {
						// Local media needs to be updated...
						$game_obj->download_media_all ($base_dir);
						$upload_media = TRUE;
						print "M";
					} elseif ($localonly == FALSE) {
						// Local media ok; remote data out of sync
						$result = $client->call('validate_media', array('game_obj_str' => $game_obj_str));

						if ($result == FALSE)
							$upload_media = TRUE;
					}
					$pos++;

					if ($localonly == FALSE && $upload_media == TRUE) {
						foreach ($game_obj->media_list as $media) {
							$destination 	= $media['source'];
							$filename       = "$base_dir/$destination";

							$f_str          = @file_get_contents ($filename);
							$f_str_64       = base64_encode ($f_str);

							$retries = 0;
							while (true) {
								$result	= $client->call('upload_media_file', 
											array(
												'game_obj_str' => $game_obj_str,
												'destination' => $destination,
												'img_str' => $f_str_64
											)
										);

								if ($result == 'success') {
									break;
								} else {
									// QND failure retry mechanism // Sleep 10 seconds and try again
									if ($i == 0) {
										print "!";
										$retries++;
										sleep (10);
									} else {
										$err_list[] = "$j: LIVE SOAP failure while processing media $destination: $result\n";
										$err_list[] = "\t$client->response\n";
										print "m";
										break;
									}
								}
							}
						}
					}

					// 4: commit the update to the database
					$pos++;
					$game_obj->write_to_db ($last_updated);
					$dbconn->commit();

				} catch (Exception $e_obj) {
					// We skip individual game failures
					print "?$pos";
					$err_list[] = "$j: " . $e_obj->getMessage() . "\n";
					$dbconn->rollback();
					break;
					//print $dbconn->error . "\n";
				}

				// Spacer inbetween games
				print " ";

				$j++;
			}
		} catch (Exception $e_obj) {
			// We skip individual page parsing failures
			$err_list[] = "Page loop $i: " . $e_obj->getMessage() . "\n";
		}

		print "\n";
		// Dump out any errors once the page is fully processed
		if (sizeof ($err_list) > 0) {
			print "Errors encountered for page $i:\n";
			foreach ($err_list as $err)
				print "\t$err";
		}
	}
	print "\n";

	// 4: Identify newly deleted games and tag as appropriate
	// TODO: break out into separate script and get it working!
	if (FALSE) {
		print "Games parsed: checking for deleted games\n";
		$qu = "select id, blog_id, name from games where status <> 'Deleted' and last_updated < from_unixtime($last_updated)";

		$rs = get_all_resultsets ($dbconn, $qu);
		for ($i = 0; $i < sizeof ($rs); $i++) {

			$game_id	= $rs[0]['id'];
			$blog_id	= $rs[0]['blog_id'];
			$name		= $rs[0]['name'];

			try {
				// Actions for deleted games:
				//	1) Set/overwrite the "old_cost" tag
				//	2) Set a "deleted" cost for filtering/tagging purposes
				//	3) Set the game status to Deleted
				$del_queries = array
				(
					"delete from game_tags where name='old_cost' and type='metadata' and game_id = $game_id",
					"update game_tags set name='old_cost' and type='metadata' where name='cost' and type='tags' and game_id = $game_id",
					"insert into game_tags (game_id, name, type, value) values ($game_id, 'cost', 'tags', 'Deleted')",
					"update games set status='Deleted' where id = $game_id"
				);

				foreach ($del_queries as $qu)
				{
					$result = $dbconn->query ($qu);
	
					if ($result === FALSE)
						throw new Exception ("Deleted game cleanup: Unable to process [$qu]");
				}			

				$dbconn->commit();
				print "Marked $blog_id/$name as Deleted";
			} catch (Exception $e_obj) {
				$dbconn->rollback();
				throw ($e_obj);
			}
		}
	}

	# 5: Close the database connection
	$dbconn->close();
} catch (Exception $e_obj) {

	print "Main: " . $e_obj->getMessage() . "\n";
	print $dbconn->error . "\n";

	if ($dbconn->connect_errno == 0) {
		$dbconn->rollback();
		$dbconn->close();
	}
}

function get_xbig_page ($page_id, &$str)
{
	//$url = "http://marketplace.xbox.com/en-US/games/catalog.aspx?d=7&r=-1&g=-1&mt=32&ot=0&sb=0&rl=0&p=$page_id";
	//$url = "http://marketplace.xbox.com/en-GB/Games/XboxIndieGames?PageSize=90&SortBy=ReleaseDate&Page=$page_id";
	$url = "http://marketplace.xbox.com/en-GB/Games/XboxIndieGames?PageSize=90&SortBy=ReleaseDate&Page=$page_id";

	/*
	$str = file_get_contents ($url);

	if (!$str)
		throw new Exception ("get_xbig_page: page $page_id: unable to load $url\n");
	 */

	get_url_contents ($url, $str);
}

function get_xbig_game_list (&$str)
{
	$url_list 	= array();
	$xbig_list 	= array();

	$base_url	= "http://marketplace.xbox.com";

	//preg_match_all ('/<a class="ProductBox" href="(.*?)" title.*>/', $str, $url_list);
	preg_match_all ('/<li class="grid-6.*?">\s*<a href="(.*?)"/', $str, $url_list);

	$url_list = $url_list[1];
	for ($i = 0; $i < sizeof ($url_list); $i++) {
		$url 			= $base_url . $url_list[$i];
		$xbig_id 		= extract_text ($url, '{.*/(.*?)$}');
		$xbig_list[$xbig_id]	= $url;
	}

	return $xbig_list;
}
?>
