<?php

date_default_timezone_set('Asia/Taipei');
session_start();

include 'vendor/autoload.php';

define('APPLICATION_NAME', 'Gmail API PHP Quickstart');
define('CREDENTIALS_PATH', '~/.credentials/google.json');
define('CLIENT_SECRET_PATH', __DIR__ . '/client_secret.json');
define('SCOPES', implode(' ', array(
  Google_Service_Gmail::GMAIL_SEND,
  Google_Service_Drive::DRIVE,
  Google_Service_Drive::DRIVE_FILE,
  Google_Service_Drive::DRIVE_METADATA,
  Google_Service_Calendar::CALENDAR
  )
));

$doodleUsername = ''; //doodle login email
$doodlePassword = ''; //password
$strSesFromName = ''; //your name
$strSesFromEmail = ''; //your email

//emaples 
$myname = "OCF";
$this_time = '本月';
$meeting_name = "工作會議";
$file_desc = '會議記錄';
$repeat_time = ['2100', '2130'];
$location = '線上 hangout';

$string = file_get_contents("members.json");
$members = json_decode($string, true);

// subject & mail content => LINE 176:177
// ================================================================================

$attendess = '';
foreach ($members as $key => $value) {
  $attendess .= encodeRecipients($value['name'].' <'.$value['email'].'>'). ', ';
}

$m = '';
while($m < 1 || $m > 12 || !is_numeric($m)) {
  $m = readline("要準備開哪個月份的 ".$myname." ".$meeting_name."？(1-12, 中斷請按 Ctrl+C) ");
}

$y = date("Y");
$title = $myname.' '.$m.' 月份'.$meeting_name;
// $url = 'http://doodle.com/poll/3grs57zsd6k8m8dk'; //for test

$client = new \Causal\DoodleClient\Client($doodleUsername, $doodlePassword);
$client->connect();

$myPolls = $client->getPersonalPolls();

$c = (int) cal_days_in_month(CAL_GREGORIAN, $m, $y);
if($m < 10) $mm = "0".$m; else $mm = $m;
for ($i = 1; $i <= $c; $i++ ) {
	
	$dw = date("w", strtotime($y.'-'.$m.'-'.$i));
	
	if($i < 10) $ii = "0".$i; else $ii = $i;

	if($dw > 0 && $dw < 6) {
		$all_date[$y.$mm.$ii] = $repeat_time;
	}
}

$send_data = [
    'type' => 'date',
    'title' => $title,
    'location' => $location,
    'ifNeedBe' => true,
    'description' => '',
    'name' => $strSesFromName,
    'email' => $strSesFromEmail,
    'dates' => $all_date
];

// print_r(json_encode($all_date));
// print_r(json_encode($send_data));

$newPoll = $client->createPoll($send_data);
$url = $newPoll->getPublicUrl();
echo 'Doodle 已建立: ' . $url;
echo "\r\n";
// Optional, if you want to prevent actually authenticating over and over again
// with future requests (thus reusing the local authentication cookies)
$client->disconnect();

if (php_sapi_name() != 'cli') {
  throw new Exception('This application must be run on the command line.');
}

