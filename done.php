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

$myname = "OCF";

$folderId = 'root'; //google drive folder ID or root
$originFileId = ''; //which template you going to copy

$calendarId = 'primary'; //google calendar id (like an email address) or use `primary` for your own calendar
$timeZone = 'Asia/Taipei';
$timeZone_plus = '+08:00';
$this_time = '本月';
$meeting_name = "工作會議";
$file_desc = '會議記錄';
$string = file_get_contents("members.json");
$members = json_decode($string, true);

$attendees = array();
foreach ($members as $key => $value) {
  $attendees[] = array('email' => $value['email']);
}


function validateDate($date)
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

$m = '';
$t = '';
$f = 0;

while (!preg_match('/\d{2}-\d{2}/', $m)) {
  while (!$f) {
    $m = readline($this_time." ".$myname." ".$meeting_name."日期？(e.g. 06-12, 中斷請按 Ctrl+C) ");
    $f = validateDate(date("Y").'-'.$m);    
  }
}

$f = 0;
while (!preg_match('/\d{2}:\d{2}/', $t)) {
  while (!$f) {
    $t = readline("時間？(e.g. 21:30, 22:00, 中斷請按 Ctrl+C) ");
    $f = preg_match("/(2[0-3]|[01][0-9]):([0-5][0-9])/", $t);
  }
}

$date = date("Y").'-'.$m.' '.$t.':00';
$endtime = date("Y-m-d"."\T"."H:i:s", strtotime( $date . ' + 2 hour'));


$y = date("Y");
$file_title = $myname.' '.$y.'-'.$m;

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


// Get the API client and construct the service object.
$client = getClient();
$service = new Google_Service_Drive($client);


// Print the names and IDs for up to 10 files.
$optParams = array(
  'pageSize' => 100,
  'fields' => "nextPageToken, files(id, name)",
  'q' => "'".$folderId."' in parents and trashed=false"
);

$results = $service->files->listFiles($optParams);
foreach ($results->files as $key => $value) {
  $this_name = (string) $value->name;
  // echo $this_name;
  if (preg_match('/(?<!\d)\d{4}-\d{2}-\d{2}(?!\d)/', $this_name, $output)) {
    $file_date[$output[0]] = $value->id;
  }
}

foreach ($file_date as $key => $value) {
  print_r($key.'(the last file) -->');
  print_r($value);
  $last_file = $value;
  break;
}

$copiedFile = new Google_Service_Drive_DriveFile();
$copiedFile->setName($file_title);

try {
	$res = $service->files->copy($originFileId, $copiedFile);
	print_r("\r\ncopy done: ");
	print_r($res['id']);

} catch (Exception $e) {
	print "An error occurred: " . $e->getMessage();
}

// if you want not only copy but also change NEW document's contain,
// use updateFile and put html to $data.
// =================================================================

// updateFile($service, $res['id']);

// function updateFile($service, $fileId) {
// 	global $file_title, $last_file, $file_desc;
//   try {
//     // First retrieve the file from the API.
//     // $file = $service->files->get($fileId);
//     $newFile = new Google_Service_Drive_DriveFile();
//     $newFile->setName($file_title.' '.$file_desc);

