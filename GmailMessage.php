<?php

/**
 * 
 */
class GmailMessage{
	
	function __construct(Google_Service_Gmail_Message $msg){
		$this->message=$msg;
		self::parseHeaders($this);
	}

	private static function parseHeaders($m){
		foreach ($m->message->payload->headers as $h) {
			if($h->name=='Subject') $m->subject=$h->value;
			if($h->name=='From') $m->from=$h->value;
		}
	}

	public function __get($a){
		return $this->message->$a;
	}
}