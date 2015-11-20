<?php 
class Shares {
	public static function get() {
		global $CFG;
	
		if (!$CFG->session_active)
			return false;
		
		$sql = 'SELECT * FROM shares WHERE id = 1';
		$result = db_query_array($sql);
		$return = $result[0];
		$return['shares_enabled'] = User::$info['shares_enabled'];
		$return['shares_owned'] = User::$info['shares_owned'];
		$return['shares_num_payouts'] = User::$info['shares_num_payouts'];
		$return['shares_earned'] = User::$info['shares_earned'];
		$return['shares_payed'] = User::$info['shares_payed'];
		return $return;
	}
	
	public static function getDividends($count=false,$page=false,$per_page=false,$currency=false) {
		global $CFG;
	
		if (!$CFG->session_active)
			return false;
	
		$page = preg_replace("/[^0-9]/", "",$page);
		$per_page = preg_replace("/[^0-9]/", "",$per_page);
		$currency = preg_replace("/[^a-zA-Z]/", "",$currency);
		$currency_info = (!empty($CFG->currencies[strtoupper($currency)])) ? $CFG->currencies[strtoupper($currency)] : $CFG->currencies['USD'];
	
		$page = ($page > 0) ? $page - 1 : 0;
		$r1 = $page * $per_page;
	
		if (!$count)
			$sql = 'SELECT id,`date`,shares_owned,percentage_owned,(dividend / '.$currency_info['usd_ask'].') AS dividend';
		else
			$sql = 'SELECT COUNT(dividends.id) AS total ';
	
		$sql .= "
		FROM dividends
		WHERE site_user = ".User::$info['id'];
	
		if ($per_page > 0 && !$count)
			$sql .= " ORDER BY id DESC LIMIT $r1,$per_page ";
	
		$result = db_query_array($sql);
	
		if (!$count)
			return $result;
		else
			return $result[0]['total'];
	}
	
	public static function executeOrder($shares,$currency,$buy=false) {
		global $CFG;
	
		if (!$CFG->session_active)
			return false;
		
		$shares = intval(preg_replace("/[^0-9.]/", "",$shares));
		$currency = preg_replace("/[^a-zA-Z]/", "",$currency);
		$currency_info = (!empty($CFG->currencies[strtoupper($currency)])) ? $CFG->currencies[strtoupper($currency)] : false;
		
		$shares_info = self::get();
		$total = round($shares_info['unit_cost_usd'] / $currency_info['usd_ask'],2,PHP_ROUND_HALF_UP) * $shares;
		$total_usd = $shares_info['unit_cost_usd'] * $shares;
		$held = self::sharesHeld();
		$multiplier = ($buy ? -1 : 1);
		$multiplier1 = ($buy ? 1 : -1);

		db_start_transaction();
		
		$user_balances = User::getBalances(User::$info['id'],array($currency_info['id']),true);
		$user_fee = FeeSchedule::getUserFees(User::$info['id']);
		$on_hold = User::getOnHold(1,User::$info['id'],$user_fee,array($currency_info['id']));
		$this_balance = (!empty($user_balances[strtolower($currency)])) ? $user_balances[strtolower($currency)] : 0;
		$this_on_hold = (!empty($on_hold[strtoupper($currency)])) ? $on_hold[strtoupper($currency)]['total'] : 0;
		$error = self::checkPreconditions($shares,$currency,$buy,$total,($this_balance - $this_on_hold),$shares_info['cycle_close_day'],$shares_info['shares_num_for_sale'],$held);
		
		if ($error) {
			db_commit();
			return $error;
		}
		
		db_update('site_users',User::$info['id'],array('shares_owned'=>(User::$info['shares_owned'] + ($shares * $multiplier1)),'shares_payed'=>(User::$info['shares_payed'] + ($total_usd * $multiplier1))));
		User::updateBalances(User::$info['id'],array($currency=>($this_balance + ($multiplier * $total))));	
		db_commit();
		
		return true;
	}
	
	public static function sharesHeld() {
		global $CFG;
		
		$sql = 'SELECT SUM(shares_owned) AS total FROM site_users WHERE shares_enabled = "Y"';
		$result = db_query_array($sql);
		if ($result)
			return $result[0]['total'];
		else
			return false;
	}
	
	public static function checkPreconditions($shares,$currency,$buy,$total,$available,$day_of_month,$num_for_sale,$held) {
		global $CFG;
		
		if (!$CFG->session_active)
			return false;
		
		$shares = intval(preg_replace("/[^0-9.]/", "",$shares));
		$currency = preg_replace("/[^a-zA-Z]/", "",$currency);
		$currency_info = (!empty($CFG->currencies[strtoupper($currency)])) ? $CFG->currencies[strtoupper($currency)] : false;
		
		if ($day_of_month != date('j'))
			return array('error'=>array('message'=>str_replace('[day]',$day_of_month,Lang::string('shares-wrong-day-error')),'code'=>'SHARES_WRONG_DAY'));
		if (($shares + $held) > $num_for_sale)
			return array('error'=>array('message'=>str_replace('[shares]',($num_for_sale - $held),Lang::string('shares-too-many-error')),'code'=>'SHARES_NOT_ENOUGH_AVAILABLE'));
		if (!($shares > 0))
			return array('error'=>array('message'=>Lang::string('shares-zero-error'),'code'=>'SHARES_ZERO'));
		if (!$currency_info)
			return array('error'=>array('message'=>Lang::string('buy-errors-no-currency'),'code'=>'SHARES_INVALID_CURRENCY'));
		if (!($shares > 0))
			return array('error'=>array('message'=>Lang::string('shares-zero-error'),'code'=>'SHARES_ZERO'));
		if (!$currency_info)
			return array('error'=>array('message'=>Lang::string('buy-errors-no-currency'),'code'=>'SHARES_INVALID_CURRENCY'));
		if ($buy && ($total > $available))
			return array('error'=>array('message'=>Lang::string('buy-errors-balance-too-low')),'code'=>'SHARES_BALANCE_TOO_LOW');
		
		return false;
	}
}
?>