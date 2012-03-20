<?

/*
 * The game_template class is intended to act as an self-contained, atomic representation of a game - i.e. all the data held in the database for the game
 * is automatically loaded into the object when it is initialised, and the object can then be serialised and used to synchronise data between the 
 * "live" and "offsite" systems.
 *
 */

class game_template
{
	private $dbconn				= FALSE;

	// Data structures are modelled on the XML output, rather than the database structure
	// (i.e. Developer name is included in the tags, rather than being a separate object)
	public $name 				= '';
	public $comment 			= '';
	public $review_content			= '';

	public $id_list				= FALSE;
	public $tags_list			= FALSE;
	public $metadata_list			= FALSE;
	public $xbig_metadata_list		= FALSE;
	public $capabilities_list		= FALSE;
	public $keys_list			= FALSE;

	public $media_list			= FALSE;

	// Internal variables: left visible for debugging purposes
	public $db_id				= FALSE;
	public $developer_id			= FALSE;
	public $related_developer_id		= FALSE;
	public $unicode_name			= '';
	public $last_updated 			= FALSE;
	public $xbig_str			= '';

	public function __construct ($dbconn)
	{
		$this->id_list			= array();
		$this->tags_list		= array();
		$this->metadata_list		= array();
		$this->xbig_metadata_list	= array();
		$this->capabilities_list	= array();
		$this->keys_list		= array();
		$this->media_list		= array();

		// TODO: shift default values assignments from load_from_xbig to here
		$this->xbig_metadata_list{'xbig_description'} = '';

		$this->initialise_db ($dbconn);
		$this->check_dbconn ();
	}

	public function initialise_db ($dbconn)
	{
		$this->dbconn = $dbconn;
	}

	public function check_dbconn ()
	{
		$dbc = $this->dbconn;

		if (!$dbc || $dbc->connect_errno > 0)
			throw new Exception ("game_template: database connection not initialised");
	}

	public function load_from_db ($game_id)
	{
		$this->check_dbconn ();

		// 1: load information from developers and games
		$qu = "select d.name dev_name, d.related_developer_id, g.* from developers d, games g where d.id = g.developer_id and g.id = $game_id";

		// get_single_resultset will automatically throw an exception if no records are found
		$rs = get_single_resultset ($this->dbconn, $qu, TRUE);

		$this->db_id			= $game_id;
		$this->name 			= $rs['name'];
		$this->comment 			= $rs['comment'];
		$this->developer_id		= (int) $rs['developer_id'];
		$this->related_developer_id	= (int) $rs['related_developer_id'];

		// Handle special cases: some information from the GAMES table is also used as searchable tags
		$this->id_list['blog_id']	= $rs['blog_id'];
		$this->id_list['xbig_id']	= $rs['xbig_id'];
		$this->tags_list['developer']	= $rs['dev_name'];

		// 2: Read data from the game_tag table and split into the appropriate arrays
		$qu = "select type, name, value from game_tags where game_id = $game_id";
		$rs_list = get_all_resultsets ($this->dbconn, $qu);

		foreach ($rs_list as $rs)
		{
			$name 	= $rs['name'];
			$type 	= $rs['type'];
			$value 	= $rs['value'];

			switch ($type)
			{
				case 'tags':
					$this->tags_list[$name] = $value;
					break;

				case 'metadata':
					$this->metadata_list[$name] = $value;
					break;

				case 'xbig_metadata':
					$this->xbig_metadata_list[$name] = $value;
					break;

				case 'capabilities':
					$this->capabilities_list[$name] = $value;
					break;

				case 'keys':
					$this->keys_list[$name] = $value;
					break;

				default:
					// Log the fact that we don't recognise the tag, but don't throw an exception
					error_log ("game_template->load_from_db: unknown tag $type:$name/$value\n");
					break;
			}
		}

		// 3: Read from the game_media table and store in the media_list table
		// The data in the table can be loaded directly into the media_list array!
		$qu = "select * from game_media where game_id = $game_id";
		$this->media_list = get_all_resultsets ($this->dbconn, $qu);

		// 4: Read the review(s) associated with the game
		//$this->read_all_reviews ();
		//$this->read_all_comments ();
	}

