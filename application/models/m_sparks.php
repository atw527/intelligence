<?php

class m_sparks extends CI_Model
{
	public $cron;
	public $sparks;
	public $loaded;
	
	public function __construct()
	{
		parent::__construct();
		
		//$this->cron[] = array('method' => 'delete_old', 'frequency' => 1800);
		
		$this->sparks = array();
		$this->loaded = false;
	}
	
	private function load_sparks()
	{
		$sql = "SELECT `spark_id`, `fact`, `class`, `method` FROM `sparks`";
		$query = $this->db->query($sql);
		$this->sparks = $query->result();
		
		$this->loaded = true;
		
		return true;
	}
	
	public function spark_exists($sfact)
	{
		if (!$this->loaded) $this->load_sparks();
		
		foreach ($this->sparks as $spark)
		{
			if (fnmatch($spark->fact, $sfact)) return true;
		}
		
		return false;
	}
	
	public function call_sparks($sfact, $old, $new)
	{
		if (!$this->loaded) $this->load_sparks();
		
		$found = false;
		
		foreach ($this->sparks as $spark)
		if (fnmatch($spark->fact, $sfact))
		{
			$class = $spark->class;
			$method = $spark->method;
			
			$found = true;
			
			if (method_exists($this->$class, $method))
			{
				 $this->$class->$method($sfact, $old, $new);
			}
			else
			{
				log_message('error', "Defined spark pointing to invalid method - $class->$method");
			}
		}
		
		if (!$found) log_message('error', "m_sparks->call_sparks called but no sparks were not found - $sfact");
	}
	
	public function temp_test($sfact, $old, $new)
	{
		log_message('info', "spark: temp_test $old / $new");
	}
	
	public function nagios_test($sfact, $old, $new)
	{
		log_message('info', 'spark: nagios_test');
	}
}

/* ?> */
