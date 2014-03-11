<?php

class fm_weather extends CI_Model
{
	private $stations;
	public $cron;
	
	public function __construct()
	{
		parent::__construct();
		
		$this->stations = array('KLOT', 'KIND');
		
		$this->cron[] = array('method' => 'import_stations', 'frequency' => 600);
		$this->cron[] = array('method' => 'import_zip', 'frequency' => 7200);
	}
	
	/**
	 * Import specific list of stations, as defined in this->__construct()
	 * 
	 * @access	public
	 * @return	boolean
	 */
	public function import_stations()
	{
		$this->load->library('http');
		
		foreach ($this->stations as $field => $value)
		{
			$url = "http://w1.weather.gov/xml/current_obs/$value.xml";
			$xml = $this->http->fetch($url);
			
			$this->import_station($xml, $url);
			
			// don't want to hammer the server with get requests
			sleep(1);
		}
		
		return true;
	}
	
	/**
	 * Import specific station, called only from this->import_stations
	 * 
	 * @access	private
	 * @param	string
	 * @param	string
	 * @return	boolean
	 */
	private function import_station($xml, $url)
	{
		try
		{
			$data = new SimpleXMLElement($xml);
		}
		catch (Exception $ex)
		{
			log_message('error', "Bad XML - $url");
			return false;
		}

		if ($data->station_id == '') 
		{
			log_message('error', "Invalid Station - $url");
			echo $output;
			return false;
		}
		else
		{
			$data = get_object_vars($data);
			$station_id = $data['station_id'];
			
			$this->db->trans_start();
			
			foreach ($data as $field => $value) if (is_string($value))
			{
				$fact = "weather.current.$station_id.$field";
				$this->m_facts->update($fact, $value);
			}
			
			$this->db->trans_complete();
			if ($this->db->trans_status() === FALSE) log_message('error', "Database update transaction failed for $station_id");
			
			return true;
		}

	}
	
	/**
	 * Imports ALL the current weather stations from the NWS
	 * 
	 * @access	public
	 * @return	boolean
	 */
	public function import_zip()
	{
		$zipfile = APPPATH . 'cache/all_xml.zip';
		$zipdir = APPPATH . 'cache/all_xml/';
		
		// we want to grab the headers to see if the file has been updated on the server
		stream_context_set_default(array('http' => array('method' => 'HEAD')));
		$headers = get_headers('http://w1.weather.gov/xml/current_obs/all_xml.zip', 1);
		$server_mtime = strtotime($headers['Last-Modified']);
		$local_mtime = filemtime($zipfile);
		
		log_message('info', 'all_xml.zip server modified time: ' . date('Y-m-d g:i:s', $server_mtime));
		log_message('info', 'all_xml.zip local modified time: ' . date('Y-m-d g:i:s', $local_mtime));
		
		/* no sense in running if the file on the server hasn't updated yet */
		if ($local_mtime + 60 > $server_mtime) return;
		
		shell_exec('rm -f ' . $zipfile);
		shell_exec('wget http://w1.weather.gov/xml/current_obs/all_xml.zip -O ' . $zipfile);
		shell_exec("unzip $zipfile -d $zipdir");
		
		$files = scandir($zipdir);
		
		$i = 0;
		
		foreach ($files as $file) if (substr($file, -3, 3) == 'xml')
		{
			$xml = file_get_contents($zipdir . $file);
			$this->import_station($xml, $zipdir . $file);
			
			echo "Imported $file \n";
			
			//if ($i++ > 25) break;
		}
		
		shell_exec('rm -rf ' . $zipdir);
		
		return true;
	}
}

/* ?> */
