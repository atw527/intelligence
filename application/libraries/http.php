<?php

class http
{
	function __construct()
	{
	
	}
	
	function fetch($url, $cache = 20)
	{
		$cachefile = APPPATH . 'cache/http-' . md5($url) . '.cache';
		$lockfile = APPPATH . 'cache/http-' . md5($url) . '.lock';
		
		/* if the lock file has been there this long, the previous attempt likely crashed before the lock file was removed */
		if (file_exists($lockfile) && filemtime($lockfile) + 60 < time()) unlink($lockfile);
		
		if (file_exists($lockfile) && filemtime($cachefile) + $cache > time())
		{
			return file_get_contents($cachefile);
		}
		
		file_put_contents($lockfile, 1);
		
		$crl = curl_init();
		curl_setopt($crl, CURLOPT_URL, $url);
		curl_setopt($crl, CURLOPT_RETURNTRANSFER, 1); 
		curl_setopt($crl, CURLOPT_CONNECTTIMEOUT, 5); 
		curl_setopt($crl, CURLOPT_TIMEOUT, 5); 
		$res = curl_exec($crl);

		if (curl_errno($crl) == 28) 
		{   
			/* connection timeout */
			log_message('error', "cURL connection timeout - $url");
			return false;
		}   
		else if (curl_errno($crl))
		{   
			log_message('error', curl_error($crl) . ' (' . curl_errno($crl) . ')');
			return false;
		}
		else
		{
			log_message('info', "cURL GET - $url");
			
			curl_close($crl);
			
			file_put_contents($cachefile, $res);
			@unlink($lockfile);
			
			return $res;
		}
	}
}

/* ?> */
