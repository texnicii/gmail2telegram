<?php
error_reporting(-1);
if (php_sapi_name() != 'cli') {
	throw new Exception('Must be run on the command line.');
}
require __DIR__.'/vendor/autoload.php';
require __DIR__.'/G2T.php';
require __DIR__.'/GmailMessage.php';

try {
	chatList();
} catch (Exception $e) {
	echo "Error: ".$e->getMessage();
}

function chatList($basedir=__DIR__){
	$dh=opendir($dir=$basedir.'/'.G2T::CHAT_STORAGE);
	while (false!==($chatId=$fileName=readdir($dh))) {
		if($fileName=='.'||$fileName=='..'||!is_dir($chatDir=$dir.'/'.$fileName)) continue;
		if(!file_exists($chatDir.'/credentials.json')) {
			echo "[$fileName] Credentials not found\n"; continue;
		}
		$G2T=new G2T($chatDir);
		$msgs=$G2T->listMessages(true);
		foreach ($msgs as $msg) {
			$G2T->sendToTelegram($msg->from."\n".$msg->subject);
		}
	}
	closedir($dh);
}
