<?

class user_template extends parent_template
{

	// __construct () 	defined by parent_template
	// check_dbconn () 	defined by parent_template
	// initialise_db ()	defined by parent_template
	// get ()		defined by parent_template

	// implementation of abstract method parent::initialise_data
	public function initialise_data ()
	{
		$this->type = 'user';
		$this->data = array();

		// Set some vaguely sane defaults...
		$this->data['id']			= 0;
		$this->data['name']			= '';
		$this->data['loginid']			= '';
		$this->data['password']			= '';
		$this->data['role']			= '';
		$this->data['last_login']		= 0;
		$this->data['status']			= '';
		$this->data['email']			= '';

		// "helper" fields, for XML output
		$this->data['last_login_timestamp']	= 0;
	}

	public function check_loginid ($loginid)
	{
		$id = FALSE;

		$qu = sprintf ("select id from users where loginid = '%s'", $this->dbconn->real_escape_string($loginid));
		try
		{
			// This function call will automatically trigger an exception if no records are found
			$rs = get_single_resultset ($this->dbconn, $qu, TRUE);
			$id = $rs['id'];
		}
		catch (Exception $e_obj)
		{
			// Do nothing: we just return FALSE
			//$this->throw_exception ($e_obj);
		}

		return $id;
	}

	// implementation of abstract method parent::read_from_db
	public function read_from_db ($id = FALSE, $loginid = '')
	{
		$this->check_dbconn ();

		if ($id)
			$qu = "select u.*, unix_timestamp(u.last_login) last_login_timestamp from users u where u.id = " . (int) $id;
		else
			$qu = sprintf ("select u.*, unix_timestamp(u.last_login) last_login_timestamp from users u where u.loginid = '%s'",
				$this->dbconn->real_escape_string($loginid));

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
			$this->throw_exception ($e_obj);
		}
	}

	public function read_metadata_from_db ()
	{
		/*
		 * PLACEHOLDER: there is currently no user metadata in the database
		 */
	}

	// override of method parent::set
	public function set ($name, $value)
	{
		// We validate the value before setting it
		switch ($name)
		{
			case 'id':
			case 'last_login_timestamp':
				if ($value != (int) $value)
					$this->throw_exception ("non-integer data value for $name: [$value]");
				break;

			case 'name':
			case 'loginid':
				if ($value == '')
					$this->throw_exception ("null value for $name");
				break;

			case 'password':
				if (preg_match ('/^[0-9a-f]{40}$/', $value) == 0)
					$this->throw_exception ("non-SHA1 value for $name");
				break;

			case 'role':
				switch ($value)
				{
					case 'superuser':
					case 'reviewer':
						break;

					default:
						$this->throw_exception ("unknown role type [$value]");
				}
				break;

			case 'status':
				switch ($value)
				{
					case 'active':
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
			case 'last_login':
				$this->data['last_login_timestamp'] = strtotime ($value);
				break;
			case 'last_login_timestamp':
				$this->data['last_login'] = date ("Y-m-d H-i-s", $value);
				break;
		}
	}

	// implementation of abstract method parent::validate
	public function validate ()
	{
		/*
		 * PLACEHOLDER: currently, users do not have any dependant relationships
		 */
	}

	public function set_password ($password)
	{
		// Encodes the password via the SHA1 algorithm in preparation for DB storage
		// TODO: make password encoding more secure
		$str = $this->encode_password ($password);

		$this->set ('password', $str);
	}

	private function encode_password ($password)
	{
		return sha1 ("xbig$password");
	}

	public function check_status ()
	{
		$retval = FALSE;

		// TODO: add some form of site-wide access permission system.  S'a bit overkill at present...
		if ($this->get ('status') == 'active')
			$retval = TRUE;

		return $retval;
	}

	public function check_password ($password)
	{
		$retval = FALSE;

		$str = $this->encode_password ($password);

		if ($str == $this->get ('password'))
			$retval = TRUE;

		return $retval;
	}

	public function write_login_timestamp ($now = FALSE)
	{
		// Lightweight helper function
		if (($now) === FALSE)
			$now = time ();

		$qu = sprintf ("update users set last_login = from_unixtime(%d) where id = %d",
					$now,
					$this->get ('id'));

		$this->execute_query ($qu);

		// TODO: write to audit trail
	}

	// implementation of abstract method parent::write_to_db
	public function update_entity_in_db ($set_last_login_timestamp = FALSE)
	{
		$this->check_dbconn ();
		$this->validate ();

		$dbconn = $this->dbconn;

		// Set timestamps on the review as appropriate
		$db_id = $this->get ('id');
		$now = time ();

		// We always update the last_updated timestamp; we only update the created_timestamp if 
		// the update has been explicitly requested
		if ($set_last_login_timestamp == TRUE)
			$this->set ('last_login_timestamp', $now);

		if ($db_id == 0)
		{
			// We set last_login to -1 for new users...
			$qu = sprintf ("insert into users (name, loginid, password, role, status, email, last_login) " .
						"values ('%s', '%s', '%s', '%s', '%s', '%s', -1)", 
							$dbconn->real_escape_string($this->get ('name')),
							$dbconn->real_escape_string($this->get ('loginid')),
							$dbconn->real_escape_string($this->get ('password')),
							$dbconn->real_escape_string($this->get ('role')),
							$dbconn->real_escape_string($this->get ('status')),
							$dbconn->real_escape_string($this->get ('email'))
						);

			$db_id = $this->execute_query ($qu, TRUE);
			$this->set ('id', $db_id);
		}
		else
		{
			// We assume the loginid field is static...
			$qu = sprintf ("update users set name = '%s', password = '%s', role = '%s', status = '%s', last_login = from_unixtime(%d) " .
							"where id = %d",
							$dbconn->real_escape_string($this->get('name')),
							$dbconn->real_escape_string($this->get('password')),
							$dbconn->real_escape_string($this->get('role')),
							$dbconn->real_escape_string($this->get('status')),
							$this->get ('last_login_timestamp'),
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

		$qu = sprintf ("delete from users where id = %d", $this->get('id'));
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
