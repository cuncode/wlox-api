<?php
class Transactions {
	public static function get($count=false,$page=false,$per_page=false,$currency=false,$user=false,$start_date=false,$type=false,$order_by=false,$order_desc=false,$public_api_all=false,$dont_paginate=false) {
		global $CFG;
		
		if ($user && !(User::$info['id'] > 0))
			return false;
		
		$page = preg_replace("/[^0-9]/", "",$page);
		$per_page = preg_replace("/[^0-9]/", "",$per_page);
		$per_page1 = ($per_page == 1 || $per_page == 5) ? 5 : $per_page;
		$page = preg_replace("/[^0-9]/", "",$page);
		$currency = preg_replace("/[^a-zA-Z]/", "",$currency);
		$start_date = preg_replace ("/[^0-9: \-]/","",$start_date);
		
		$page = ($page > 0) ? $page - 1 : 0;
		$r1 = $page * $per_page;
		$order_arr = array('date'=>'transactions.id','btc'=>'transactions.btc','btcprice'=>'usd_price','fiat'=>'usd_amount','fee'=>'usd_fee');
		$order_by = ($order_by) ? $order_arr[$order_by] : 'transactions.id';
		$order_desc = ($order_desc) ? 'ASC' : 'DESC';
		$user = ($user) ? User::$info['id'] : false;
		$usd_info = $CFG->currencies['USD'];
		$usd_field = 'usd_ask';
		$currency_info = (!empty($CFG->currencies[strtoupper($currency)])) ? $CFG->currencies[strtoupper($currency)] : false;

		if ($type == 'buy')
			$type = $CFG->transactions_buy_id;
		elseif ($type == 'sell')
			$type = $CFG->transactions_sell_id;
		else
			$type = preg_replace("/[^0-9]/", "",$type);

		if ($CFG->memcached) {
			$cached = null;
			if ($per_page == 5 && !$count && !$public_api_all)
				$cached = $CFG->m->get('trans_l5_'.$currency_info['currency']);
			elseif ($per_page == 1 && !$count && !$public_api_all)
				$cached = $CFG->m->get('trans_l1_'.$currency_info['currency']);
			elseif ($public_api_all)
				$cached = $CFG->m->get('trans_api'.(($per_page) ? '_l'.$per_page : '').(($user) ? '_u'.$user : '').(($currency) ? '_c'.$currency_info['currency'] : '').(($type) ? '_t'.$type : ''));
			
			if (is_array($cached)) {
				if (count($cached) == 0)
					return false;
				
				return $cached;
			}
		}
		
		$price_str = '(CASE WHEN transactions.currency = '.$currency_info['id'].' THEN transactions.btc_price WHEN transactions.currency1 = '.$currency_info['id'].' THEN transactions.orig_btc_price ELSE (CASE transactions.currency1 ';
		$amount_str = '(CASE WHEN transactions.currency = '.$currency_info['id'].' THEN (transactions.btc_price * transactions.btc) WHEN transactions.currency1 = '.$currency_info['id'].' THEN (transactions.orig_btc_price * transactions.btc) ELSE (CASE transactions.currency1 ';
		$usd_str = '(CASE transactions.currency ';
		$currency_abbr = '(CASE IF(transactions.site_user = '.$user.',transactions.currency,transactions.currency1) ';
		$currency_abbr1 = '(CASE transactions.currency ';
		$currency_abbr2 = '(CASE transactions.currency1 ';
		
		foreach ($CFG->currencies as $curr_id => $currency1) {
			if (is_numeric($curr_id) || $currency1['currency'] == 'BTC')
				continue;
	
			if (!empty($currency_info) && $currency1['id'] == $currency_info['id'])
				continue;
	
			$conversion = (empty($currency_info) || $currency_info['currency'] == 'USD') ? $currency1[$usd_field] : $currency1[$usd_field] / $currency_info[$usd_field];
			$price_str .= ' WHEN '.$currency1['id'].' THEN (transactions.orig_btc_price * '.$conversion.')';
			$amount_str .= ' WHEN '.$currency1['id'].' THEN ((transactions.orig_btc_price * transactions.btc) * '.$conversion.')';
			$usd_str .= ' WHEN '.$currency1['id'].' THEN '.$currency1[$usd_field].' ';
			$currency_abbr .= ' WHEN '.$currency1['id'].' THEN "'.$currency1['currency'].'" ';
			$currency_abbr1 .= ' WHEN '.$currency1['id'].' THEN "'.$currency1['currency'].'" ';
			$currency_abbr2 .= ' WHEN '.$currency1['id'].' THEN "'.$currency1['currency'].'" ';
		}
		$price_str .= ' END) END)';
		$amount_str .= ' END) END)';
		$usd_str .= ' END)';
		$currency_abbr .= ' END)';
		$currency_abbr1 .= ' END)';
		$currency_abbr2 .= ' END)';
		
		if (!$count && !$public_api_all)
			$sql = "SELECT transactions.id,transactions.date,transactions.site_user,transactions.site_user1,transactions.btc,transactions.currency,transactions.currency1,transactions.btc_price,transactions.orig_btc_price,transactions.fiat, (UNIX_TIMESTAMP(transactions.date) - ({$CFG->timezone_offset})) AS time_since ".(($user > 0) ? ",IF(transactions.site_user = $user,transaction_types.name_{$CFG->language},transaction_types1.name_{$CFG->language}) AS type, IF(transactions.site_user = $user,transactions.fee,transactions.fee1) AS fee, IF(transactions.site_user = $user,transactions.btc_net,transactions.btc_net1) AS btc_net, IF(transactions.site_user1 = $user,transactions.orig_btc_price,transactions.btc_price) AS fiat_price, IF(transactions.site_user = $user,transactions.currency,transactions.currency1) AS currency" : ", ROUND($price_str,2) AS btc_price, LOWER(transaction_types1.name_en) AS maker_type").", UNIX_TIMESTAMP(transactions.date) AS datestamp ".(($order_by == 'usd_price') ? ', ROUND(('.$usd_str.' * transactions.btc_price),2) AS usd_price' : '').(($order_by == 'usd_amount') ? ', ROUND(('.$usd_str.' * transactions.fiat),2) AS usd_amount' : '');
		elseif ($public_api_all && $user)
			$sql = "SELECT transactions.id AS id, transactions.date AS date, UNIX_TIMESTAMP(transactions.date) AS `timestamp`, transactions.btc AS btc, LOWER(IF(transactions.site_user = $user,transaction_types.name_{$CFG->language},transaction_types1.name_{$CFG->language})) AS side, IF(transactions.site_user1 = $user,transactions.orig_btc_price,transactions.btc_price) AS price, ROUND((IF(transactions.site_user1 = $user,transactions.orig_btc_price,transactions.btc_price) * IF(transactions.site_user = $user,transactions.btc_net,transactions.btc_net1)),2) AS amount, ROUND((IF(transactions.site_user1 = $user,transactions.orig_btc_price,transactions.btc_price) * IF(transactions.site_user = $user,transactions.fee,transactions.fee1)),2) AS fee, $currency_abbr AS currency ";
		elseif ($public_api_all && !$user && $currency)
			$sql = "SELECT transactions.id AS id, transactions.date AS date, UNIX_TIMESTAMP(transactions.date) AS `timestamp`, transactions.btc AS btc, LOWER(transaction_types1.name_{$CFG->language}) AS maker_type, ROUND($price_str,2) AS price, ROUND($amount_str,2) AS amount, IF(transactions.currency != {$currency_info['id']} AND transactions.currency1 != {$currency_info['id']},$currency_abbr2,'{$currency_info['currency']}') AS currency ";
		elseif ($public_api_all && !$user)
			$sql = "SELECT transactions.id AS id, transactions.date AS date, UNIX_TIMESTAMP(transactions.date) AS `timestamp`, transactions.btc AS btc, transactions.btc_price AS price, transactions.orig_btc_price AS price1, ROUND((transactions.btc_price * transactions.btc),2) AS amount, ROUND((transactions.orig_btc_price * transactions.btc),2) AS amount1, $currency_abbr1 AS currency, $currency_abbr2 AS currency1 ";
		else
			$sql = "SELECT COUNT(transactions.id) AS total ";
			
		$sql .= " 
		FROM transactions
		LEFT JOIN transaction_types ON (transaction_types.id = transactions.transaction_type)
		LEFT JOIN transaction_types transaction_types1 ON (transaction_types1.id = transactions.transaction_type1)
		WHERE 1 ";
			
		if ($user > 0)
			$sql .= " AND (transactions.site_user = $user OR transactions.site_user1 = $user) ";
		if ($start_date > 0)
			$sql .= " AND transactions.date >= '$start_date' ";
		if ($type > 0 && !$user)
			$sql .= " AND (transactions.transaction_type = $type OR transactions.transaction_type1 = $type) ";
		elseif ($type > 0 && $user)
			$sql .= " AND IF(transactions.site_user = $user,transactions.transaction_type,transactions.transaction_type1) = $type ";
		if ($currency && $user)
			$sql .= " AND transactions.currency = {$currency_info['id']} ";

		if ($per_page > 0 && !$count && !$dont_paginate)
			$sql .= " ORDER BY $order_by $order_desc LIMIT $r1,$per_page1 ";
		if (!$count && $dont_paginate)
			$sql .= " ORDER BY transactions.id DESC ";

		$result = db_query_array($sql);
		if ($CFG->memcached) {
			if (!$result)
				$result = array();
			
			if (($per_page == 5 || $per_page == 1) && !$count && !$public_api_all) {
				$CFG->m->set('trans_l5_'.$currency_info['currency'],$result,300);
				$result1 = array_slice($result,0,1);
				$CFG->m->set('trans_l1_'.$currency_info['currency'],$result1,300);
			}
			elseif ($public_api_all)
				$CFG->m->set('trans_api'.(($per_page) ? '_l'.$per_page : '').(($user) ? '_u'.$user : '').(($currency) ? '_c'.$currency_info['currency'] : '').(($type) ? '_t'.$type : ''),$result,300);
			
			if ($public_api_all) {
				$cached = $CFG->m->get('trans_cache');
				if (!$cached)
					$cached = array();
				
				$key = (($per_page) ? '_l'.$per_page : '').(($user) ? '_u'.$user : '').(($currency) ? '_c'.$currency_info['currency'] : '').(($type) ? '_t'.$type : '');
				$cached[$key] = true;
				$CFG->m->set('trans_cache',$cached,300);
			}
		}
		
		if ($result && count($result) == 0)
			return false;
		
		if (!$count)
			return $result;
		else
			return $result[0]['total'];
	}
	
