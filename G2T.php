<?php
use \unreal4u\TelegramAPI\HttpClientRequestHandler;
use \unreal4u\TelegramAPI\TgLog;
use \unreal4u\TelegramAPI\Telegram\Methods\SendMessage;
/**
 * 
 */
class G2T{

	const CHAT_STORAGE='chatStorage';
	const BOT_TOKEN_STORAGE='.bot_token';

	public $googleClient;
	private $chatDir;

	function __construct($chatDir){
		$this->chatDir=$chatDir;
		$this->googleClient=self::googleClient($chatDir.'/credentials.json', $chatDir.'/token.json');
		$this->gmailService=new Google_Service_Gmail($this->googleClient);
		$this->filter=self::loadFilter($chatDir);
		if(file_exists($bt=__DIR__.'/'.self::BOT_TOKEN_STORAGE)&&$botToken=file_get_contents($bt)){
			$loop=\React\EventLoop\Factory::create();
			$this->httpClient=new HttpClientRequestHandler($loop);
			$this->telegramService=new TgLog(trim($botToken), $this->httpClient);
		}
	}

	static function loadFilter($chatDir) : string{
		if(!file_exists($filter=$chatDir.'/filter')) return '';
		return trim(file_get_contents($filter));
	}

	static function googleClient($credentialsPath, $tokenPath){
		$client = new Google_Client();
		$client->setApplicationName('Gmail API PHP - G2T');
		$client->setScopes([Google_Service_Gmail::GMAIL_READONLY, Google_Service_Gmail::GMAIL_METADATA]);
		$client->setAuthConfig($credentialsPath);
		$client->setAccessType('offline');
		$client->setPrompt('select_account consent');

		// Load previously authorized token from a file, if it exists.
		// The file token.json stores the user's access and refresh tokens, and is
		// created automatically when the authorization flow completes for the first
		// time.
		if (file_exists($tokenPath)) {
			$accessToken = json_decode(file_get_contents($tokenPath), true);
			$client->setAccessToken($accessToken);
		}

		// If there is no previous token or it's expired.
		if ($client->isAccessTokenExpired()) {
			// Refresh the token if possible, else fetch a new one.
			if ($client->getRefreshToken()) {
				$client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
			} else {
				// Request authorization from the user.
				$authUrl = $client->createAuthUrl();
				printf("Open the following link in your browser:\n%s\n", $authUrl);
				print 'Enter verification code: ';
				$authCode = trim(fgets(STDIN));

				// Exchange authorization code for an access token.
				$accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
				$client->setAccessToken($accessToken);

				// Check to see if there was an error.
				if (array_key_exists('error', $accessToken)) {
					throw new Exception(join(', ', $accessToken));
				}
			}
			// Save the token to a file.
			if (!file_exists(dirname($tokenPath))) {
				mkdir(dirname($tokenPath), 0700, true);
			}
			file_put_contents($tokenPath, json_encode($client->getAccessToken()));
		}
		return $client;
	}

	public function listMessages(bool $verbose=false) : array {
		$userId='me';
		$service=$this->gmailService;
		$pageToken=null;
		$messages=[];
		$opt_param['q']=$this->filter;
		$opt_param['includeSpamTrash']=true;
		do {
			if ($pageToken) {
				$opt_param['pageToken']=$pageToken;
			}
			$messagesResponse = $service->users_messages->listUsersMessages($userId, $opt_param);
			if ($messagesResponse->getMessages()) {
				$m=$messagesResponse->getMessages();
				foreach ($m as $val) {
					$message=$service->users_messages->get($userId, $val->getId());
					if($message->id==$this->messageOffset()->id)
						break 2;
					elseif((int)$message->internalDate<(int)$this->messageOffset()->date)
						break 2;
					if($verbose) echo "[$message->id, $message->internalDate] ".mb_substr($message->snippet, 0, 64)."...\n";
					$messages[]=new GmailMessage($message);
				}
				$pageToken=$messagesResponse->getNextPageToken();
			}
		} while ($pageToken);

		if(count($messages))
			$this->messageOffset(['id'=>$messages[0]->id, 'date'=>$messages[0]->internalDate]);
		return $messages;
	}

	public function messageOffset($set=false){
		$offsetFile=$this->chatDir.'/offset';
		if($set===false){
			if(!isset($this->messageOffset)&&file_exists($offsetFile))
				$this->messageOffset=json_decode(trim(file_get_contents($offsetFile)));
			if(!is_object($this->messageOffset)){
				$this->messageOffset=new \StdClass;
				$this->messageOffset->id=0;	
				$this->messageOffset->date=0;
			}
			return $this->messageOffset;
		}elseif(is_array($set)){
			$this->messageOffset=$set;
			file_put_contents($offsetFile, json_encode($this->messageOffset));
		}
	}
}