<?php 
class Wallets {
	public static function getWallet($c_currency=false) {
		global $CFG;
		
		if (!array_key_exists($c_currency,$CFG->currencies))
			return false;
		
		$currency = $CFG->currencies[$c_currency]['id'];
		return DB::getRecord('wallets',$c_currency,0,1);
	}
}
?>