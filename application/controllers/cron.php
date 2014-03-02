<?php

class cron extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		
		if (!$this->input->is_cli_request()) die("Just what do you think you are doing, Dave?");
	}

	public function index()
	{
		//header('Content-type: text/plain');
		
		$this->load->model('fm_weather');
		
		foreach ($this as &$object)
		{
			if (isset($object->cron)) foreach ($object->cron as &$job)
			{
				$class = get_class($object);
				$method = $job['method'];
				$frequency = $job['frequency'];
				
				$sql = "SELECT * FROM `cron` WHERE `class` = '$class' && `method` = '$method' && DATE_ADD(timestamp, INTERVAL $frequency second) > NOW() LIMIT 1";
				$query = $this->db->query($sql);
				$is_fresh = $query->num_rows();
				
				if ($is_fresh) continue;
				
				/* still here?  better run it! */
				$this->$class->$method();
				log_message('debug', "Cron Run - $class->$method, freq of $frequency seconds");
				
				$sql = "INSERT INTO `cron` (`class`, `method`) VALUES ('$class', '$method') ON DUPLICATE KEY UPDATE `timestamp` = NOW()";
				$query = $this->db->query($sql);
			}
		}
	}
}

/* ?> */