/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function getClient() {
  $client = new Google_Client();
  $client->setApplicationName(APPLICATION_NAME);
  $client->setScopes(SCOPES);
  $client->setAuthConfigFile(CLIENT_SECRET_PATH);
  $client->setAccessType('offline');

  // Load previously authorized credentials from a file.
  $credentialsPath = expandHomeDirectory(CREDENTIALS_PATH);
  if (file_exists($credentialsPath)) {
    $accessToken = file_get_contents($credentialsPath);
    
  } else {
    // Request authorization from the user.
    $authUrl = $client->createAuthUrl();
    printf("Open the following link in your browser:\n%s\n", $authUrl);
    print 'Enter verification code: ';
    $authCode = trim(fgets(STDIN));

    // Exchange authorization code for an access token.
    $accessToken = $client->authenticate($authCode);

    // Store the credentials to disk.
    if(!file_exists(dirname($credentialsPath))) {
      mkdir(dirname($credentialsPath), 0700, true);
    }
    file_put_contents($credentialsPath, json_encode($accessToken));
    printf("Credentials saved to %s\n", $credentialsPath);
  }
  $client->setAccessToken($accessToken);

  // Refresh the token if it's expired.
   if ($client->isAccessTokenExpired()) {
        $refreshToken = $client->getRefreshToken();
        $client->refreshToken($refreshToken);
        $newAccessToken = $client->getAccessToken();
        $newAccessToken['refresh_token'] = $refreshToken;
        file_put_contents($credentialsPath, json_encode($newAccessToken));
    }

  if ($client->isAccessTokenExpired()) {
    $client->refreshToken($client->getRefreshToken());
    file_put_contents($credentialsPath, json_encode($client->getAccessToken()));
  }
  return $client;
}

/**
 * Expands the home directory alias '~' to the full path.
 * @param string $path the path to expand.
 * @return string the expanded path.
 */
function expandHomeDirectory($path) {
  $homeDirectory = getenv('HOME');
  if (empty($homeDirectory)) {
    $homeDirectory = getenv("HOMEDRIVE") . getenv("HOMEPATH");
  }
  return str_replace('~', realpath($homeDirectory), $path);
}

function encodeRecipients($recipient){
    $recipientsCharset = 'utf-8';
    if (preg_match("/(.*)<(.*)>/", $recipient, $regs)) {
        $recipient = '=?' . $recipientsCharset . '?B?'.base64_encode($regs[1]).'?= <'.$regs[2].'>';
    }
    return $recipient;
}

$client = getClient();
$objGMail = new Google_Service_Gmail($client);

$strSubject = '[調查] '.$title.'時間';

$strMailContent = '請各位填寫 '.$title.'時間調查表 doodle: <b>'.$url.'</b><br/><br/><br/>感謝！<br/><br/>'.$strSesFromName;
$strMailTextVersion = strip_tags($strMailContent, '');
$strRawMessage = "";
$boundary = uniqid(rand(), true);
$subjectCharset = $charset = 'utf-8';

$board_staff = $attendess;
$strRawMessage .= 'To: ' .$board_staff. "\r\n";
$strRawMessage .= 'From: '. encodeRecipients($strSesFromName . " <" . $strSesFromEmail . ">") . "\r\n";

$strRawMessage .= 'Subject: =?' . $subjectCharset . '?B?' . base64_encode($strSubject) . "?=\r\n";
$strRawMessage .= 'MIME-Version: 1.0' . "\r\n";
$strRawMessage .= 'Content-type: Multipart/Mixed; boundary="' . $boundary . '"' . "\r\n";

$strRawMessage .= "\r\n--{$boundary}\r\n";
$strRawMessage .= 'Content-Type: text/plain; charset=' . $charset . "\r\n";
$strRawMessage .= 'Content-Transfer-Encoding: 7bit' . "\r\n\r\n";

$strRawMessage .= "--{$boundary}\r\n";
$strRawMessage .= 'Content-Type: text/html; charset=' . $charset . "\r\n";
$strRawMessage .= 'Content-Transfer-Encoding: quoted-printable' . "\r\n\r\n";
$strRawMessage .= $strMailContent . "\r\n";

try {
    // The message needs to be encoded in Base64URL
    $mime = rtrim(strtr(base64_encode($strRawMessage), '+/', '-_'), '=');
    $msg = new Google_Service_Gmail_Message();
    $msg->setRaw($mime);
    $objSentMsg = $objGMail->users_messages->send("me", $msg);

	  echo "\r\n";
    echo $attendess;
    echo "\r\n";
    print('上述 member 通知信件已出～');
    // var_dump($objSentMsg);

} catch (Exception $e) {
    print($e->getMessage());
    unset($_SESSION['access_token']);
}

