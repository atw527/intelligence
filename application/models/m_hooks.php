<?php

class m_hooks extends CI_Model
{
	public $cron;
	public $hooks;
	
	public function __construct()
	{
		parent::__construct();
		
		//$this->cron[] = array('method' => 'delete_old', 'frequency' => 1800);
		
		$this->hooks = array();
	}
	
	private function load_hooks()
	{
		$sql = "SELECT `hook_id`, `fact` FROM `hooks`";
		$query = $this->db->query($sql);
		
		foreach ($query->result() as $row)
		{
			$this->hooks[$row->hook_id] = $row->fact;
		}
		
		return true;
	}
	
	public function hook_exists($fact)
	{
		if (empty($this->hooks)) $this->load_hooks();
		
		foreach ($this->hooks as $hook)
		{
			if (fnmatch($hook, $fact)) return true;
		}
		
		return false;
	}
}

/* ?> */
