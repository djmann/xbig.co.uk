<?

class review_template extends parent_template
{

	// __construct () 	defined by parent_template
	// check_dbconn () 	defined by parent_template
	// initialise_db ()	defined by parent_template
	// get ()		defined by parent_template

	// implementation of abstract method parent::initialise_data
	public function initialise_data ()
	{
		$this->type = 'review';
		$this->data = array();

		// Set some vaguely sane defaults...
		$this->data['id']			= 0;
		$this->data['game_id']			= 0;
		$this->data['game_name']		= '';
		$this->data['user_id']			= 0;
		$this->data['content']			= '';
		$this->data['score']			= 0;
		$this->data['recommendation']		= 0;
		$this->data['status']			= 'published';
		$this->data['created']			= '';
		$this->data['created_timestamp']	= 0;

		// "helper" fields, for XML output
		$this->data['blog_id']			= '';
		$this->data['loginid']			= '';
		$this->data['last_updated']		= '';
		$this->data['last_updated_timestamp']	= 0;
	}

	// implementation of abstract method parent::read_from_db
	public function read_from_db ($id)
	{
		$this->check_dbconn ();

		$qu = "select gr.*, unix_timestamp(gr.last_updated) last_updated_timestamp, unix_timestamp(gr.created) created_timestamp " .
			"from game_reviews gr where gr.id = " . (int) $id;

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
		try
		{
			// get_single_resultset will automatically trigger an exception if no records are found
			/*
			 * Each game review should have a score set against it; for the moment, there's a global "recommendation" value in
			 * the game_tags table.
			 * Therefore, we have a qnd hack until a full review entity system is implemented...
			 * old code:
			$qu = "select g.name, g.blog_id, u.loginid from games g, users u, game_reviews gr " .
				"where g.id = gr.game_id and u.id = gr.user_id and gr.id = " . (int) $this->get ('id');
			 */

			$qu = "select g.name, g.blog_id, u.loginid, gt.value recommendation from games g, users u, game_reviews gr, game_tags gt " .
				"where g.id = gr.game_id and u.id = gr.user_id and g.id = gt.game_id and gt.name='Recommendation' and " .
				"gr.id = " . (int) $this->get ('id');

			$rs = get_single_resultset ($this->dbconn, $qu, TRUE);

			$this->set ('game_name', 	$rs['name']);
			$this->set ('blog_id', 		$rs['blog_id']);
			$this->set ('loginid', 		$rs['loginid']);
			$this->set ('recommendation', 	$rs['recommendation']);

		
			/*
			$qu = sprintf ("select config_value from global_config where name='recommendation' and config_key = %d", $this->get ('score'));
			$rs = get_single_resultset ($this->dbconn, $qu, TRUE);
			$this->set ('recommendation', $rs['config_value']);
			 */
		}
		catch (Exception $e_obj)
		{
			$this->throw_exception ($e_obj);
		}
	}

