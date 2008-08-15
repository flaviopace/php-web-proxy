<?php

require_once(dirname(__FILE__) . '/Connector.class.php');
require_once(dirname(__FILE__) . '/Logger.class.php');

/**
 * Curl implementation of the Connector interface.
 */
class CurlConnector implements Connector {
	
	var $headers = array();
	
	function __construct($url) {
		$this->log = new Logger();
		$this->url = $url;
		
		$this->curl = curl_init($this->url);
		
		curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, FALSE);
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($this->curl, CURLOPT_AUTOREFERER, TRUE);
		curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, FALSE); // Disable SSL cert checking
		curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, FALSE); // cURL does not like some SSL certs apparently
		curl_setopt($this->curl, CURLOPT_HEADERFUNCTION, array(&$this, 'extractHeaders'));
		curl_setopt($this->curl, CURLOPT_WRITEFUNCTION, array(&$this, 'extractBody'));
		
		$this->log->debug('Curl Connector initialised with URL: ' . $this->url);
	}
	
	function setLogin($username, $password) {
		$this->username = $username;
		$this->password = $password;
		
		curl_setopt($this->curl, CURLOPT_USERPWD, $this->username . ':' . $this->password);
	}
	
	function setPostInfo($fields) {
		$postdata = '';
		$fileUpload = FALSE;
		
		foreach($fields as $key=>$val) {
			if (substr($val, 0, 1) == '@') {
				$fileUpload = TRUE;
				break;
			}
			
			$postdata .= urlencode($key) . '=' . urlencode($val);
			$postdata .= '&';
		}
		
		curl_setopt($this->curl, CURLOPT_POST, TRUE);
		curl_setopt($this->curl, CURLOPT_POSTFIELDS, $fileUpload == TRUE ? $fields : $postdata);
	}
	
	function setReferer($referer) {
		curl_setopt($this->curl, CURLOPT_REFERER, $url);
	}
	
	function setCookie($cookie) {
		curl_setopt($this->curl, CURLOPT_COOKIE, $cookie);
	}
	
	function connect() {
		curl_exec($this->curl);
		
		$info = curl_getinfo($this->curl);
		$this->httpCode = $info['http_code'];
	}
	
	function disconnect() {
		curl_close($this->curl);
	}
	
	function getHeaders() {
		return $this->headers;
	}
	
	function getHttpCode() {
		return $this->httpCode;
	}
	
	function getOutput() {
		return $this->output;
	}
	
	/** 
	 * Callback function -- cURL will callback this function with each header.
	 */
	function extractHeaders($curl, $header) {
		if (empty($header)) {
			return 0;
		}
		
		if (strpos($header, ':') === FALSE) {
			// Probably HTTP header
			return strlen($header);
		}
		
		$key = strtolower(trim(substr($header, 0, strpos($header, ':'))));
		$val = trim(substr($header, strpos($header, ':') + 1));
		
		if (array_key_exists($key, $this->headers)) {
			if (is_array($this->headers[$key])) {
				$this->headers[$key][] = $val;
			}
			else {
				$this->headers[$key] = array($this->headers[$key], $val);
			}
		}
		else {
			$this->headers[$key] = $val;
		}
		
		$this->log->debug('Set header: ' . $key . ' => ' . $val);
		
		return strlen($header);
	}
	
	/**
	 * Callback function -- cURL will callback this function with the body.
	 */
	function extractBody($curl, $body) {
		if (empty($body)) {
			return 0;
		}
		
		$this->output .= $body;
		
		return strlen($body);
	}
}