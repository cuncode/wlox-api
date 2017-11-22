<?php
class Ethereum {
	public $user,$pass,$host,$port;
	
	public function __construct($user,$pass,$host,$port) {
		$this->user = $user;
		$this->pass = $pass;
		$this->host = $host;
		$this->port = $port;
		$this->from_wei = 1000000000000000000;
	}
	
	public function newAccount() {
		$params = array(
			'jsonrpc'=>'2.0x',
			'method'=>'personal_newAccount',
			'params'=>array($this->pass),
			'id'=>time()
		);
		
		$address = false;
		
		$result = $this->call($params);
		if ($result && $result['result'])
			return $result['result'];
		else if ($result && $result['error'])
			trigger_error($result['error']['message']);

		return false;	
	}
	
	public function call($params) {
		$ch = curl_init();
			
		curl_setopt($ch,CURLOPT_URL,$this->host.':'.$this->port);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch,CURLOPT_POSTFIELDS,str_replace('2.0x','2.0',json_encode($params,JSON_NUMERIC_CHECK)));
		curl_setopt($ch,CURLOPT_POST,1);
		
		$headers = array();
		$headers[] = "Content-Type: application/x-www-form-urlencoded";
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		
		$result = json_decode(curl_exec($ch),true);
		curl_close($ch);
		
		return $result;
	}
}