//     $data = '<html><head><meta content="text/html; charset=UTF-8" http-equiv="content-type"><style type="text/css">ol{margin:0;padding:0}table td,table th{padding:0}.c1{padding-bottom:3pt;line-height:1.149999976158142;page-break-after:avoid;orphans:2;widows:2;text-align:center;height:11pt}.c3{font-size:16pt;font-family:"Trebuchet MS";font-style:normal;color:#000000;font-weight:bold}.c9{padding-bottom:3pt;line-height:1.149999976158142;page-break-after:avoid;text-align:center}.c15{font-weight:normal;text-decoration:none;vertical-align:baseline;font-style:normal}.c0{padding-bottom:12pt;orphans:2;widows:2;height:11pt}.c7{font-size:11pt;font-family:"Arial";color:#1155cc;text-decoration:underline}.c5{padding-top:10pt;padding-bottom:12pt;page-break-after:avoid}.c14{background-color:#ffffff;max-width:451.4pt;padding:72pt 72pt 72pt 72pt}.c6{font-size:11pt;font-family:"Arial";color:#000000}.c12{font-size:21pt;font-family:"Trebuchet MS";color:#000000}.c2{color:inherit;text-decoration:inherit}.c4{orphans:2;widows:2}.c10{font-weight:bold}.c11{text-align:center}.c13{margin-left:36pt}.c8{height:11pt}.title{padding-top:24pt;color:#000000;font-weight:bold;font-size:36pt;padding-bottom:6pt;font-family:"Arial";line-height:1.0;page-break-after:avoid;text-align:left}.subtitle{padding-top:18pt;color:#666666;font-size:24pt;padding-bottom:4pt;font-family:"Georgia";line-height:1.0;page-break-after:avoid;font-style:italic;text-align:left}li{color:#000000;font-size:11pt;font-family:"Arial"}p{margin:0;color:#000000;font-size:11pt;font-family:"Arial"}h1{padding-top:32pt;color:#000000;font-weight:bold;font-size:20pt;padding-bottom:18pt;font-family:"Arial";line-height:1.149999976158142;text-align:left}h2{padding-top:29.2pt;color:#000000;font-weight:bold;font-size:16pt;padding-bottom:17.2pt;font-family:"Arial";line-height:1.149999976158142;text-align:left}h3{padding-top:28pt;color:#434343;font-weight:bold;font-size:14pt;padding-bottom:16pt;font-family:"Arial";line-height:1.149999976158142;text-align:left}h4{padding-top:26.8pt;color:#666666;font-weight:bold;font-size:12pt;padding-bottom:16.8pt;font-family:"Arial";line-height:1.149999976158142;text-align:left}h5{padding-top:24.8pt;color:#666666;font-weight:bold;font-size:11pt;padding-bottom:16.8pt;font-family:"Arial";line-height:1.149999976158142;text-align:left}h6{padding-top:30pt;color:#666666;font-weight:bold;font-size:11pt;padding-bottom:22pt;font-family:"Arial";line-height:1.149999976158142;font-style:italic;text-align:left}</style></head><body class="c14"><p class="c1"><span class="c6 c15"></span></p><p class="c4 c9"><span class="c12">&nbsp;&nbsp;&nbsp;'.$file_title.' 會議記錄</span></p><p class="c4 c8 c11"><span class="c12"></span></p><p class="c4"><span class="c6">&#20027;&#24109;: &nbsp;</span></p><p class="c4"><span class="c6">&#20986;&#24109;: (&#35531;&#31805;&#21517;) &#65306;</span></p><p class="c4"><span class="c6">&#25214;&#23563;&#19979;&#19968;&#27425;&#20027;&#24109;&#65306;</span></p><hr><p class="c0"><span class="c6"></span></p><h1 class="c4 c5"><span class="c3">&#22238;&#39015;</span></h1><p class="c4 c13"><span class="c7"><a class="c11" href="https://www.google.com/url?q=https://drive.google.com/open?id%3D'.$last_file.'&amp;sa=D&amp;ust=1467361038021000&amp;usg=AFQjCNGrOf7m19vm3T9Xq6oPnWpdfArfjg">&#19978;&#27425;&#26371;&#35696;&#32000;&#37636;</a></span></p><p class="c4 c13 c8"><span class="c7"><a class="c2" href="https://www.google.com/url?q=https://www.google.com/url?q%3Dhttps://drive.google.com/open?id%253D1Cr0IC3cq-T_WwdaVzl6nUZzDwkvAbpEL6Rw-CaD6ZA4%26sa%3DD%26ust%3D1467361038021000%26usg%3DAFQjCNGrOf7m19vm3T9Xq6oPnWpdfArfjg&amp;sa=D&amp;ust=1467361597769000&amp;usg=AFQjCNG3ApxpibpCz1WLetw_vQV54VOZ3g"></a></span></p><p class="c0 c13"><span class="c7"><a class="c2" href="https://www.google.com/url?q=https://www.google.com/url?q%3Dhttps://drive.google.com/open?id%253D1Cr0IC3cq-T_WwdaVzl6nUZzDwkvAbpEL6Rw-CaD6ZA4%26sa%3DD%26ust%3D1467361038021000%26usg%3DAFQjCNGrOf7m19vm3T9Xq6oPnWpdfArfjg&amp;sa=D&amp;ust=1467361597769000&amp;usg=AFQjCNG3ApxpibpCz1WLetw_vQV54VOZ3g"></a></span></p><h1 class="c5 c4"><span class="c3">&#22577;&#21578;&#20107;&#38917;</span></h1><p class="c0"><span class="c3"></span></p><h1 class="c5 c4"><span class="c3">&#35342;&#35542;&#20107;&#38917;</span></h1><p class="c4 c8"><span class="c3"></span></p><p class="c4"><span class="c6 c10">Action Item:</span></p><p class="c4 c8"><span class="c6 c10"></span></p></body></html>';
//     $additionalParams = array(
//         'data' => $data
//     );

//     $updatedFile = $service->files->update($fileId, $newFile, $additionalParams);

//     print("\r\nupdate done");
//   } catch (Exception $e) {
//     print "An error occurred: " . $e->getMessage();
//   }
// }

print_r("\r\n");
print_r($file_desc."：");
print_r("\r\n");
print_r('https://docs.google.com/document/d/'.$res['id'].'/edit'."\r\n");

$str = $y.'-'.$m;
$this_m = date("m", strtotime($str));
$summary = $myname.' '.$this_m.' 月'.$meeting_name;

$event = new Google_Service_Calendar_Event(array(
  'summary' => $summary,
  'description' => $this_time.$meeting_name.'：'.'https://docs.google.com/document/d/'.$res['id'].'/edit',
  'hangoutLink' => $myname.$y.'-'.$m,

  'start' => array(
    'dateTime' => date("Y").'-'.$m.'T'.$t.':00'.$timeZone_plus,
    'timeZone' => $timeZone,
  ),
  'end' => array(
    'dateTime' => $endtime.$timeZone_plus,
    'timeZone' => $timeZone,
  ),
  'attendees' => $attendees,
  'reminders' => array(
    'useDefault' => FALSE,
    'overrides' => array(
      array('method' => 'email', 'minutes' => 24 * 60),
      array('method' => 'popup', 'minutes' => 20),
    ),
  ),
));

$service = new Google_Service_Calendar($client);
$event = $service->events->insert($calendarId, $event);
printf('Event created: %s'."\r\n", $event->htmlLink);
