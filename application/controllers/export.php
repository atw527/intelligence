<?php

class export extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		
		//if (!$this->input->is_cli_request()) die("Just what do you think you are doing, Dave?");
	}

	public function index()
	{
		if (!$this->input->is_cli_request()) $this->output->enable_profiler(TRUE);
		
		$this->m_facts->file = fopen('/tmp/export.txt', 'w');
		$this->m_facts->export('weather.current.klot');
		fclose($this->m_facts->file);
		
		echo '<pre>';
		readfile('/tmp/export.txt');
		echo '</pre><br /><br />';
	}
}

/* ?> */
