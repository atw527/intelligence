<?php

class fm_weather extends CI_Model
{
	private $stations;
	public $cron;
	
	function __construct()
	{
		parent::__construct();
		
		$this->stations = array('KLOT', 'KIND');
		
		$this->cron[] = array('method' => 'update_stations', 'frequency' => 600);
	}
	
	function get_current($parameters = array())
	{
		if (!list($station_id, $metric) = explode('.', $parameters)) throw new Exception('Incorrect parameters supplied.');
		
		$station_id = $this->db->escape($station_id);
		$metric = $this->db->escape($metric);
		$sql = "SELECT * FROM fm_weather_current WHERE `station_id` = $station_id && `metric` = $metric LIMIT 1";
		$query = $this->db->query($sql);
		$row = $query->row();
		
		return $row->value;
	}
	
	function update_stations()
	{
		foreach ($this->stations as $field => $value)
		{
			$this->update_station($value);
			sleep(5);
		}
	}
	
	function update_station($station_id)
	{
		$this->load->library('http');
		$url = "http://api.wunderground.com/weatherstation/WXCurrentObXML.asp?ID=$station_id";
		$xml = $this->http->fetch($url);
		
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
			log_message('error', "Invalid Wunderground Station - $url");
			echo $output;
			return false;
		}
		else
		{
			$data = get_object_vars($data);
			foreach ($data as $field => $value) if (is_string($value))
			{
				$sql = "INSERT INTO fm_weather_current (`station_id`, `metric`, `value`) VALUES ('$station_id', '$field', '$value') ON DUPLICATE KEY UPDATE `value` = '$value', `timestamp` = NOW()";
				$query = $this->db->query($sql);
			}
			
			return true;
		}

	}
}

/* ?> */
