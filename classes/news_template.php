<?

/*
 * news_template: provides a OO-interface for interacting with "news" entities, including the tags associated with them
 */

class news_template extends parent_template
{
	// __construct () 	defined by parent_template
	// check_dbconn () 	defined by parent_template
	// initialise_db ()	defined by parent_template
	// get ()			defined by parent_template

	// implementation of abstract method parent::initialise_data
	public function initialise_data ()
	{
		$this->type = 'news';
		$this->data = array();

		// Set some vaguely sane defaults...
		$this->data['id']			= 0;
		$this->data['user_id']			= 0;
		$this->data['headline']			= '';
		$this->data['content']			= '';
		$this->data['status']			= 'published';
		$this->data['created']			= '';
		$this->data['last_updated']		= '';

		// "helper" fields, for XML output
		$this->data['loginid']			= '';
		$this->data['created_timestamp']	= 0;
		$this->data['last_updated_timestamp']	= 0;

		$this->tags = array();
	}

	// implementation of abstract method parent::read_from_db
	public function read_from_db ($id)
	{
		$this->check_dbconn ();

		$qu = "select un.*, unix_timestamp(un.last_updated) last_updated_timestamp, unix_timestamp(un.created) created_timestamp " .
			"from user_news un where un.id = " . (int) $id;

		try
		{
			// This function call will automatically trigger an exception if no records are found
			$rs = get_single_resultset ($this->dbconn, $qu, TRUE);

			if ($rs)
			{
				foreach ($rs as $key => $value)
					$this->set ($key, $value);
			}

			$this->read_metadata_from_db ();
		}
		catch (Exception $e_obj)
		{
			$this->throw_exception ($e_obj);
		}
	}

	public function read_metadata_from_db ()
	{
		$this->check_dbconn ();
		$qu = "select u.loginid from users u, user_news un " .
			"where u.id = un.user_id and un.id = " . (int) $this->get ('id');

		$rs = get_single_resultset ($this->dbconn, $qu, TRUE);

		$this->set ('loginid', $rs['loginid']);
	}

	// override of method parent::set
	public function set ($name, $value)
	{
		// We validate the value before setting it
		switch ($name)
		{
			case 'id':
			case 'user_id':
				if ($this->get ($name) != 0)
					$this->throw_exception ("value already defined for $name: [$value]");
				// ... and then we fall through to the integer checks: no break!

			case 'last_updated_timestamp':
			case 'created_timestamp':
				if ($value != (int) $value)
					$this->throw_exception ("non-integer data value for $name: [$value]");
				break;

			case 'content':
			case 'headline':
			case 'created':
			case 'last_updated':
			case 'loginid':
				if ($value == '')
					$this->throw_exception ("null value for $name");
				break;

			case 'status':
				switch ($value)
				{
					case 'published':
					case 'unpublished':
					case 'locked':
						break;

					default:
						$this->throw_exception ("unknown status type [$value]");
				}
				break;
		}

		$this->data[$name] = $value;

		// the time/timestamp fields are a special case: if one has been set, then we need to set the other
		switch ($name)
		{
			case 'last_updated':
				$this->data['last_updated_timestamp'] = strtotime ($value);
				break;
			case 'last_updated_timestamp':
				$this->data['last_updated'] = date ("Y-m-d H-i-s", $value);
				break;
			case 'created':
				$this->data['created_timestamp'] = strtotime ($value);
				break;
			case 'created_timestamp':
				$this->data['created'] = date ("Y-m-d H-i-s", $value);
				break;
		}
	}

	public function get_tag ($name)
	{
		$retval = '';

		switch ($name)
		{
			case 'game_names':
			case 'game_tags':
			case 'news_tags':
				$retval = $this->tags[$name];
				break;

			default:
				$this->throw_exception ("unrecognised tag name [$tag_name]");
		}

		return $retval;
	}

	public function set_tag ($tag_name, $tag_value)
	{
		switch ($name)
		{
			case 'game_names':
			case 'game_tags':
			case 'news_tags':
				$this->tags[$name] = $value;
				break;

			default:
				$this->throw_exception ("unrecognised tag name [$tag_name]");
		}
	}

	public function get_all_tags ()
	{
		return $this->tags;
	}

	// implementation of abstract method parent::validate
	public function validate ()
	{
		$this->check_dbconn ();

		$user_id = $this->get ('user_id');
		if ($user_id == '' || $user_id == 0)
			$this->throw_exception ("user_id not set");

		try
		{
			// This call will automatically throw an exception if no records are found
			$qu = sprintf ("select id from users where id = %d", (int) $user_id);
			$rs = get_single_resultset ($this->dbconn, $qu, TRUE);
		}
		catch (Exception $e_obj)
		{
			$this->throw_exception ($e_obj);
		}
	}

