<?php

class m_facts extends CI_Model
{
	function __construct()
	{
		parent::__construct();
	}

	function get($fact)
	{
		if (substr_count($fact, '.') < 2) throw new Exception('Fact "' . $fact . '" does not include enough values.');
		list($class, $method, $parameters) = explode('.', $fact, 3);
		
		/* test the validity of the method */
		$get_method = 'get_' . $method;
		$fm_class = 'fm_' . $class;
		if (!method_exists($this->$fm_class, $get_method)) throw new Exception('Method "' . $method . '" does not exist in ' . $class);
		
		return $this->$fm_class->$get_method($parameters);
	}
}

/* ?> */