	public static function candlesticks($candle_size=false,$currency=false,$c=false,$first=false,$last=false) {
		global $CFG;

		$avail_sizes = array(
			'1min'=>array(60,60,'MINUTE'),
			'3min'=>array(180,60,'MINUTE'),
			'5min'=>array(300,60,'MINUTE'),
			'15min'=>array(900,60,'MINUTE'),
			'30min'=>array(1800,60,'MINUTE'),
			'1h'=>array(3600,3600,'HOUR'),
			'2h'=>array(7200,3600,'HOUR'),
			'4h'=>array(14400,3600,'HOUR'),
			'6h'=>array(21600,3600,'HOUR'),
			'12h'=>array(43200,3600,'HOUR'),
			'1d'=>array(86400,86400,'DAY'),
			'3d'=>array(259200,86400,'DAY'),
			'1w'=>array(604800,604800,'WEEK')
		);
	
		if (!array_key_exists($candle_size,$avail_sizes))
			return false;
		
		$c = preg_replace("/[^0-9]/", "",$c);
		$c = (!$c) ? 300 : $c;
		$first = preg_replace("/[^0-9]/", "",$first);
		$last = preg_replace("/[^0-9]/", "",$last);
		
		
		$limit = false;
		if (!$last) {
			if ($first) {
				$end = '(SELECT `date` FROM transactions WHERE id = '.$first.')';
			}
			else
				$end = 'NOW()';
				
			$start = 'INTERVAL '.(($avail_sizes[$candle_size][0] / $avail_sizes[$candle_size][1]) * $c).' '.$avail_sizes[$candle_size][2];
			$sql = 'SELECT id FROM transactions WHERE `date` < ('.$end.' - '.$start.') ORDER BY id DESC LIMIT 0,1';
			$result = db_query_array($sql);
			if ($result) {
				$last = $result[0]['id'];
			}
				
			if (!$result) {
				$limit = ' LIMIT 0,'.$c;
				$last = false;
			}
		}
		
		if ($CFG->memcached) {
			$first_block = false;
			$last_block = false;
			$finalized = false;
			$cached = array();
			
			if ($first > 0) {
				$block_id = floor(($first - 1) / $c);
				for ($i = $block_id; $i >= 0; $i--) {
					$block = $CFG->m->get('candles_'.$currency.'_'.$i);
					if (!$block)
						continue;
					
					$cached = array_merge($block['items'],$cached);
					$first = $block['items'][0]['id'];
					$first_block = $block;
					
					if ($last > 0 && $first <= $last) {
						$last = $first - ($c - count($first_block));
						break;
					}
				}
			}
			else if ($last > 0) {
				$block_id = floor(($last + 1) / $c);
				while ($block = $CFG->m->get('candles_'.$currency.'_'.$block_id)) {
					$cached = array_merge($cached,$block['items']);
					$last = $block['items'][count($block['items']) - 1]['id'];
					$last_block = $block;
					$block_id++;
				}
			}
			else {
				$block_id = $CFG->m->get('candles_'.$currency.'_last');
				if ($block_id) {
					while ($block = $CFG->m->get('candles_'.$currency.'_'.$block_id)) {
						$cached = array_merge($cached,$block['items']);
						$last = $block['items'][count($block['items']) - 1]['id'];
						$last_block = $block;
						$block_id++;
					}
				}
			}
		
			if (!$first && (count($cached) > 0 && (time() - strtotime($cached[count($cached) - 1]['t']) < $avail_sizes[$candle_size][1]) || $last_block['e_final']))
				return $cached;
			else if ($first_block && (count($first_block['items']) >= 300 || $first_block['s_final']))
				return $cached;
		}
		
		$currency = preg_replace("/[^a-zA-Z]/", "",$currency);
		$currency_info = (!empty($CFG->currencies[strtoupper($currency)])) ? $CFG->currencies[strtoupper($currency)] : $CFG->currencies['USD'];
		$usd_field = 'usd_ask';
		
		$price_str = '(CASE WHEN transactions.currency = '.$currency_info['id'].' THEN transactions.btc_price WHEN transactions.currency1 = '.$currency_info['id'].' THEN transactions.orig_btc_price ELSE transactions.orig_btc_price * (CASE transactions.currency1 ';
		foreach ($CFG->currencies as $curr_id => $currency1) {
			if (is_numeric($curr_id) || $currency1['currency'] == 'BTC')
				continue;
		
			if (!empty($currency_info) && $currency1['id'] == $currency_info['id'])
				continue;
		
			$conversion = (empty($currency_info) || $currency_info['currency'] == 'USD') ? $currency1[$usd_field] : $currency1[$usd_field] / $currency_info[$usd_field];
			$price_str .= ' WHEN '.$currency1['id'].' THEN '.$conversion.' ';
		}
		$price_str .= ' END) END)';

		$where = ' WHERE 1 ';
		if ($first) {
			$where .= ' AND id < '.$first.' ';
		}
		
		if ($last) {
			$where .= ' AND id > '.$last;
		}

		$sql = 'SELECT `date` AS t, '.$price_str.' AS price, id, btc AS vol FROM transactions '.$where.' ORDER BY id DESC '.$limit;
		$result =  db_query_array($sql);

		if ($result) {
			$result = array_reverse($result);
			
			if ($CFG->memcached) {
				$cached_blocks = array();
				$block_before = 0;
				$block_first_id = 0;
				
				foreach ($result as $row) {
					$block_id = intval($row['id'] / $c);
					if (!array_key_exists($block_id,$cached_blocks)) {
						$block = $CFG->m->get('candles_'.$currency.'_'.$block_id);
						$block_before = 0;
						
						if ($block) {
							$cached_blocks[$block_id] = $block;
							$block_first_id = $cached_blocks[$block_id]['items'][0]['id'];
						}
						else {
							$cached_blocks[$block_id] = array('items'=>array(),'s_final'=>array(),'e_final'=>array());
							$block_first_id = 0;
						}
					}
					
					if (count($cached_blocks[$block_id]['items']) > 0 && $row['id'] < $block_first_id) {
						$cached_blocks[$block_id]['items'] = array_splice($cached_blocks[$block_id]['items'],$block_before,0,array($row));
						$block_before++;
					}
					else if (count($cached_blocks[$block_id]['items']) == 0 || $row['id'] > $cached_blocks[$block_id]['items'][count($cached_blocks[$block_id]['items']) - 1]['id']) {
						$cached_blocks[$block_id]['items'][] = $row;
					}	
				}
				
				if ($cached) {
					if (!$first)
						$result = array_merge($cached,$result);
					else 
						$result = array_merge($result,$cached);
				}
				
				
				
				$first_id = $result[0]['id'];
				$last_id = $result[count($result) - 1]['id'];
				$first_block_id = floor($first_id / $c);
				$last_block_id = floor($first_id / $c);
				
				$last_block = false;
				foreach ($cached_blocks as $block_id => $items) {
					$items['s_final'] = (!empty($items['s_final']) || count($items['items']) >= 300 || ($first_id <= $items['items'][0]['id'] && $block_id > $first_block_id));
					$items['e_final'] = (!empty($items['e_final']) || count($items['items']) >= 300 || ($last_id >= $items['items'][count($items['items']) - 1]['id'] && $block_id < $last_block_id));
					$CFG->m->set('candles_'.$currency.'_'.$block_id,$items,0);
					$last_block = $block_id;
				}
				
				if (!$first)
					$CFG->m->set('candles_'.$currency.'_last',$last_block,0);
			}
		}
		else {
			$first_id = ($last) ? $last : 0;
			$last_id = ($first && $last) ? $first_id : $last + 1;
			$result = array(array('first_id'=>$first_id,'last_id'=>$first_id));
		}
		
		return $result;
	}
	
	public static function getTypes() {
		global $CFG;
		
		if ($CFG->memcached) {
			$cached = $CFG->m->get('transaction_types');
			if ($cached) {
				return $cached;
			}
		}
		
		$sql = "SELECT * FROM transaction_types ORDER BY id ASC ";
		$result = db_query_array($sql);
		
		if ($CFG->memcached)
			$CFG->m->set('transaction_types',$result,300);
		
		return $result;
	}
}