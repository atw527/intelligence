<?php

/**
 * Some variable standards:
 * $fact can be in array() or string format
 * $sfact is $fact in string format
 * $afact is $fact in array format
 * 
 * $pfact is the parent fact, likely in array format
 */

class m_facts extends CI_Model
{
	private $cache;
	public $file;
	public $cron;
	
	public function __construct()
	{
		parent::__construct();
		
		$this->cache = array();
		
		$this->cron[] = array('method' => 'delete_old', 'frequency' => 1800);
	}
	
	/**
	 * Return the database row (includes value) of a child fact
	 * 
	 * @access	public
	 * @param	string
	 * @return	object
	 */
	public function get_row($fact)
	{
		$afact = $this->str_to_arr($fact);
		$sfact = $this->arr_to_str($fact);
		
		// the parent fact is one level up, so the parent of nagios.host.atw-05 is nagios.host
		$pfact = $afact;
		$name = array_pop($pfact);
		$name = $this->db->escape_str($name);
		$pfact_id = (count($pfact) == 0) ? -1 : $this->get_id($pfact);
		
		$sql = "SELECT * FROM `facts` WHERE `parent_id` = $pfact_id && `name` = '$name' LIMIT 1";
		$query = $this->db->query($sql);
		
		if ($query->num_rows())
		{
			return $query->row();
		}
		else
		{
			// The requested fact doesn't exist.  We will create one with a null value and return that.
			
			$query = $this->db->query("INSERT INTO `facts` (`parent_id`, `name`) VALUES ($pfact_id, '$name')");
			
			$row = new stdClass;
			$row->fact_id = $this->db->insert_id();
			$row->parent_id = $pfact_id;
			$row->name = $name;
			$row->value = null;
			$row->timestamp = date('Y-m-d g:i:s');
			
			return $row;
		}
	}
	
	/**
	 * Get the value only of a fact
	 * 
	 * This is in a separate function for now because this will eventually be cached in some way.
	 * 
	 * @access	public
	 * @param	string
	 * @return	string
	 */
	public function get_value($fact)
	{
		return $this->get_row($fact)->value;
	}
	
	/**
	 * Get the ID only of a fact
	 * 
	 * This is in a separate function for now because this will eventually be cached in some way.
	 * 
	 * @access	public
	 * @param	string
	 * @return	int
	 */
	public function get_id($fact)
	{
		$afact = $this->str_to_arr($fact);
		$sfact = $this->arr_to_str($fact);
		
		if (count($afact) > 3) return $this->get_row($fact)->fact_id;
		
		$index = array_search($sfact, $this->cache);
		
		if ($index === false)
		{
			$index = $this->get_row($fact)->fact_id;
			$this->cache[$index] = $sfact;
		}
		else
		{
			//echo "Cached entry - $index / {$this->cache[$index]}\n ";
		}
		
		return $index;
	}
	
	/**
	 * Update an existing fact with a new value
	 * 
	 * @access	public
	 * @param	mixed
	 * @param	string
	 * @return	bool
	 */
	public function update($fact, $value)
	{
		$afact = $this->str_to_arr($fact);
		$sfact = $this->arr_to_str($fact);
		
		// TODO hooks
		// check if we have a hook
		// fetch the current fact data if we do to compare the values
		
		// the parent fact is one level up, so the parent of nagios.host.atw-05 is nagios.host
		$pfact = $afact;
		$name = array_pop($pfact);
		$name = $this->db->escape_str($name);
		$pfact_id = (count($pfact) == 0) ? -1 : $this->get_id($pfact);
		
		$value = $this->db->escape_str($value);
		
		$sql = "INSERT INTO `facts` (`parent_id`, `name`, `value`) VALUES ('$pfact_id', '$name', '$value') ON DUPLICATE KEY UPDATE `value` = '$value', `timestamp` = NOW()";
		$query = $this->db->query($sql);
		
		//log_message('info', "Fact update success - $fact / $value");
		
		return $this->db->affected_rows();
	}
	
