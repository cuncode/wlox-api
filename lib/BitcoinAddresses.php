<?php
class BitcoinAddresses{
	static $bitcoin;
	
	public static function get($count=false,$c_currency=false,$page=false,$per_page=false,$user=false,$unassigned=false,$system=false,$public_api=false) {
		global $CFG;
		
		if (!$CFG->session_active || !(User::$info['id'] > 0))
			return false;
		
		$page = preg_replace("/[^0-9]/", "",$page);
		$per_page = preg_replace("/[^0-9]/", "",$per_page);
		$c_currency = preg_replace("/[^0-9]/", "",$c_currency);
		
		if (empty($CFG->currencies[strtoupper($c_currency)]))
			$c_currency = $CFG->currencies[$main['crypto']]['id'];
		else
			$c_currency = $CFG->currencies[strtoupper($c_currency)]['id'];
		
		$page = ($page > 0) ? $page - 1 : 0;
		$r1 = $page * $per_page;
		$user = User::$info['id'];
		
		if (!$count && !$public_api)
			$sql = "SELECT * FROM bitcoin_addresses WHERE 1 ";
		elseif (!$count && $public_api)
			$sql = "SELECT address,`date` FROM bitcoin_addresses WHERE 1 ";
		else
			$sql = "SELECT COUNT(id) AS total FROM bitcoin_addresses WHERE 1  ";
		
		if ($user > 0)
			$sql .= " AND site_user = $user ";
		
		if ($unassigned)
			$sql .= " AND site_user = 0 ";
		
		if ($system)
			$sql .= " AND system_address = 'Y' ";
		else
			$sql .= " AND system_address != 'Y' ";
		
		if ($c_currency)
			$sql .= ' AND c_currency = '.$c_currency.' ';
		
		if ($per_page > 0 && !$count)
			$sql .= " ORDER BY bitcoin_addresses.date DESC LIMIT $r1,$per_page ";
		
		$result = db_query_array($sql);
		if (!$count)
			return $result;
		else
			return $result[0]['total'];
	}
	
	public static function getNew($c_currency=false,$return_address=false,$user_id=false) {
		global $CFG;
		
		if (!$CFG->session_active)
			return false;
		
		$c_currency = preg_replace("/[^0-9]/", "",$c_currency);
		if (!array_key_exists($c_currency,$CFG->currencies))
			return false;
		
		$c_currency_info = $CFG->currencies[$c_currency];
		$wallet = Wallets::getWallet($c_currency);
		$user_id = (!$user_id) ? User::$info['id'] : $user_id;
		
		if ($c_currency_info['currency'] != 'ETH') {
			require_once('../lib/easybitcoin.php');
			$bitcoin = new Bitcoin($wallet['bitcoin_username'],$wallet['bitcoin_passphrase'],$wallet['bitcoin_host'],$wallet['bitcoin_port'],$wallet['bitcoin_protocol']);
			$new_address = $bitcoin->getnewaddress();
		}
		else {
			$ethereum = new Ethereum($wallet['bitcoin_username'],$wallet['bitcoin_passphrase'],$wallet['bitcoin_host'],$wallet['bitcoin_port']);
			$new_address = $ethereum->newAccount();
		}
		
		if (!$new_address)
			return false;
		
		$new_id = db_insert('bitcoin_addresses',array('c_currency'=>$c_currency,'address'=>$new_address,'site_user'=>$user_id,'date'=>date('Y-m-d H:i:s')));
		
		return ($return_address) ? $new_address : $new_id;
	}
	
	public static function validateAddress($c_currency=false,$btc_address) {
		global $CFG;
		
		$btc_address = preg_replace("/[^0-9a-zA-Z]/",'',$btc_address);
		$c_currency = preg_replace("/[^0-9]/", "",$c_currency);
		$wallet = Wallets::getWallet($c_currency);
		$is_valid = false;
		
		if (!$btc_address || !$c_currency)
			return false;
	
		$c_currency_info = $CFG->currencies[$c_currency];
		if ($c_currency_info['currency'] != 'ETH') {
			require_once('../lib/easybitcoin.php');
			$bitcoin = new Bitcoin($wallet['bitcoin_username'],$wallet['bitcoin_passphrase'],$wallet['bitcoin_host'],$wallet['bitcoin_port'],$wallet['bitcoin_protocol']);
			$response = $bitcoin->validateaddress($btc_address);
			$is_valid = $response['isvalid']; 
		}
		else if ($c_currency_info['coin_type'] == 'ETH') {
			$is_valid = (strlen($btc_address) == 42);
		}
	
		return $is_valid;
	}
}