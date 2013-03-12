<?php

class Response304
{
	var $response;
	
	public function __construct($response)
	{
		$this->response = $response; 
		$this->cacheable = false;
	}
	
	public function serve()
	{
		header('HTTP/1.1 304 Not Modified');
		header('Last-Modified:'.$this->response->lastModified, true);
		header('ETag:'.$this->response->eTag, true);
	}
}
