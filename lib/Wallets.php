<?php 
class Wallets {
	public static function getWallet($c_currency=false) {
		global $CFG;
		
		if (!array_key_exists($c_currency,$CFG->currencies))
			return false;
		
		$currency = $CFG->currencies[$c_currency]['id'];
		return DB::getRecord('wallets',$c_currency,0,1);
	}
	
	public static function sumFields($wallet_id,$fields) {
		global $CFG;
	
		if (!is_array($fields) || empty($fields) || empty($wallet_id))
			return false;
	
		$set = array();
		foreach ($fields as $field => $sum_amount) {
			if (!is_numeric($sum_amount))
				continue;
	
			$set[] = $field.' = '.$field.' + ('.$sum_amount.')';
		}
	
		$sql = 'UPDATE wallets SET '.implode(',',$set).' WHERE id = '.$wallet_id;
		return db_query($sql);
	}
}
?>