	/**
	 * Export fact data in a table format
	 * 
	 * m_facts->file must be set to an open file or stream pointer before calling this method!
	 * 
	 * @access	public
	 * @param	mixed
	 * @param	mixed
	 * @return	boolean
	 */
	public function export($fact = array(), $fact_row = false)
	{
		if (!get_resource_type($this->file) == 'file' && !get_resource_type($this->file) == 'stream')
		{
			log_message('error', 'm_facts->export() was called before first setting the file pointer, m_facts->file');
			return false;
		}
		
		$afact = $this->str_to_arr($fact);
		
		if ($fact_row === false)
		{
			$fact_row = $this->get_row($fact);
		}
		
		// write the current fact we are working in
		fwrite($this->file, $this->arr_to_str($afact) . " = " . $fact_row->value . "\n");
		
		// see if we have any children
		
		$sql = "SELECT * FROM `facts` WHERE `parent_id` = $fact_row->fact_id";
		$query = $this->db->query($sql);
		
		foreach ($query->result() as $row)
		{
			$cfact = $afact;
			array_push($cfact, $row->name);
			
			if (!$this->export($cfact, $row)) return false;
		}
		
		return true;
	}
	
	/**
	 * Converts a fact from the string format - nagios.service.atw-05.Ping - to an array format - array("nagios", "service", "atw-05", "Ping")
	 * 
	 * @access	public
	 * @param	string
	 * @return	array
	 */
	public function str_to_arr($sfact)
	{
		if (is_array($sfact)) return $sfact; // silly goose, this is already an array!
		
		// thankfully PHP has a native method for dealing with CSV lines, 
		// this is basically the same thing, but with periods instead
		return str_getcsv($sfact, '.', '"');
	}
	
	/**
	 * Converts a fact from array format to string format
	 * 
	 * @access	public
	 * @param	array
	 * @return	string
	 */
	public function arr_to_str($afact)
	{
		if (!is_array($afact)) return $afact; // silly goose, this is already a string!
		
		foreach ($afact as &$value)
		{
			// I am attempting to follow the format of CSV, except with periods
			if (preg_match('/[". ]+/', $value))
			{
				$value = '"' . str_replace('"', '""', $value) . '"';
			}
		}
		
		return implode('.', $afact);
	}
	
	/**
	 * Converts fact from string format to string format :D
	 * 
	 * Yes, this makes sense because the string is then formatted to our standards
	 * External applications may enclode the keys in quotes when not necessary, for example
	 * 
	 * @access	public
	 * @param	string
	 * @return	string
	 */
	public function str_to_str($sfact)
	{
		return $this->arr_to_str($this->str_to_arr($sfact));
	}
	
	/**
	 * Delete old facts that have not been updated recently
	 * 
	 * @access	public
	 * @param	int
	 * @return	int
	 */
	public function delete_old($hour = 24, $limit = 200)
	{
		/**
		 * This is the query I wanted to run:
		 * DELETE FROM `facts` WHERE `fact_id` NOT IN (SELECT `parent_id` FROM `facts`) && DATE_ADD(`timestamp`, INTERVAL 24 hour) < NOW()
		 * I can't do this because I am referencing the table I want to modify...or something like that.  Ref MySQL Error #1093
		 * The subquery is necessary because the root fact keys are going to be old...I only want to clean up the old children keys
		 */
		
		$hour = (int)$hour;
		$limit = (int)$limit;
		$sql = "SELECT `fact_id` FROM `facts` WHERE `fact_id` NOT IN (SELECT `parent_id` FROM `facts`) && DATE_ADD(`timestamp`, INTERVAL $hour hour) < NOW() LIMIT $limit";
		$query = $this->db->query($sql);
		
		$rows = array();
		if ($query->num_rows())
		{
			foreach ($query->result() as $row)
			{
				$rows[] = $row->fact_id;
			}
			
			$ids = implode(', ', $rows);
			$sql = "DELETE FROM `facts` WHERE `fact_id` IN ($ids)";
			$query = $this->db->query($sql);
			
			$deleted = $this->db->affected_rows();
		}
		else
		{
			$deleted = 0;
		}
		
		log_message('info', 'Cleaned out ' . $deleted . ' stale facts.');
		
		return $deleted;
	}
}

/* ?> */
