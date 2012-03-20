<?

/*
 * The game_template class is intended to act as an self-contained, atomic representation of a game - i.e. all the data held in the database for the game
 * is automatically loaded into the object when it is initialised, and the object can then be serialised and used to synchronise data between the 
 * "live" and "offsite" systems.
 *
 */

class gtn
{
	public $type		= 'game';

	// Game-specific information: each game is associated with several entities (developer, reviews, media, etc) and also has several metadata
	// values/tags associated with it
	public $media_list	= FALSE;
	public $review_list	= FALSE;
	public $comment_list	= FALSE;
	public $tags_list	= FALSE;

	// TODO: add "updated" as a parent value so that we can detect if a given entity has been updated
	// I suppose this is where a variation on the Observer pattern could be useful...

	public function initialise_data ()
	{
	    	// Unlike the other entities, games have multiple types of metadata associated with them
    		// We therefore store the information in a two-dimensional array
    		$this->data 			= array();
		$this->media_list		= array();
		$this->review_list		= array();
		$this->comment_list		= array();
    		$this->tags_list 		= array();

		// The data and tags sub-arrays need to have default values set...
		$this->data['id']				= 0;
		$this->data['developer_id']			= 0;
		$this->data['blog_id']				= '';
		$this->data['xbig_id']				= '';
		$this->data['name']				= '';
		$this->data['unicode_name']			= '';
		$this->data['comment']				= '';
		$this->data['status']				= '';
		$this->data['last_updated']			= 0;
		
		// keys: a set of keys which can be used to uniquely identify the game; also used to speed up searching
		$this->tags_list['keys']		= array();
		$this->tags_list['keys']['date_key']		= '';
		$this->tags_list['keys']['name_key']		= '';
		$this->tags_list['keys']['sort_key']		= '';

		// tags: a list of searchable metadata...
		$this->tags_list['tags']		= array();
		$this->tags_list['tags']['all']			= 'Show All';	// Semi-hack: used as a "select all" wildcard
		$this->tags_list['tags']['category']		= '';
		$this->tags_list['tags']['cost']		= '';
		$this->tags_list['tags']['developer']		= '';
		$this->tags_list['tags']['genre']		= '';
		$this->tags_list['tags']['reviewed']		= 'Unreviewed';
		$this->tags_list['tags']['recommendation']	= 'Unreviewed';
		$this->tags_list['tags']['subgenre']		= '';

		// metadata: general, unsearchable metadata
		$this->tags_list['metadata']	= array();
		$this->tags_list['metadata']['inspired_by']		= '';
		$this->tags_list['metadata']['release_date']		= '';
		$this->tags_list['metadata']['review_date']		= '';	// Should be set to the date of the *oldest* available review
		$this->tags_list['metadata']['filesize']		= '';
		$this->tags_list['metadata']['boxart']			= 'No';
		$this->tags_list['metadata']['screenshot_count']	= '0';

		// xbig_metadata: metadata extracted from the xbox.com website.  Theoretically, could be merged with metadata; the XSL stylesheets
		// would also need to be updated, however...
		$this->tags_list['xbig_metadata']	= array();
		$this->tags_list['xbig_metadata']['xbig_description']		= '';
		$this->tags_list['xbig_metadata']['xbig_classification']	= '';
		$this->tags_list['xbig_metadata']['rating']			= 0;
		$this->tags_list['xbig_metadata']['rating_count']		= 0;

		// capabilities: the xbox.com website has switched to giving games a list of (0,n) capabilities
		// We therefore don't bother pre-initialising this!
		$this->tags_list['capabilities']	= array();

		$this->updated 	= FALSE;
	}

	public function __sleep ()
	{
		print "Need to check if referenced objects need to be explicitly serialised or not...\n";
		die;
		parent::__sleep ();
	}

