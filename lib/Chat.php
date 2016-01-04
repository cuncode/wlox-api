<?php 
class Chat{
	public static function get($last_id=false) {
		global $CFG;
		
		if ($CFG->memcached) {
			$cached = $CFG->m->get('chat');
			if ($cached) {
				return $cached;
			}
		}
		
		$sql = 'SELECT COUNT(DISTINCT user_id) AS total FROM sessions';
		$result = db_query_array($sql);
		$total = ($result) ? $result[0]['total'] : 0;
		
		$sql = 'SELECT id,message,username FROM chat '.($last_id > 0 ? 'WHERE id > '.$last_id : '').' ORDER BY id DESC LIMIT 0,30';
		$result = db_query_array($sql);
		
		if ($CFG->memcached)
			$CFG->m->set('chat',array('numUsers'=>$total,'messages'=>($result ? $result : array()),'lastId'=>$result[0]['id']),300);
		
		return array('numUsers'=>$total,'messages'=>$result,'lastId'=>$result[0]['id']);
	}
	
	public static function newMessage($message=false) {
		global $CFG;
	
		if (!$CFG->session_active)
			return false;
	
		if (!$message)
			return false;
		
		if ($CFG->memcached) {
			$cached = $CFG->m->get('chat');
			if ($cached) {
				$cached['messages'] = array_unshift($cached,array('id'=>0,'message'=>$message,'username'=>$handle,'site_user'=>User::$info['id']));
				if (count($cached['messages']) > 30)
					array_pop($cached['messages']);
				
				$CFG->m->set('chat',$cached,300);
			}
		}
		
		$message = preg_replace('/[^\pL 0-9a-zA-Z!@#$%&*?\.\-\_\,]/u','',$message);
		$handle = (empty(User::$info['chat_handle'])) ? 'Guest-'.User::$info['user'] : User::$info['chat_handle'];
		$id = db_insert('chat',array('message'=>$message,'username'=>$handle,'site_user'=>User::$info['id']));
		return array('lastId'=>$id);
	}
}
?>