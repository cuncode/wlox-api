<?php
class Gateways {
	public static function get($key) {
		global $CFG;
		
		$key = preg_replace("/[^0-9a-zA-Z\.\-\_]/", "",$key);
		
		if (!$CFG->session_active || !$key)
			return false;
		
		$sql = 'SELECT * FROM gateways WHERE `key` = "'.$key.'"';
		$result = db_query_array($sql);
		
		if (!$result)
			return false;
		
		return $result[0];
	}
}