	public function load_from_db ($game_id)
	{
		$this->check_dbconn ();

		// Load the core information from the [Games] table
		$qu = sprintf ("select * from games g where g.id = %d", $game_id);
		
		// get_single_resultset will automatically throw an exception if no records are found
		$rs = get_single_resultset ($this->dbconn, $qu, TRUE);

		foreach ($rs as $key => $value)
			$this->set ($key, $value);

		// Handle special cases: some information from the [Games] table is also stored as metadata for indexing purposes
		$this->set_tag ('id', 'blog_id', $rs['blog_id']);
		$this->set_tag ('id', 'xbig_id', $rs['xbig_id']);

		// For the moment, we don't bother treating the developer as a separate entity...
		// We just store the developer's id in the $data array and stick their name in the $tags array for searching/display purposes
		$qu = sprintf ("select name from developers d where d.id = %d", $this->get ('developer_id'));
		$rs = get_single_resultset ($this->dbconn, $qu, TRUE);
		$this->set_tag('tags', 'developer', $rs['name']);

		// Read data from the game_tag table and split into the appropriate arrays
		$qu = sprintf ("select type, name, value from game_tags where game_id = %d", $game_id);
		$rs_list = get_all_resultsets ($this->dbconn, $qu);

		foreach ($rs_list as $rs)
			$this->set_tag ($rs['type'], $rs['name'], $rs['value']);

		// Read from the game_media table and store in the media_list table
		// HACK: the data from the table is loaded directly into the media_list array!
		// TODO: convert into true entities?
		$qu = "select * from game_media where game_id = $game_id";
		$this->media_list = get_all_resultsets ($this->dbconn, $qu);

		// Read the review(s) associated with the game
		$this->load_reviews_from_db ();

		// Read all the comment(s) associated with the game
		$this->load_comments_from_db ();

		// Reset the "updated" flag, as we've only read data from the database
		$this->updated = FALSE;
	}

	public function load_reviews_from_db ()
	{
		// The load_*_from_db are virtually identical, but the overhead/complexity of a system to auto-parse entity requests is fairly high...
		$this->check_dbconn ();

		$qu = sprintf ("select id from game_reviews where game_id = %d", $this->get ('id'));
		$rs_list = get_all_resultsets ($this->dbconn, $qu);

		foreach ($rs_list as $rs)
			$this->set_review (new review_template ($this->dbconn, $id));
	}

	public function load_comments_from_db ()
	{
		// The load_*_from_db are virtually identical, but the overhead/complexity of a system to auto-parse entity requests is fairly high...
		$this->check_dbconn ();

		$qu = sprintf ("select id from game_comments where game_id = %d", $this->get ('id'));
		$rs_list = get_all_resultsets ($this->dbconn, $qu);

		foreach ($rs_list as $rs)
			$this->set_comment (new comment_template ($this->dbconn, $id));
	}

	// We need to handle new child-entitles being added to the game
	// For the moment, we assume that there is only ever *ONE* blank entity (review/comment/etc - the ID will be zero) 
	// associated with a game
	// blank entities will auto-skip updating the database, as their $updated flag will be FALSE
	public function set_review ($review_obj)
	{
		$id = $review_obj->get('id');

		$this->review_list[$id] = $review_obj;

		if ($review_obj->updated == TRUE)
			$this->updated = TRUE;
	}

	public function set_comment ($comment_obj)
	{
		$id = $comment_obj->get('id');

		$this->comment_list[$id] = $comment_obj;

		if ($comment_obj->updated == TRUE)
			$this->updated = TRUE;
	}

	public function get_review ($review_id)
	{
		return $this->review_list[$review_id];
	}

	public function get_comment ($comment)
	{
		return $this->comment_list[$comment_id];
	}

	public function set_tag ($tag_type, $tag_name, $tag_value)
	{
	    	// For the moment, we don't bother trying to validate the values...
	    	$this->tags_list[$tag_type][$tag_name] = $tag_value;

			$this->updated = TRUE;
	}

	public function get_tag ($tag_type, $tag_name, $default_value)
	{
		$retval = $default_value;

		if (array_key_exists ($tag_type, $this->tags_list) === TRUE)
			if (array_key_exists ($tag_name, $this->tags_list[$tag_name]) === TRUE)
					$retval = $this->tags_list[$tag_type][$tag_name];

		return $retval;
	}

