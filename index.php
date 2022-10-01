<?php
require_once('PhpIpCam/JpegSegment.php');
require_once('PhpIpCam/ImageFromUpstreamCameraExtractor.php');

use PhpIpCam\ImageFromUpstreamCameraExtractor;

//$imageFromUpstreamCameraExtractor = new ImageFromUpstreamCameraExtractor('tcp://192.168.42.60', 4747, '/video?1920x1080', null, null);
$imageFromUpstreamCameraExtractor = new ImageFromUpstreamCameraExtractor('tcp://192.168.1.250', 4747, '/video?1920x1080', null, null);

try {
    $img = $imageFromUpstreamCameraExtractor->getImageFromUpstreamCamera();
} catch (Exception $ex) {
    echo $ex->getMessage();
}

// PHP Fatal error:  Maximum execution time of 30 seconds exceeded in C:\Projects\php_ip_cam\PhpIpCam\ImageFromUpstreamCameraExtractor.php on line 160

/*
$url ="http://192.168.42.60/video?1920x1080";
$port="4747";
$fh = fopen("C:\Projects\php_ip_cam\data.jpg", "w") or die($php_errormsg);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
//curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_ALL);
curl_setopt($ch, CURLOPT_PORT, $port);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_FILE, $fh);
$success = curl_exec($ch);
if (!$success) print "<br><B>Error!!</b><br>";
$output = curl_exec($ch);
$info = curl_getinfo($ch);
curl_exec($ch);
curl_close($ch);
*/

/*
const HOST = "tcp://192.168.42.60";
const PORT = "4747";
const URL  = "/video?1920x1080";

// Establish regular connection to upstream camera
$fp = @fsockopen(HOST, PORT, $errno, $errstr, ini_get("default_socket_timeout"));

$out = "GET ".URL." HTTP/1.1\r\n";
$out .= "Host: $host\r\n";
$out .= "\r\n";
$result = fwrite($fp, $out);

if($result === false) {
	throw new Exception("Could not fwrite to upstream camera");
	return false;
}

fclose($fs);
*/

/*
$content = file_get_contents('http://192.168.42.60:4747/cam/1/frame.jpg');
file_put_contents('C:\Projects\php_ip_cam\data.jpg', $content);
*/
/*
//$host = "tls://192.168.42.60";
$host = "tcp://192.168.42.60";
$port = "4747";
$url  = "/video?1920x1080";
$fingerprint = false; //rely on defaults

DebugMessage("opening fp");
if ($fingerprint !== false) {
	DebugMessage("connect to camera with fingerprint: ". $fingerprint);
	$context = stream_context_create([
		'ssl' => [
			'verify_peer' => false,
			//'allow_self_signed' => true,
			//'peer_fingerprint' =>  $fingerprint, //could not make it work so far
			'verify_peer_name' => false
		]
	]);
	//establish connection to upstream camera with dodgy cert
	$fp = stream_socket_client("$host:$port", $errno, $errstr, ini_get("default_socket_timeout"), STREAM_CLIENT_CONNECT, $context);
} else {
	//establish regular connection to upstream camera
	$fp = @fsockopen($host, $port, $errno, $errstr, ini_get("default_socket_timeout"));
}
if ($fp === false) {
	DebugMessage("Input failed (FP: ". json_encode($fp).", $errstr)");
	return false;
}
//DebugMessage("FP is ok");

//request upstream camera data to send stream
$out = "GET $url HTTP/1.1\r\n";
$out .= "Host: $host\r\n";
$out .= "\r\n";
$result = fwrite($fp, $out);
if($result === false) {
	DebugMessage("Could not fwrite to upstream camera");
	return false;
}

$filename = 'data.jpg';
fwrite($fs, $out);

$fm = fopen ($filename, "w");
stream_set_timeout($fs, 30);
while(!feof($fs) && ($debug = fgets($fs)) != "\r\n" ); // ignore headers
while(!feof($fs)) {
$contents = fgets($fs, 4096);
fwrite($fm, $contents);
$info = stream_get_meta_data($fs);
if ($info['timed_out']) {
  break;
}
}
fclose($fm);
fclose($fs);
*/
/*

          if ($info['timed_out']) {
            // Delete temp file if fails
            unlink($temp_file_name);
            $this->writeDebugInfo("FAILED - Connection timed out: ", $temp_file_name);
          } else {
            // Move temp file if succeeds
            $media_file_name = str_replace('temp/', 'media/', $temp_file_name);
            rename($temp_file_name, $media_file_name);
            $this->writeDebugInfo("SUCCESS: ", $media_file_name);
          }
*/

//DebugMessage("contacted upstream camera, send $result bytes");

/******************************************************************************
 * Description.: print a Debug message to stream
 * Input Value.: $str is the message
 * $seconds is the duration how long the message is shown
 * Return Value: -
 ******************************************************************************/
function DebugMessage($str, $seconds = 4)
{
    //echo $str;
}

/*
http://192.168.42.60:4747/cam/1/frame.jpg

  <button type="button" class="btn btn-default" onclick="window.open('/cam/1/frame.jpg', '_blank').focus();">
      <span class="glyphicon glyphicon-download-alt" aria-hidden="true"></span>&nbsp;Save Photo Here
  </button>
*/