<?php

class nagios extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
	}

	public function index($name, $value)
	{
		global $argv;
		var_dump($argv);
		var_dump($name);
		var_dump($value);
		echo "\n";

		$this->m_facts->add('nagios', $name, 'int', $value);
	}
}

/* ?> */