	public function load_from_xbig ($url, $xbig_id)
	{
		// Function to load the data from the xbox.com website
		// XBIG games can also have zero or more metatags associated with them
		// These need to parsed and allocated to the appropriate tags[] or metadata[] attribute
		// Sadly, the xbox.com HTML isn't XHTML (there are unclosed tags, etc), so we can't simply use an XML parser to process the data.
		// Instead, we use a set of customised regex's to pull out the data
		//
		// NOTE: this method checks for the presence of media (boxart, screenshots) but does NOT download theml; validation/downloading of the 
		// media is the responsibility of the caller

		$this->check_dbconn ();

		get_url_contents ($url, $str);

		// Check to see if the game exists...
		$game_id = get_game_id ($this->dbconn, $xbig_id);

		if ($game_id)
		{
			// Load the current values from the database: these will be overwritten as appropriate
			$this->load_from_db ($game_id);
		}
		else
		{
			// The game is new to xblig.co.uk: populate the "static" values for the game
			// (i.e. these values should not change in the future)

			// We need to ensure the name is searchable/english-readable
			// Simple check: does the name contain any alphanumeric characters
			// \w also matches on unicode characters, so we need to explicitly use a-zA-Z0-9
			$unicode_name 	= extract_text ($str, '{<title>\s*(.*?) - Xbox.com}');
			$name 		= $unicode_name;
			if (!preg_match ('{^[a-zA-Z0-9]}', $unicode_name))
				$name = "ZZ (Non-ASCII title) - {$this->tags_list['developer']}: {$this->get_tag ('keys', 'date_key')}";

			$dev 		= extract_text ($str, '{<label>Developer:<\/label> (.*?)<\/li>}');
			$filesize	= extract_text ($str, '{<label>Size:<\/label> (.*?)<\/li>}'));

			// Currently, xbox.com outputs the date as "dd/mm/yyyy": for strtotime to work, it needs to be
			// in the format "dd-mm-yyyy", as otherwise the functions assumes it's US-formatted...
			// We also need a sortable version of the release date for several of the fields below
			$t_str 		= extract_text ($str, '{<label>Release date:<\/label> (.*?)<\/li>}');
			$t_str 		= str_replace ('/', '-', $t_str);
			$release_ts	= strtotime ($t_str);
			$release_date	= date ('jS M Y', $release_ts);

			// Further keys for searching
			// (TODO: check and see if sort_key/name_key can be merged...)
			$date_key	= date ('Ymd', $release_ts);
			$name_key 	= sanitise_str ($name) . $date_key;
			$sort_key	= $name_key;

			// We then create a default (and unique) "blog_id" value by combining the *sanitised* name with the datestamp
			// This can be manually overriden at a later date
			$blog_id	= sanitise_str ($name) . "_zz_" . $date_key;

			$this->set ('name',			$name);
			$this->set ('unicode_name',		$unicode_name);

			$this->set_tag ('id', 			'xbig_id', 	$xbig_id);
			$this->set_tag ('id', 			'blog_id',	$blog_id);
			$this->set_tag ('keys', 		'date_key', 	$date_key);
			$this->set_tag ('keys', 		'name_key',	$name_key);
			$this->set_tag ('keys', 		'sort_key',	$name_key);
			$this->set_tag ('tags', 		'developer',	$dev);
			$this->set_tag ('metadata', 		'release_date',	$release_date);
			$this->set_tag ('metadata', 		'filesize',	$filesize);
			$this->set_tag ('xbig_metadata', 	'url', 		$url);

			// Capabilities are slightly trickier than the other tags, as it's essentially a variable-length list which could
			// potentially hold anything.
			// To keep things simple, the tag name is the capability name with all non-word characters (\W) stripped out
			$m_list 	= array();
			$t_str 		= extract_text ($str, '{class="capabilities">(.*?)</div>}s');
			preg_match_all ('{<li>(.*?)</li>}s', $t_str, $m_list);
			$m_list 	= $m_list[1];
			foreach ($m_list as $capability)
			{
				$t_str = strtolower (preg_replace ('/\W/', '', $capability));
				$this->set_tag ('capabilities',	$t_str,		$capability);
			}

		}

		// Now we have the core information defined, we now process the "variable" data from the HTML

		$category 		= extract_text ($str, 			'{<label>Genre:</label>\s*(.*?)<}');

		// NOTE: We hack the " MSP" currency note onto the end of the cost
		$cost 			= extract_text ($str, 			'{<span class="MSPoints.*?">(\d+)</span>}s') . ' MSP';

		// The description from xbox.com needs to be split into two, as it holds the description and the classification
		$tmp_description	= extract_text ($str, 			'{<div id="overview1".*?<p>(.*?)</p>}s');
		$xbig_description	= extract_text ($tmp_description, 	'{.*?\. (.*)}s', TRUE);
		$xbig_classification	= extract_text ($tmp_description, 	'{(^This.*?\.)}s', TRUE);

		// NOV 2010 UPDATE: Extracting a game's rating is now a bit trickier, as it's no longer explicitly displayed as a number.  Instead, 
		// it has to be reverse engineered from the number *and* type of "star" images on-screen...
		$t_str 		= extract_text ($str, '{class="UserRatingStarStrip">(.*?</div>.*?)</div>}s');
		$rating 	= 0;
		$rating_count	= 0;
		if (preg_match_all ('{<span class="Star Star(\d)">}s', $t_str, $m_list) !== FALSE)
		{
			$m_list 	= $m_list[1];
			foreach ($m_list as $i)
			{
				switch ($i)
				{
					case '4': 	$rating += 1; 		break;
					case '3': 	$rating += 0.75; 	break;
					case '2': 	$rating += 0.5; 	break;
					case '1': 	$rating += 0.25; 	break;
					case '0': 				break;
					default: 				error_log ("Unknown Star Rating '$i'\n");
				}
			}

			$rating_count = extract_text ($t_str, '{.*(\d+)\s*$}s');
		}
		else
		{
			$this->throw_exception ('Failed to extract rating data');
		}

