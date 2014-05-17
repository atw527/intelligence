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
		$afact = $this->to_arr($fact);
		$sfact = $this->to_str($fact);
		
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
			
			// first see if this has a spark
			$has_spark = (int)$this->m_sparks->spark_exists($sfact);
			
			$query = $this->db->query("INSERT INTO `facts` (`parent_id`, `name`, `has_spark`) VALUES ($pfact_id, '$name', $has_spark)");
			
			$row = new stdClass;
			$row->fact_id = $this->db->insert_id();
			$row->parent_id = $pfact_id;
			$row->name = $name;
			$row->value = null;
			$row->has_spark = null;
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
		$afact = $this->to_arr($fact);
		$sfact = $this->to_str($fact);
		
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
		$afact = $this->to_arr($fact);
		$sfact = $this->to_str($fact);
		
		$row = $this->get_row($afact);
		
		if ($row->has_spark && $row->value != $value)
		{
			$this->m_sparks->call_sparks($sfact, $row->value, $value);
		}
		
		$value = $this->db->escape_str($value);
		
		$sql = "UPDATE `facts` SET `value` = '$value', `timestamp` = NOW() WHERE `fact_id` = $row->fact_id LIMIT 1";
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
		
		$afact = $this->to_arr($fact);
		
		if ($fact_row === false)
		{
			$fact_row = $this->get_row($fact);
		}
		
		// write the current fact we are working in
		fwrite($this->file, $this->to_str($afact) . " = " . $fact_row->value . "\n");
		
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
	public function to_arr($fact)
	{
		if (is_array($fact)) return $fact; // silly goose, this is already an array!
		
		// thankfully PHP has a native method for dealing with CSV lines, 
		// this is basically the same thing, but with periods instead
		return str_getcsv($fact, '.', '"');
	}
	
	/**
	 * Converts a fact from array format to string format, or reformats the string
	 * 
	 * @access	public
	 * @param	array
	 * @return	string
	 */
	public function to_str($fact)
	{
		if (!is_array($fact)) $fact = str_getcsv($fact, '.', '"');
		
		foreach ($fact as &$value)
		{
			// I am attempting to follow the format of CSV, except with periods
			if (preg_match('/[". ]+/', $value))
			{
				$value = '"' . str_replace('"', '""', $value) . '"';
			}
		}
		
		return implode('.', $fact);
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