	// override of method parent::set
	public function set ($name, $value)
	{
		// We validate the value before setting it
		switch ($name)
		{
			case 'id':
			case 'game_id':
			case 'game_name':
			case 'user_id':
				if ($this->get ($name) != 0)
					$this->throw_exception ("value already defined for $name: [$value]");
				// ... and then we fall through to the integer checks: no break!

			case 'score':
			case 'last_updated_timestamp':
			case 'created_timestamp':
				if ($value != (int) $value)
					$this->throw_exception ("non-integer data value for $name: [$value]");
				break;

			case 'content':
			case 'created':
			case 'last_updated':
			case 'blog_id':
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

	// implementation of abstract method parent::validate
	public function validate ()
	{
		$this->check_dbconn ();

		$entity_list = array ('game_id' => 'games', 'user_id' => 'users');

		try
		{
			foreach ($entity_list as $key => $table)
				$this->validate_entity ($table, $key);
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

		// Set timestamps on the review as appropriate
		$db_id = $this->get ('id');
		$now = time ();

		// We always update the last_updated timestamp; we only update the created_timestamp if this is a new record or if 
		// the update has been explicitly requested
		$this->set ('last_updated_timestamp', $now);
		if ($set_created_timestamp == TRUE || $db_id == 0)
			$this->set ('created_timestamp', $now);

			if ($db_id == 0)
			{
				$qu = sprintf ("insert into game_reviews (game_id, user_id, content, score, status, created, last_updated) " .
							"values (%d, %d, '%s', %d, '%s', from_unixtime(%d), from_unixtime(%d))", 
								$this->get ('game_id'),
								$this->get ('user_id'),
								$dbconn->real_escape_string($this->get ('content')),
								$this->get ('score'),
								$dbconn->real_escape_string($this->get ('status')),
								$this->get ('created_timestamp'),
								$this->get ('last_updated_timestamp')
							);

				$db_id = $this->execute_query ($qu, TRUE);
				$this->set ('id', $db_id);
			}
			else
			{
				// We assume the game_id/user_id/created fields are static...
				$qu = sprintf ("update game_reviews set content = '%s', score = %d, status = '%s', " .	
						"created = from_unixtime(%d), last_updated = from_unixtime(%d) where id = %d",
								$dbconn->real_escape_string($this->get('content')),
								$this->get ('score'),
								$dbconn->real_escape_string($this->get('status')),
								$this->get ('created_timestamp'),
								$this->get ('last_updated_timestamp'),
								$this->get ('id')
							);

				$this->execute_query ($qu);
			}

		// Make sure that metadata is consistent
		$this->read_metadata_from_db ();
	}

	// implementation of abstract method parent::delete_from_db
	public function delete_from_db ()
	{
		$this->check_dbconn ();

		// In theory, this should never be called: if we don't want a review to be visible, we just mark it as "locked"
		$qu = sprintf ("delete from user_reviews where id = %d", $this->get('id'));
		execute_query ($this->dbconn, $qu);

		$this->initialise_data ();
	}

	public function render_to_xml ()
	{
		/*
		 * XML structure
		 	<review blog_id='' user_id = ''>
				<game_name></game_name>
				<loginid></loginid>
				<created></created>
				<last_updated></last_updated>
		 		<content></content>
		 		<score></score>
		 		<recommendation></recommendation>
		 		<status></status>
		 	</review>
		 *
		 */

		$this->check_dbconn ();

		$str = '';
		$c_stamp = date ("Y-m-d", $this->get('created_timestamp'));
		$lu_stamp = date ("Y-m-d H:i:s", $this->get('last_updated_timestamp'));
		
		// REMINDER: generate_xml_node_str ($node_name, $node_value, $attributes, [$escape_cdata, [$self_closed]])
		$inner_str = generate_xml_node_str ("loginid", 		$this->get('loginid'), 		FALSE, TRUE);
		$inner_str = generate_xml_node_str ("game_name", 	$this->get('game_name'), 	FALSE, TRUE);
		$inner_str .= generate_xml_node_str ("created",		$c_stamp, 			FALSE, TRUE);
		$inner_str .= generate_xml_node_str ("last_updated",	$lu_stamp, 			FALSE, TRUE);
		$inner_str .= generate_xml_node_str ("content",		$this->get('content'), 		FALSE, TRUE);
		$inner_str .= generate_xml_node_str ("status", 		$this->get('status'), 		FALSE, TRUE);

		// Not sure if we need both, but...
		$inner_str .= generate_xml_node_str ("score",		$this->get('score'),		FALSE, TRUE);
		$inner_str .= generate_xml_node_str ("recommendation",	$this->get('recommendation'),	FALSE, TRUE);


		$tmp_array = array ('blog_id' => $this->get('blog_id'), 'user_id' => $this->get('user_id'));
		$str = generate_xml_node_str ('review', $inner_str, $tmp_array, FALSE);

		return $str;
	}

}
?>
