<?

abstract class parent_template
{
	public $dbconn			= FALSE;
	public $type			= 'parent';
	public $data			= FALSE;
	public $current_user		= FALSE;
	public $updated			= FALSE;

	public function __construct ($dbconn, $id = FALSE, $user_obj = FALSE)
	{
		$this->initialise_db ($dbconn);
		$this->check_dbconn ();

		$this->initialise_data ();

		if ($id != FALSE)
			$this->read_from_db ($id);

		if ($user_obj != FALSE)
			$this->set_current_user ($user_obj);

		$this->updated = FALSE;
	}

	public function __sleep ()
	{
		$this->dbconn	= FALSE;

		// __sleep is meant to return a list of the "serialisable" attributes the object contains
		return array ('type', 'data');
	}

	public function initialise_db ($dbconn)
	{
		$this->dbconn = $dbconn;
	}

	public function check_dbconn ()
	{
		$dbc = $this->dbconn;

		if (!$dbc || $dbc->connect_errno > 0)
			throw new Exception ("{$this->type}_template->check_db: database connection not initialised");
	}

	public function set_current_user (user_template $user_obj)
	{
		$this->current_user = $user_obj;
	}

	public function set ($name, $value)
	{
		$this->data[$name] = $value;
		$this->updated = TRUE;
	}

	public function get ($name, $trim_output = FALSE)
	{
		if (array_key_exists ($name, $this->data) === FALSE)
			throw new Exception ("{$this->type}_template: data item [$name] not found");

		$value = $this->data[$name];

		return $value;
	}

	/*
	 * NOTE: this function does not update the timestamp(s) associated with the entity.  Instead, it updates the "global"
	 * timestamp used by the caching algorithms to determine if the caches are still valid
	 */
	public function update_timestamp_in_db ()
	{
		$this->check_dbconn ();

		$qu = "update global_config set config_value=now() where name='last_updated'";
		execute_query ($this->dbconn, $qu);
	}

	public function write_to_db ()
	{
		$this->check_dbconn ();

		if ($this->updated == TRUE)
		{
			$this->validate ();
			$this->update_entity_in_db ();
			$this->update_timestamp_in_db ();
		}

		// We don't throw an error, as this would force the caller to explicitly check $this->updated, which defeats the purpose of setting it
		// internally...
	}

	public function throw_exception ($data)
	{
		// Distinct overkill, but easy!
		$backtrace = debug_backtrace();
		$fn_name = $backtrace[1]['function'];
		
		$str = "{$this->type}_template - entity ID {$this->get('id')}: $fn_name: ";

		switch (gettype ($data))
		{
				case 'string':
					$str .= $data;
					break;

				case 'object':
					if (get_class ($data) == 'Exception' || get_parent_class ($data) == 'Exception')
						$str .= $data->getMessage();
					else
						$this->throw_exception ("non-Exception class {get_class ($data}");
					break;

				default:
					$this->throw_exception ("unknown type {get_class ($data}");
		}
					
		throw new Exception ($str);
	}

	public function render_to_text ($max_length = FALSE)
	{
		// Strip and truncate the content as appropriate
		return html_to_text ($this->get('content'), $max_length);
	}

	public function validate_entity ($table, $id)
	{
		$this->check_dbconn ();

		if ($id == '' || $id == 0)
		{
			$this->throw_exception ("id not set for table [$table]");
		}
		else
		{
			$qu = sprintf ("select id from %s where id = %d", $dbconn->real_escape_string($table), (int) $id);

			// This call will automatically throw an exception if no records are found
			$rs = get_single_resultset ($this->dbconn, $qu, TRUE);
		}
	}

	public function execute_query ($qu, $create = FALSE)
	{
		$this->check_dbconn ();

		try
		{
			if ($create)
				$db_id = execute_query ($this->dbconn, $qu, TRUE);
			else
				execute_query ($this->dbconn, $qu, $create);
		}
		catch (Exception $e_obj)
		{
			$this->throw_exception ("failed to process [$qu] - {$e_obj->getMessage()}");
		}

		if ($create)
			return $db_id;
	}

	abstract public function update_entity_in_db ();
	abstract public function read_from_db ($id);
	abstract public function read_metadata_from_db ();
	abstract public function delete_from_db ();
	abstract public function initialise_data ();
	abstract public function validate ();
	abstract public function render_to_xml ();
}
 
?>