	public function load_from_xbig ($url, $xbig_id)
	{
		// Function to load the data from the xbox.com website
		// XBIG games can also have zero or more metatags associated with them
		// These need to parsed and allocated to the appropriate tags[] or metadata[] attribute
		// Sadly, the xbox.com HTML isn't XHTML (unclosed tags, etc), so we can't simply use an XML parser to process the data.
		// Instead, we use a set of customised regex's to pull out the data
		//
		// Checks for media (boxart, screenshots) are performed on every single pass: actual checking/downloading of the images is left to the caller

		$this->check_dbconn ();

		get_url_contents ($url, $str);

		// 1: Load the current "live" values from the database: these will be overwritten as appropriate
		$game_id = get_game_id ($this->dbconn, $xbig_id);

		if ($game_id)
			$this->load_from_db ($game_id);

		if (!$game_id)
		{
			// Set initial values/defaults
			$this->comment				= "Unreviewed";
			$this->xbig_metadata_list['url']	= $url;
			$this->id_list['xbig_id']		= $xbig_id;

			// Currently, xbox.com outputs the date as "dd/mm/yyyy": for strtotime to work, it needs to be
			// in the format "dd-mm-yyyy", as otherwise the functions assumes it's US-formatted...
			$release_date 				= extract_text ($str, '{<label>Release date:<\/label> (.*?)<\/li>}');
			$release_date 				= str_replace ('/', '-', $release_date);
			
			// We also need a sortable version of the release date for several of the fields below
			$this->keys_list['date_key']		= date ('Ymd', strtotime ($release_date));

			// We need to ensure the name is searchable/english-readable
			// Simple check: does the name contain any alphanumeric characters
			// \w also matches on unicode characters, so we need to explicitly use a-zA-Z0-9
			$this->tags_list['developer']		= extract_text ($str, '{<label>Developer:<\/label> (.*?)<\/li>}');
			$this->name				= extract_text ($str, '{<title>\s*(.*?) - Xbox.com}');
			$this->unicode_name 			= $this->name;

			if (!preg_match ('{^[a-zA-Z0-9]}', $this->name))
				$this->name 			= "ZZ (Non-ASCII title) - {$this->tags_list['developer']}: " .
										"{$this->keys_list['date_key']}";

			// Create a default "blog_id" value by combining the sanitised name with the datestamp
			$this->id_list['blog_id']		= sanitise_str ($this->name) . "_zz_" . $this->keys_list['date_key'];

			// Further keys for searching
			// The name key is a based on the default, sanitised version of the name
			$this->keys_list['name_key']			= sanitise_str ($this->name) . $this->keys_list['date_key'];
			$this->keys_list['sort_key']			= $this->keys_list['name_key'];

			$this->tags_list['all']				= 'Show All';
			$this->tags_list['reviewed']			= 'Unreviewed';
			$this->tags_list['recommendation']		= 'Unreviewed';
			$this->tags_list['genre']			= 'Unknown';
			$this->tags_list['subgenre']			= 'TBC';

			// General metadata
			$this->metadata_list['inspired_by']		= 'TBC';
			$this->metadata_list['release_date']		= date ('jS M Y', strtotime ($release_date));
			$this->metadata_list['review_date']		= '';
			$this->metadata_list['filesize']		= extract_text ($str, '{<label>Size:<\/label> (.*?)<\/li>}');
			$this->metadata_list['boxart'] 			= 'No';
			$this->metadata_list['screenshot_count']	= '0';

			// All other tags are driven by values within the HTML
		}

		// 1: Extract "capabilities" from the HTML.
		// To keep things simple, the tag name is the capability name with all non-word characters (\W) stripped out
		$m_list 	= array();
		$t_str 		= extract_text ($str, '{class="capabilities">(.*?)</div>}s');
		preg_match_all ('{<li>(.*?)</li>}s', $t_str, $m_list);
		$m_list 	= $m_list[1];
		foreach ($m_list as $capability)
		{
			$t_str = strtolower (preg_replace ('/\W/', '', $capability));
			$this->capabilities_list [$t_str] = $capability;
		}

		// Deal with the values common to both inserts/updates
		// We hack the " MSP" currency note onto the end of the cost
		// Note that we *DON'T* overwrite XML-sourced data
		$this->tags_list['category']		= extract_text ($str, '{<label>Genre:</label>\s*(.*?)<}');
		$this->tags_list['cost']		= extract_text ($str, '{<span class="MSPoints.*?">(\d+)</span>}s') . " MSP";

		// XBIG metadata
		$tmp_description									= extract_text ($str, '{<div id="overview1".*?<p>(.*?)</p>}s');
		$this->xbig_metadata_list['xbig_description']		= extract_text ($tmp_description, '{.*?\. (.*)}s', TRUE);
		$this->xbig_metadata_list['xbig_classification']	= extract_text ($tmp_description, '{(^This.*?\.)}s', TRUE);

		// NOV 2010 UPDATE: Extracting a game's rating is now a bit trickier, as it's no longer explicitly displayed as a number.  Instead, 
		// it has to be reverse engineered from the number *and* type of "star" images on-screen...
		$this->xbig_metadata_list['rating']		= 0;
		$this->xbig_metadata_list['rating_count']	= 0;

		$t_str 		= extract_text ($str, '{class="UserRatingStarStrip">(.*?</div>.*?)</div>}s');
		preg_match_all ('{<span class="Star Star(\d)">}s', $t_str, $m_list);
		$m_list 	= $m_list[1];
		$rating 	= 0;
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
		$this->xbig_metadata_list['rating']		= $rating;
		$this->xbig_metadata_list['rating_count']	= extract_text ($t_str, '{.*<span>([\d,]+)<\/span>\s*$}s');

		// Each game can have a "boxart" image and a set of sample screenshots associated with it.
		// To keep things simple, we wipe/recreate on every single pass
		$this->media_list 				= array();
		$this->metadata_list['boxart'] 			= 'No';
		$this->metadata_list['screenshot_count'] 	= 0;

		// NOV 2010: quick hack to identify image URLs...
		preg_match_all ("{\"(http://download.xbox.com.*\.jpg)\"}", $str, $m_list);
		$m_list 	= $m_list[1];
		$sc 		= 0;
		foreach ($m_list as $url)
		{
			$t_str = extract_text ($url, '{(\w+?)\d*.jpg$}');
			switch ($t_str)
			{
				case 'xboxboxart':
					// Special case: The boxart is shown twice!
					if ($this->metadata_list['boxart'] == 'No')
					{
						// Note that "source" = LOCAL name for the image (i.e. what xboxindiegames.co.uk will refer to)
						$this->metadata_list['boxart'] 	= 'Yes';
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
					// Increment the image count first...
					$sc++;

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
		$this->metadata_list['screenshot_count'] 	= $sc;
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

	// Function to download the given media from $source and store in $destination
	// The "destination" path/filename is assumed to be absolute
	// (e.g. "/var/www/images/test.jpg")
	// We also assume that if the file already exists, it should be overwritten
	// When convert_image is set, the media is assumed to be an image and will be
	// passed to imagemagick for resampling, to reduce the filesize
	public function download_media_file ($source, $destination, $convert_image = TRUE)
	{
		// First, we need to create the directory if it doesn't exist
		$i = strrpos ($destination, '/');

		if ($i !== FALSE) {
			$dir_name = substr ($destination, 0, $i);

			if (!file_exists ($dir_name)) {
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
		if ($convert_image === TRUE) {
			$tmp_source	= '/tmp/resized.jpg';
			$output		= FALSE;
			$result		= 0;
			exec ("convert $destination -quality 90% $tmp_source", $output, $result);
			if ($result != 0) {
				$str = implode ("\n", $output);
				throw new Exception ("download_media_file: Failed to resample $destination: $result - $str");
			}

			if (unlink ($destination) == FALSE)
				throw new Exception ("download_media_file: Failed to remove old version of $destination");

			/*
			// For some reason, this failed, but rename() doesn't provide any useful debug...
			if (rename (, $destination) == FALSE)
				throw new Exception ("download_media_file: Failed to copy $tmp_source to $destination");
			 */
			$output = FALSE;
			$result = 0;
			exec ("mv $tmp_source $destination", $output, $result);
			if ($result != 0) {
				// QND retry mechanism
				sleep (10);
				$output = FALSE;
				$result = 0;
				exec ("mv $tmp_source $destination", $output, $result);

				if ($result != 0) {
					$str = implode ("\n", $output);
					throw new Exception ("download_media_file: Failed to copy $tmp_source to $destination: $result - $str");
				}
			}
		}
	}

	public function download_media_all ($parent_dir)
	{
		$j	= sizeof ($this->media_list);

		for ($i = 0; $i < $j; $i++) {
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

		$dbconn 		= $this->dbconn;

		if (!$last_updated)
			$last_updated = time();

		// We maintain a simple "stacktrace" to assist in debugging
		$stacktrace 	= array();

		try {
			// Check to see if the game exists in the database - it might not, if it's a new release or there was a SOAP sync failure
			$this->db_id = get_game_id ($dbconn, $this->id_list['xbig_id']);

			$stacktrace[] = "{$this->name}: checked database: found game with id '{$this->db_id}'\n";

			if ($this->db_id == FALSE) {
				// 1: Create the developer record (if required)
				$developer	= $this->tags_list['developer'];
				$developer_id   = -1;

				$qu = sprintf ("select id from developers where name='%s'", $dbconn->real_escape_string($developer));
				$rs = get_single_resultset ($dbconn, $qu);

				if ($rs == FALSE) {
					$qu = sprintf ("insert into developers (name) values ('%s')", $dbconn->real_escape_string($developer));
					$developer_id = execute_query ($dbconn, $qu, TRUE);

					$stacktrace[] = "{$this->name}: Created developer id $developer_id\n";
				} else {
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
			} else {
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

			foreach ($this->media_list as $item) {
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
			if ($update_review == TRUE) {
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
		} catch (Exception $e_obj) {
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
