<?

class comment_template extends parent_template
{

	// __construct () 	defined by parent_template
	// check_dbconn () 	defined by parent_template
	// initialise_db ()	defined by parent_template
	// get ()		defined by parent_template

	// implementation of abstract method parent::initialise_data
	public function initialise_data ()
	{
		$this->type = 'comment';
		$this->data = array();

		// Set some vaguely sane defaults...
		$this->data['id']				= 0;
		$this->data['game_id']			= 0;
		$this->data['blog_id']			= '';
		$this->data['guest_name']		= '';
		$this->data['guest_email']		= '';
		$this->data['content']			= '';
		$this->data['status']			= 'published';
		$this->data['created']			= '';
		$this->data['created_timestamp']	= 0;
	}

	// implementation of abstract method parent::read_from_db
	public function read_from_db ($id)
	{
		$this->check_dbconn ();

		$qu = "select gc.*, unix_timestamp(gc.created) created_timestamp from guest_comments gc " .
			"where gc.id = " . (int) $id;

		try
		{
			// This function call will automatically trigger an exception if no records are found
			$rs = get_single_resultset ($this->dbconn, $qu, TRUE);

			foreach ($rs as $key => $value)
				$this->set ($key, $value);

			$this->read_metadata_from_db ();
		}
		catch (Exception $e_obj)
		{
			throw new Exception ("comment_template: {$e_obj->getMessage()}");
		}
	}

	public function read_metadata_from_db ()
	{
		$this->check_dbconn ();
		$qu = "select blog_id from games where id = " . (int) $this->get ('game_id');
		$rs = get_single_resultset ($this->dbconn, $qu, TRUE);

		$this->set ('blog_id', $rs['blog_id']);
	}

	// override of method parent::set
	public function set ($name, $value)
	{
		// Validate the value before setting it
		try
		{
			switch ($name)
			{
				case 'guest_email':
					break;

				case 'id':
				case 'game_id':
				case 'created_timestamp':
					if ($value != (int) $value)
						throw new Exception ("non-integer data value for $name: [$value]");
					break;

				case 'guest_name':
				case 'content':
				case 'created':
				case 'blog_id':
					if ($value == '')
						throw new Exception ("null value for $name");
					break;

				case 'status':
					switch ($value)
					{
						case 'published':
						case 'locked':
							break;

						default:
							throw new Exception ("unknown status type [$value]");
					}
					break;

				//default:
				//	throw new Exception ("unknown data key [$name]");
			}

			$this->data[$name] = $value;

			// created/created_timestamp are a special case: if one has been set, then we need to set the other
			if ($name == 'created')
				$this->data['created_timestamp'] = strtotime ($value);
			elseif ($name == 'created_timestamp')
				$this->data['created'] = date ("Y-m-d H-i-s", $value);
		}
		catch (Exception $e_obj)
		{
				throw new Exception ("comment_template->set: review {$this->get('id')}: {$e_obj->getMessage()}");
		}
	}

	// implementation of abstract method parent::validate
	public function validate ()
	{
		$this->check_dbconn ();

		try
		{
			// Simple "syntax" validation
			if ($this->get ('content') == '')
				throw new Exception ("content not set");

			$game_id = $this->get ('game_id');
			if ($game_id == '')
			{
				throw new Exception ("game_id not set");
			}
			else
			{
				$qu = sprintf ("select id from games where id = %d", (int) $game_id);

				// This call will automatically throw an exception if no records are found
				$rs = get_single_resultset ($this->dbconn, $qu, TRUE);
			}
		}
		catch (Exception $e_obj)
		{
			throw new Exception ("comment_template->validate: review {$this->get('id')}: {$e_obj->getMessage()}");
		}
	}

	// implementation of abstract method parent::write_to_db
	public function update_entity_in_db ($set_created_timestamp = TRUE)
	{
		$this->check_dbconn ();
		$this->validate ();

		$dbconn = $this->dbconn;

		// Set timestamps on the review as appropriate
		$db_id = $this->get ('id');
		$now = time ();

		// Only update the timestamp if this is a new record or if the update has been explicitly requested
		if ($set_created_timestamp == TRUE || $id == 0)
			$this->set ('created_timestamp', $now);

		if ($db_id == 0)
		{
			$qu = sprintf ("insert into guest_comments (game_id, guest_name, guest_email, content, status, created) " .
						"values (%d,'%s','%s','%s','%s', from_unixtime(%d))", 
							$this->get ('game_id'),
							$dbconn->real_escape_string($this->get ('guest_name')),
							$dbconn->real_escape_string($this->get ('guest_email')),
							$dbconn->real_escape_string($this->get ('content')),
							$dbconn->real_escape_string($this->get ('status')),
							$this->get ('created_timestamp')
						);

			$db_id = execute_query ($dbconn, $qu, TRUE);
			$this->set ('id', $db_id);
		}
		else
		{
			// We assume the game_id field is static...
			$qu = sprintf ("update guest_comments set guest_name = '%s', guest_email = '%s', content = '%s', " .
						"status='%s', created=from_unixtime (%d) where id = %d",
							$dbconn->real_escape_string($this->get('guest_name')),
							$dbconn->real_escape_string($this->get('guest_email')),
							$dbconn->real_escape_string($this->get('content')),
							$dbconn->real_escape_string($this->get('status')),
							$this->get ('created_timestamp'),
							$this->get ('id')
						);

			execute_query ($dbconn, $qu);
		}

		// Then, we need to make sure that any metadata the entity needs is consistent
		$this->read_metadata_from_db ();
	}

	// implementation of abstract method parent::delete_from_db
	public function delete_from_db ()
	{
		$this->check_dbconn ();

		// In theory, this should never be called: if we don't want a review to be visible, we just mark it as "locked"
		$qu = sprintf ("delete from guest_comments where id = %d", $this->get('id'));
		execute_query ($this->dbconn, $qu);

		$this->initialise_data ();
	}

	public function render_to_xml ($show_full_info = FALSE)
	{
		/*
		 * XML structure
		 	<comment blog_id=''>
				<guest_name></guest_name>
				<created></created>
		 		<content></content>
				<guest_email></guest_email>	-- INTERNAL USE ONLY
				<status></status>		-- INTERNAL USE ONLY
		 	</comment>
		 *
		 */

		$str = '';
		$datestamp = date ("Y-m-d", $this->get('created_timestamp'));
		
		// 	generate_xml_node_str ($node_name, $node_value, $attributes, [$escape_cdata, [$self_closed]])
		$inner_str = generate_xml_node_str ("guest_name", 	$this->get('guest_name'), 		FALSE, TRUE);
		$inner_str .= generate_xml_node_str ("created",		$datestamp, 				FALSE, TRUE);
		$inner_str .= generate_xml_node_str ("content",		$this->get('content'), 			FALSE, TRUE);

		if ($show_full_info === TRUE)
		{
			$inner_str .= generate_xml_node_str ("guest_email", 	$this->get('guest_email'), 	FALSE, TRUE);
			$inner_str .= generate_xml_node_str ("status", 		$this->get('status'), 		FALSE, TRUE);
		}

		$tmp_array = array ('blog_id' => $this->get('blog_id'));
		$str = generate_xml_node_str ('comment', $inner_str, $tmp_array, FALSE);

		return $str;
	}
}
?>