	// implementation of abstract method parent::write_to_db
	public function update_entity_in_db ($set_created_timestamp = TRUE)
	{
		$this->check_dbconn ();
		$this->validate ();

		$dbconn = $this->dbconn;

		// Set timestamps on the entity as appropriate
		$db_id = $this->get ('id');
		$now = time ();

		// We always update the last_updated timestamp; we only update the created_timestamp if this is a new record or if 
		// the update has been explicitly requested
		$this->set ('last_updated_timestamp', $now);
		if ($set_created_timestamp == TRUE || $db_id == 0)
			$this->set ('created_timestamp', $now);

		if ($db_id == 0)
		{
			$qu = sprintf ("insert into user_news (user_id, headline, content, status, created, last_updated) " .
						"values (%d, '%s', '%s', '%s', from_unixtime(%d), from_unixtime(%d))", 
							$this->get ('user_id'),
							$dbconn->real_escape_string($this->get ('headline')),
							$dbconn->real_escape_string($this->get ('content')),
							$dbconn->real_escape_string($this->get ('status')),
							$this->get ('created_timestamp'),
							$this->get ('last_updated_timestamp')
						);

			try
			{
				$db_id = execute_query ($dbconn, $qu, TRUE);
			}
			catch (Exception $e_obj)
			{
				$this->throw_exception ($e_obj);
			}

			$this->set ('id', $db_id);
		}
		else
		{
			// We assume the game_id/user_id/created fields are static...
			$qu = sprintf ("update user_news set headline= '%s', content = '%s', status = '%s', last_updated = from_unixtime(%d) where id = %d",
							$dbconn->real_escape_string($this->get('headline')),
							$dbconn->real_escape_string($this->get('content')),
							$dbconn->real_escape_string($this->get('status')),
							$this->get ('last_updated_timestamp'),
							$this->get ('id')
						);

			try
			{
				execute_query ($dbconn, $qu);
			}
			catch (Exception $e_obj)
			{
				$this->throw_exception ($e_obj);
			}

		}

		// Make sure that metadata is consistent
		$this->read_metadata_from_db ();
	}

	// implementation of abstract method parent::delete_from_db
	public function delete_from_db ()
	{
		$this->check_dbconn ();

		// In theory, this should never be called: if we don't want a review to be visible, we just mark it as "locked"
		$qu = sprintf ("delete from user_news where id = %d", $this->get('id'));

		try
		{
			execute_query ($this->dbconn, $qu);
		}
		catch (Exception $e_obj)
		{
			$this->throw_exception ($e_obj);
		}

		$this->initialise_data ();
	}

	public function render_to_xml ()
	{
		/*
		 * XML structure
		 	<news id=''>
				<loginid></loginid>
				<created></created>
				<last_updated></last_updated>
		 		<content></content>
		 		<status></status>
				<tags>
					<tag type=''></tag>
				</tags>
		 	</news>
		 *
		 */

		$this->check_dbconn ();

		$c_stamp = date ("Y-m-d", $this->get('created_timestamp'));
		$lu_stamp = date ("Y-m-d H:i:s", $this->get('last_updated_timestamp'));
		
		// REMINDER: generate_xml_node_str ($node_name, $node_value, $attributes, [$escape_cdata, [$self_closed]])
		$inner_str = generate_xml_node_str ("loginid", 		$this->get('loginid'), 		FALSE, TRUE);
		$inner_str .= generate_xml_node_str ("created",		$c_stamp, 			FALSE, TRUE);
		$inner_str .= generate_xml_node_str ("last_updated",	$lu_stamp, 			FALSE, TRUE);
		$inner_str .= generate_xml_node_str ("content",		$this->get('content'), 		FALSE, TRUE);
		$inner_str .= generate_xml_node_str ("status", 		$this->get('status'), 		FALSE, TRUE);

		$tmp_array = array ('type', '');
		$tag_str = '';
		foreach ($this->tags as $key => $value)
		{
			$tmp_array['type'] = $key;
			$tag_str .= generate_xml_node_str ("tag",	$value,	 	$tmp_array, TRUE, FALSE);
		}
		
		$inner_str .= generate_xml_node_str ("tags", $tag_str, FALSE, FALSE, FALSE);

		return $inner_str;
	}
}

?>
