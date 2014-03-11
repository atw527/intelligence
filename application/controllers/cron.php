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
		$lockfile = APPPATH . 'cache/cron.lock';
		
		if (file_exists($lockfile))
		{
			log_message('error', 'Cron is still running...maybe a stuck lock file?');
			return;
		}
		
		file_put_contents($lockfile, 1);
		
		$this->benchmark->mark('cron_start');
		
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
				log_message('info', "Cron Run - $class->$method, freq of $frequency seconds");
				
				$sql = "INSERT INTO `cron` (`class`, `method`) VALUES ('$class', '$method') ON DUPLICATE KEY UPDATE `timestamp` = NOW()";
				$query = $this->db->query($sql);
			}
		}
		
		@unlink($lockfile);
		
		$this->benchmark->mark('cron_end');
		log_message('info', 'Cron run time - ' . $this->benchmark->elapsed_time('cron_start', 'cron_end'));
	}
}

/* ?> */
