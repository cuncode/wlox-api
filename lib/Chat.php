<?php 
class Chat{
	public static function get($last_id=false) {
		global $CFG;
		
		$sql = 'SELECT COUNT(DISTINCT user_id) AS total FROM sessions';
		$result = db_query_array($sql);
		$total = ($result) ? $result[0]['total'] : 0;
		
		$sql = 'SELECT id,message,username FROM chat '.($last_id > 0 ? 'WHERE id > '.$last_id : '').' ORDER BY id DESC LIMIT 0,30';
		$result = db_query_array($sql);
		return array('numUsers'=>$total,'messages'=>$result,'lastId'=>$result[0]['id']);
	}
	
	public static function newMessage($message=false) {
		global $CFG;
	
		if (!$CFG->session_active) {
			return array('chat-not-logged-in'=>1);
		}
	
		if (!$message)
			return false;
		
		$message = preg_replace('/[^\pL 0-9a-zA-Z!@#$%&*?\.\-\_\,]/u','',$message);
		$handle = (empty(User::$info['chat_handle'])) ? 'Guest-'.User::$info['user'] : User::$info['chat_handle'];
		$id = db_insert('chat',array('message'=>$message,'username'=>$handle,'site_user'=>User::$info['id']));
		return array('lastId'=>$id);
	}
}
?>