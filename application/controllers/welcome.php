<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Welcome extends CI_Controller {

	/**
	 * Index Page for this controller.
	 *
	 * Maps to the following URL
	 * 		http://example.com/index.php/welcome
	 *	- or -  
	 * 		http://example.com/index.php/welcome/index
	 *	- or -
	 * Since this controller is set as the default controller in 
	 * config/routes.php, it's displayed at http://example.com/
	 *
	 * So any other public methods not prefixed with an underscore will
	 * map to /index.php/welcome/<method_name>
	 * @see http://codeigniter.com/user_guide/general/urls.html
	 */
	public function index()
	{
		if (!$this->input->is_cli_request()) $this->output->enable_profiler(TRUE);
		
		$this->load->view('welcome_message');
	}
	
	public function headers()
	{
		stream_context_set_default(array('http' => array('method' => 'HEAD')));
		$headers = get_headers('http://w1.weather.gov/xml/current_obs/all_xml.zip', 1);
		
		header('Content-type: text/plain');
		var_dump($headers);
	}
	
	public function weather()
	{
		$this->fm_weather->import_zip();
	}
	
	public function match()
	{
		header('Content-type: text/plain');
		
		echo $this->m_hooks->hook_exists('nagios.host.atw-05');
	}
}

/* End of file welcome.php */
/* Location: ./application/controllers/welcome.php */
