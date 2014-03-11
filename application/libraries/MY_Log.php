<?php

class MY_Log extends CI_Log
{
	/* just switching INFO and DEBUG to keep application messages without all the framework default messages */
	protected $_levels	= array('ERROR' => '1', 'INFO' => '2',  'DEBUG' => '3', 'ALL' => '4');
}

/* ?> */