		// Each game can have a "boxart" image and a set of sample screenshots associated with it.
		// To keep things simple, we wipe/recreate on every single pass
		$this->media_list 				= array();

		// NOV 2010: quick hack to identify image URLs...
		$boxart = 'No';
		$screenshot_count = 0;

		if (preg_match_all ("{\"(http://download.xbox.com.*\.jpg)\"}", $str, $m_list))
		{
			$m_list 	= $m_list[1];

			foreach ($m_list as $url)
			{
				$t_str = extract_text ($url, '{(\w+?)\d*.jpg$}');
				switch ($t_str)
				{
					case 'xboxboxart':
						// Special case: The boxart is shown twice on the page, we only want to process it once!
						if ($boxart == 'No')
						{
							// Note that "source" = LOCAL name for the image (i.e. what xboxindiegames.co.uk will refer to)
							$boxart = 'Yes';

							$img_name 	= 'boxart';
							$source		= "images/xna_media/{$xbig_id}/{$img_name}.jpg";

							$this->media_list[] = array (
									'type' 		=> 'XBIG Media',
									'label' 	=> 'Game Boxart',
									'description' 	=> 'Image taken from XNA website',
									'source' 	=> $source,
									'url'		=> $url
								); 
						}
						break;

					case 'screen':
						// Increment the overall image count
						$screenshot_count++;

						$img_name	= extract_text ($url, '{(\w+?).jpg$}');
						$source		= "images/xna_media/{$xbig_id}/{$img_name}.jpg";
						$this->media_list[] = array (
								'type' 		=> 'XBIG Media',
								'label' 	=> 'Game Screenshot',
								'description' 	=> 'Image taken from XNA website',
								'source' 	=> $source,
								'url'		=> $url
							); 
						break;
				
					case 'webboxart':
						// Not currently needed
						break;

					default: 	print "Unknown Media Type '$t_str'\n";
				}
			}

		}
		else
		{
			$this->throw_exception ('Failed to extract media data');
		}

