<?php

/**
 * 
 */
class GmailMessage{
	
	function __construct(Google_Service_Gmail_Message $msg){
		$this->message=$msg;
	}

	public function subject(){
		foreach ($this->message->payload->headers as $h) {
			if($h->name=='Subject') return $h->value;
		}
	}

	public function __get($a){
		return $this->message->$a;
	}
}