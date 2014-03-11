<?php

class rpc extends CI_Controller
{
	private $available;
	
	public function __construct()
	{
		parent::__construct();
		
		$this->load->library('xmlrpc');
		$this->load->library('xmlrpcs');
		
		$this->available = true;
	}

	public function index()
	{
		$config['functions']['update_fact'] = array('function' => 'rpc.update_fact');
		$config['functions']['update_facts'] = array('function' => 'rpc.update_facts');
		$config['object'] = $this;

		$this->xmlrpcs->initialize($config);
		$this->xmlrpcs->serve();
	}
	
	private function check_availability(&$ret)
	{
		if (!$this->available)
		{
			$ret = $this->xmlrpc->send_error_message('503', 'Service temporarily unavailable.');
		}
		
		return $this->available;
	}
	
	public function update_fact($request)
	{
		/* TODO: some sort of auth */
		
		if (!$this->check_availability($ret)) return $ret;
		
		$param = $request->output_parameters();
		
		$fact = $param[0];
		$value = $param[1];
		
		$this->m_facts->update($fact, $value);
		
		$response = array(true, 'boolean');
		return $this->xmlrpc->send_response($response);
	}
	
	public function update_facts($request)
	{
		/* TODO: some sort of auth */
		
		if (!$this->check_availability($ret)) return $ret;
		
		$param = $request->output_parameters();
		
		$this->db->trans_start();
		
		foreach ($param as $field => $value)
		{
			$fact = $value[0];
			$value = $value[1];
		
			$this->m_facts->update($fact, $value) or log_message('error', "Fact update returned failure - $fact / $value");
		}
		
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) log_message('error', "Database update transaction failed for rpc->update_facts");
		
		$response = array(true, 'boolean');
		return $this->xmlrpc->send_response($response);
	}
}

/* ?> */