		// Finally, set the tag information...
		$this->set_tag ('tags', 		'category',		$category);
		$this->set_tag ('tags', 		'cost',			$cost);
		$this->set_tag ('metadata', 		'boxart',		$boxart);
		$this->set_tag ('metadata', 		'screenshot_count',	$screenshot_count);
		$this->set_tag ('xbig_metadata', 	'xbig_description',	$xbig_description);
		$this->set_tag ('xbig_metadata', 	'xbig_classification',	$xbig_classification);
		$this->set_tag ('xbig_metadata', 	'rating',		$rating);
		$this->set_tag ('xbig_metadata', 	'rating_count',		$rating_count);
	}

	// Function to check if a given media file already exists on the local file system and is a valid image file!
	// For the moment, we assume that all media items are images which can be validated via ImageMagick's "identify" command
	// We also assume that the filename given has been correctly mapped (e.g. ../images)
	public function validate_media_file ($filename, $check_content = TRUE)
	{
		// 1: check to see if the image exists
		$retval = FALSE;

		if (file_exists ($filename) == TRUE)
		{
			if ($check_content == TRUE)
			{
				// This depends on the availability of ImageMagick
				$output = FALSE;
				$result = 0;
				exec ("identify $filename", $output, $result);

				if ($result == 0)
					$retval = TRUE;
			}
			else
			{
				// Not much else we can easily do, so we just check the filesize
				if (filesize ($filename) > 0)
					$retval = TRUE;
			}
		}
			
		return $retval;
	}

	// Function returns either TRUE or FALSE:
	//	TRUE: all the media for this game is present (and is a valid JPG image)
	//	FALSE: one or more of the images is either missing or invalid
	public function validate_media_all ($parent_dir, $check_content = TRUE)
	{
		$retval 	= TRUE;
		$j 		= sizeof ($this->media_list);

		for ($i = 0; $i < $j; $i++)
		{
			$img_data = $this->media_list[$i];
			// TODO: make the pathnames unix/dos safe
			$filename = $parent_dir . "/" . $img_data['source'];

			$retval = $this->validate_media_file ($filename, $check_content);

			if ($retval != TRUE)
				break;
		}

		return $retval;
	}

	// Function to download the given media from $url and store in $filename
	// The filename is assumed to be complete (i.e. the path is relative to the script calling this library)
	// (e.g. "images/test.jpg")
	// We also assume that if this function is called, all existing media for the game should be overwritten.
	public function download_media_file ($source, $destination, $convert_media = TRUE)
	{
		// 1: Create the directory (if any) as required
		$i = strrpos ($destination, '/');
		if ($i !== FALSE)
		{
			$dir_name = substr ($destination, 0, $i);

			if (!file_exists ($dir_name))
			{
				if (mkdir ($dir_name, 0755) == FALSE)
					throw new Exception ("download_media_file: Failed to create directory $dir_name");
			}
		}

		$media_data = file_get_contents ($source);

		if ($media_data == FALSE)
			throw new Exception ("download_media_file: Failed to read $source");

		if (file_put_contents ($destination, $media_data) == FALSE)
			throw new Exception ("download_media_file: Failed to write to $destination");

		// We also need to resample the image to reduce bandwidth overheads
		// This is managed via a call to an external program: the imagemagick "convert" tool
		// (This also deals with the fact that the images on xbox.com are PNGs with a .jpg extension!)
		if ($convert_media)
		{
			$tmp_source	= '/tmp/resized.jpg';
			$output		= FALSE;
			$result		= 0;
			exec ("convert $destination -quality 90% $tmp_source", $output, $result);
			if ($result != 0)
			{
				$str = implode ("\n", $output);
				throw new Exception ("download_media_file: Failed to resample $destination: $result - $str");
			}

			if (unlink ($destination) == FALSE)
				throw new Exception ("download_media_file: Failed to remove old version of $destination");

			/*
			// For some reason, this failed, but rename() doesn't provide any useful debug...
			if (rename ($tmp_source, $destination) == FALSE)
				throw new Exception ("download_media_file: Failed to copy $tmp_source to $destination");
			 */
			$output = FALSE;
			$result = 0;
			exec ("mv $tmp_source $destination", $output, $result);
			if ($result != 0)
			{
				// QND retry mechanism
				sleep (10);
				$output = FALSE;
				$result = 0;
				exec ("mv $tmp_source $destination", $output, $result);

				if ($result != 0)
				{
					$str = implode ("\n", $output);
					throw new Exception ("download_media_file: Failed to copy $tmp_source to $destination: $result - $str");
				}
			}
		}
	}

	public function download_media_all ($parent_dir)
	{
		$j	= sizeof ($this->media_list);

		for ($i = 0; $i < $j; $i++)
		{
			$img_data = $this->media_list[$i];
			// TODO: make the pathnames unix/dos safe
			$source 	= $img_data['url'];
			$destination 	= $parent_dir . "/" . $img_data['source'];

			$this->download_media_file ($source, $destination);
		}

		return $j;
	}

	public function write_to_db ($last_updated = FALSE, $update_review = FALSE, $update_review_date = TRUE)
	{
		$this->check_dbconn ();

		// TODO: write validation system
		$this->validate ();

		$dbconn	= $this->dbconn;

		if (!$last_updated)
			$last_updated = time();

		// We maintain a simple "stacktrace" to assist in debugging
		$stacktrace 	= array();

		try
		{
			// Check to see if the game exists in the database - it might not, if it's a new release or there was a SOAP sync failure
			$db_id = get_game_id ($dbconn, $this->get_tag ('id', 'xbig_id', FALSE);

			$stacktrace[] = "{$this->name}: checked database for game: found id '{$db_id}'\n";

			if ($db_id == FALSE)
			{
				// New game...

				// First, we create the developer record (as required)
				$developer	= $this->get_tag ('tags', 'developer');
				$developer_id   = -1;

				$qu = sprintf ("select id from developers where name='%s'", $dbconn->real_escape_string($developer));
				$rs = get_single_resultset ($dbconn, $qu);

				if ($rs == FALSE)
				{
					$qu = sprintf ("insert into developers (name) values ('%s')", $dbconn->real_escape_string($developer));
					$developer_id = execute_query ($dbconn, $qu, TRUE);

					$stacktrace[] = "{$this->name}: Created developer id $developer_id\n";
				}
				else
				{
					$developer_id = $rs['id'];
					$stacktrace[] = "{$this->name}: Loaded developer id $developer_id\n";
				}

				// Read the game status from the cost tag
				$game_status = 'Active';
				if ($this->tags_list['cost'] == 'Deleted')
					$game_status = 'Deleted';
			
				// 2: Create the game record
				$qu = sprintf ("insert into games (developer_id, blog_id, xbig_id, name, unicode_name, comment, status, last_updated)" .
					" values (%d, '%s', '%s', '%s', '%s', '%s', '%s', from_unixtime(%d))",
						$developer_id,
						$dbconn->real_escape_string($this->id_list['blog_id']),
						$dbconn->real_escape_string($this->id_list['xbig_id']),
						$dbconn->real_escape_string($this->name),
						$dbconn->real_escape_string($this->unicode_name),
						$dbconn->real_escape_string($this->comment),
						$game_status,
						$last_updated
						);

				$this->db_id = execute_query ($dbconn, $qu, TRUE);
			}
			else
			{
				// Update
				// For the moment, we assume that the developer, release date never change; the latter may not be 100% true if a game is 
				// withdrawn/republished, but that's not a major concern...
				$qu = sprintf ("update games set name='%s', last_updated = from_unixtime(%d), blog_id = '%s', comment='%s' where id = %d",
						$dbconn->real_escape_string($this->name),
						$last_updated,
						$dbconn->real_escape_string($this->id_list['blog_id']),
						$dbconn->real_escape_string($this->comment),
						$this->db_id);

				execute_query ($dbconn, $qu);
			}

			// 2: update media
			// To keep things simple, we delete and recreate for each update
			$qu = "delete from game_media where game_id = {$this->db_id}";
			execute_query ($dbconn, $qu);

			foreach ($this->media_list as $item)
			{
				$qu = sprintf ("insert into game_media (game_id, type, source, url, label, description) values ('%d', '%s', '%s', '%s', '%s', '%s')",
						$this->db_id,
						$dbconn->real_escape_string($item['type']),
						$dbconn->real_escape_string($item['source']),
						$dbconn->real_escape_string($item['url']),
						$dbconn->real_escape_string($item['label']),
						$dbconn->real_escape_string($item['description']));

				execute_query ($dbconn, $qu);
			}

			// 3: update the "user sourced" data - reviews and comments
			//$this->write_all_reviews ();
			//$this->write_all_comments ();

			//*
			if ($update_review == TRUE)
			{
				// Again, we go for a simple wipe/recreate system
				$qu = "delete from game_reviews where game_id = {$this->db_id}";
				execute_query ($dbconn, $qu);

				// Quick hack pending completion of multi-user rewrite
				$qu = sprintf ("insert into game_reviews (user_id, game_id, content, status, created, last_updated) " .
					"values (1, '%d', '%s', 'published', now(), now())",
						$this->db_id,
						$dbconn->real_escape_string($this->review_content));

				$date_str       = date ('jS M Y');

				if ($update_review_date == TRUE)
					$this->metadata_list['review_date'] = $date_str;

				execute_query ($dbconn, $qu);
			}
			// */

			// 4: update the tags
			// To adhere to the KISS principle, we delete and recreate for both inserts and updates
			// Note that we *don't* write out the ID tags, as they're stored in the games table for indexing purposes
			$this->set_game_tags ('tags');
			$this->set_game_tags ('metadata');
			$this->set_game_tags ('xbig_metadata');
			$this->set_game_tags ('capabilities');
			$this->set_game_tags ('keys');

			//  Finally, set a flag so that the XML-generating scripts can determine whether cached content can be reused or not
			$qu = "update global_config set config_value=$last_updated where name='last_updated'";
			execute_query ($dbconn, $qu);

			return $this->db_id;
		}
		catch (Exception $e_obj)
		{
			print "Update failure: " . $e_obj->getMessage() . "\n";

			print_r ($stacktrace);
			throw new Exception ("write_to_db: " . $e_obj->getMessage());
		}
	}

	public function set_game_tags ($type)
	{
		$list = FALSE;

		switch ($type)
		{
			case 'tags': 		$list = $this->tags_list; 		break;
			case 'metadata': 	$list = $this->metadata_list; 		break;
			case 'xbig_metadata': 	$list = $this->xbig_metadata_list; 	break;
			case 'capabilities': 	$list = $this->capabilities_list; 	break;
			case 'keys': 		$list = $this->keys_list; 		break;

			case 'id':
				print "id information is held in the games table and shouldn't be written to the tags table!\n";
			default:
				throw new Exception ("set_game_tags: unknown type $type\n");
		}

		try
		{
			$dbconn = $this->dbconn;

			// KISS: we delete and recreate
			$qu = "delete from game_tags where game_id = {$this->db_id} and type='$type'";
			execute_query ($dbconn, $qu);

			foreach ($list as $orig_name => $orig_value)
			{
				$name	= $dbconn->real_escape_string($orig_name);
				$value	= $dbconn->real_escape_string($orig_value);

				$qu = sprintf ("insert into game_tags (game_id, name, value, type) values (%d, '%s', '%s', '%s')",
					$this->db_id, $name, $value, $type);

				execute_query ($dbconn, $qu);
			}
		}
		catch (Exception $e_obj)
		{
			throw new Exception ("set_game_tags: " . $e_obj->getMessage());
		}
	}

	public function import_review_data ($truncate = 0)
	{
		$imported = TRUE;
		if ($this->tags_list['reviewed'] == 'Reviewed')
		{
			$qu = "select content from game_reviews where game_id = {$this->db_id} limit 1";
			$rs = get_single_resultset ($this->dbconn, $qu);

			$content_str = $rs['content'];

			// Strip and truncate the content as appropriate
			if ($truncate > 0)
			{
				$max_content_length = $truncate;

				$content_str = strip_tags ($content_str);
				$content_str = preg_replace ('/\s+/', ' ', $content_str);
				if (strlen($content_str) > $max_content_length)
				{
					// Make sure we don't truncate in the middle of a word
					$content_str = substr ($content_str, 0, $max_content_length);
					$j = strrpos ($content_str, ' ');
					$content_str = substr ($content_str, 0, $j) . "...";
				}
			}

			$this->review_content = $content_str;
		}
		else
		{
			// It's up to the caller to check the return value...
			$imported = FALSE;
			$game_obj->review_content = "";
		}

		return $imported;
	}

	public function generate_xml_str ($include_xbig = TRUE, $include_review_info = FALSE, $indent = 2)
	{
		$outer_indent = '';
		$inner_indent = '';
		$str = '';
		$xbig_str = '';

		// Simple hack for readability
		if ($indent > 0)
		{
			for ($i = 0; $i < $indent; $i++)
				$outer_indent .= "\t";
			$inner_indent = $outer_indent . "\t";
		}

		// Pre-parse any optional data to keep the generation routine simple
		// Function parameters quick look-up:
		// 	generate_xml_node_str ($node_name, $node_value, $attributes, $escape_cdata, $self_closed)
		if ($include_xbig == TRUE)
		{
			// Major hack: if xbig_description is set to FALSE, it should not be included in the output
			$t_str = $this->xbig_metadata_list['xbig_description'];

			if ($t_str !== FALSE)
				$xbig_str = $inner_indent . generate_xml_node_str ("xbig_description", $this->xbig_metadata_list['xbig_description'], FALSE, TRUE) . "\n";

			// Minor hack: the xbig_description field is not meant to be included as an attribute!
			unset ($this->xbig_metadata_list['xbig_description']);
	
			$xbig_str .= $inner_indent . generate_xml_node_str ("xbig_metadata", '', $this->xbig_metadata_list, 	TRUE) . "\n";
			$this->xbig_metadata_list['xbig_description'] = $t_str;
		}

		// Generate the "inner" content
		$str =	"\n" . $inner_indent . generate_xml_node_str ("id", 	'', 		$this->id_list, 		FALSE) . "\n" .
			$inner_indent . generate_xml_node_str ("name", 		$this->name, 	FALSE, 				TRUE) . "\n" .
			$inner_indent . generate_xml_node_str ("comment", 	$this->comment,	FALSE, 				TRUE) . "\n" .
			$inner_indent . generate_xml_node_str ("tags", 		'', 		$this->tags_list, 		FALSE) . "\n" .
			$inner_indent . generate_xml_node_str ("metadata", 	'', 		$this->metadata_list, 		FALSE) . "\n" .
			$inner_indent . generate_xml_node_str ("capabilities", 	'', 		$this->capabilities_list, 	FALSE) . "\n" .
			$xbig_str;

		if ($include_review_info)
		{
			// 1: the review content
			if ($this->review_content != '')
				$str .= $inner_indent . generate_xml_node_str ("review_content", $this->review_content,	FALSE, TRUE) . "\n";

			// 2: any media associated with the game
			// This includes both the "local" media and the "remote" media extracted from xbox.com
			// XML format:
			// <media>
			//	<item type='XBIG Media'>
			//		<source>...</source>
			//		<label></label>
			//		<description></description>
			//	</item>
			//</media>
			$str .= "<media>\n";

			// we can't guarantee the position of items in this list, as data may have come in from xbox.com rather than the
			// DB

			// To keep things simple, we therefore do a pre-parse and sort the data into a temporary array
			// Rankings:
			// 	1: boxart
			// 	2: images
			// 	3: a.n.other (e.g. videos)

			$t_list = array();
			$b_item	= FALSE;

			foreach ($this->media_list as $item)
			{
				if (strpos ($item['source'], 'boxart') !== FALSE)
					$b_item = $item;
				elseif ($item['type'] == 'image')
					array_unshift ($t_list, $item);
				else
					array_push ($t_list, $item);
			}

			if ($b_item)
				array_unshift ($t_list, $b_item);

			//foreach ($this->media_list as $item)
			foreach ($t_list as $item)
			{
				$t_array['type'] = $item['type'];
				$t_str = generate_xml_node_str 	("source", 	$item['source'],	FALSE, 	TRUE) . "\n";
				$t_str .= generate_xml_node_str ("label", 	$item['label'],		FALSE, 	TRUE) . "\n";
				$t_str .= generate_xml_node_str ("description",	$item['description'], 	FALSE, 	TRUE) . "\n";

				$str .= generate_xml_node_str ("item", 		$t_str, 	$t_array, 	FALSE) . "\n";
			}

			$str .= "</media>\n";

			// Lookup other games from the same developer
			// <other_games>
			//	<game xbig_id=X blog_id=Y cost=Z reviewed=A recommendation=B>
			//		<name>fred</name>
			//		<comment>fred</comment>
			//	</game>
			// </other_games>
			// The query for this is a bit tricky: we need to find all games which match the following:
			//	1: where the developer matches the current game's developer
			//	2: where the developer matches the current game's "related developer id"
			//	3: where the "related developer id" matches the current game's developer
			//	4: where the "related developer id" matches the current game's "related develooper id"
			// (phew!)

			//$qu = "select * from games where developer_id = {$this->developer_id} and id <> {$this->db_id}";
			$di	= $this->developer_id;
			$rdi	= $this->related_developer_id;

			$qu = "select g.* from games g, developers d where d.id = g.developer_id and " .
				" (d.id in ($di, $rdi) or d.related_developer_id in ($di, $rdi))" .
				" and g.id <> {$this->db_id}";

			// REWORKED HACK:
			/*
				SELECT g.id, g.name from games g where developer_id = 4 and g.id <> 4 
			   	UNION 
				select g.id, g.name from games g, developers d1, developers d2 
					where d1.id = 4 and d1.related_developer_id = d2.id and g.developer_id = d2.id
			 */
	
			$other_games = get_all_resultsets ($this->dbconn, $qu);

			$str .= "<other_games>\n";
			foreach ($other_games as $game)
			{
				$t_str = generate_xml_node_str 	("name", 	$game['name'],		FALSE, 	TRUE) . "\n";
				$t_str .= generate_xml_node_str ("comment", 	$game['comment'],	FALSE, 	TRUE) . "\n";

				$qu = "select type, name, value from game_tags where game_id = {$game['id']}";
				$all_tags = get_all_resultsets ($this->dbconn, $qu);

				$t_array = array();
				$t_array['xbig_id'] = $game['xbig_id'];
				$t_array['blog_id'] = $game['blog_id'];

				foreach ($all_tags as $rs)
				{
					// To keep things simple, we assume for the moment that all the tags of 
					// interest are unique by name as well as type...
					$name = $rs['name'];
					switch ($name)
					{
						case 'xbig_id':
						case 'blog_id':
						case 'cost':
						case 'reviewed':
						case 'release_date':
						case 'recommendation':
							$t_array[$name] = $rs['value'];
							break;
					}
				}

				$str .= generate_xml_node_str ("game", $t_str,	$t_array, 	FALSE) . "\n";
			}
			$str .= "</other_games>\n";
		}
		
		// Insert the content into the parent XML node...
		$str = $outer_indent . generate_xml_node_str ("game", $str, $this->keys_list, FALSE) . "\n";

		return $str;
	}

	public function debug ()
	{
		print "===\n";
		print "Name:\t\t\t" 	. $this->name . "\n";
		print "Comment:\t\t" 	. $this->comment . "\n";

		print "ID info:\n";
		foreach ($this->id_list as $tag => $value)
			print "\t\t\t$tag:\t$value\n";

		print "Tag info:\n";
		foreach ($this->tags_list as $tag => $value)
			print "\t\t\t$tag:\t$value\n";

		print "Metadata info:\n";
		foreach ($this->metadata_list as $tag => $value)
			print "\t\t\t$tag:\t$value\n";

		print "XBIG Metadata info:\n";
		foreach ($this->xbig_metadata_list as $tag => $value)
			print "\t\t\t$tag:\t$value\n";

		print "Capabilities info:\n";
		foreach ($this->capabilities_list as $tag => $value)
			print "\t\t\t$tag:\t$value\n";

		print "Keys info:\n";
		foreach ($this->keys_list as $tag => $value)
			print "\t\t\t$tag:\t$value\n";
	}
}
?>
