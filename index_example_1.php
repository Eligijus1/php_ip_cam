<?php
/* *********************************************************************
 * 
 * MJPG-ReStreamer, MJPG-Reverse-Proxy written in PHP
 * 
 * (c) 2015 Stephen Price / Webmad, http://www.webmad.co.nz
 * (c) 2022 Tom Stöveken
 * 
 * This PHP script connects to a MJPG/MJPEG camera stream, extracts just
 * the pictures and retransmits them as a MJPG-stream
 * 
 * In contrast to common HTTP(s) reverse-proxies this script just passes
 * the MJPEG stream and prevents accessing the upstream camera details
 * and settings.
 * 
 * ********************************************************************/

//192.168.42.60:4747/video?1920x1080
//define camera, if the camera supports TLS / SSL put "tls://" in front
//of the host or IP
$host = "tls://192.168.42.60";
$port = "4747";
//the URL varies a lot for each manufacturer and camera model
$url  = "/video?1920x1080";
//If the camera requires Basic authentication provide credentials as
// username:password if no auth is required, set $auth to value false
//$auth = "my_username:my_password_123";
$auth = false;

//if upstream camera uses self-signed certificates regular verification options fail
//the fingerprint (SHA-1, lower case, no ':') can still be verified
//example #1: $fingerprint = '1234567890abcdef1234567890abcdef12345678'
//example #2: $fingerprint = openssl_x509_fingerprint(file_get_contents('camera.pem'))
//example #3: $fingerprint = false; //rely on defaults
//$fingerprint = openssl_x509_fingerprint(file_get_contents('camera.pem'));
//So far this does not work, so we ignore fingerprint for now:
$fingerprint = '1234567890abcdef1234567890abcdef12345678';

//This script grants access by username and password
//Either by Basic auth or by passing those values as GET parameter
$user = "another_username";
$pass = "another_password123";

//to get some debug messages set to "true"
$debug = false;

/**********************************************************************/
if ($debug) {
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);
}
ini_set('output_buffering', 0);
ini_set('zlib.output_compression', 0);

//the boundary can be configured here. The upstream cameras boundary is 
//taken from the headers if $boundaryIn is set to false
$boundaryOut = "MyMultipartBoundaryDoNotStumble";
$boundaryIn = false;

set_time_limit(5);

if( isset($_GET["$user"]) and strcmp($_GET["$user"], $pass) == 0 ) {
	//user is authenticated
}
else {
	//trigger browser to show a password dialog
	if( !isset($_SERVER['PHP_AUTH_USER']) or !isset($_SERVER['PHP_AUTH_PW']) ) {
		header('WWW-Authenticate: Basic realm="Webcam"');
		header('HTTP/1.0 401 Unauthorized');
		echo 'No credentials';
		exit;
	}
	if( strcmp($_SERVER['PHP_AUTH_USER'], $user) != 0 or strcmp($_SERVER['PHP_AUTH_PW'], $pass) != 0 ) {
		header('WWW-Authenticate: Basic realm="Webcam"');
		header('HTTP/1.0 401 Unauthorized');
		echo 'Wrong credentials';
		exit;
	}
}

$key = ftok(__FILE__, 'a');

//allocate or attach to 1 MByte of shared memory 
$shm = shm_attach($key, 1024*1024, 0600) or die("shm_attach failed");

//get IDs of mutexes, all mutexes will be auto-released on exit
$shm_mutex_id = sem_get($key, 1, 0600, true) or die("sem_get failed");

//send headers
header('Cache-Control: no-cache');
header('Pragma: no-cache');
header('Expires: Sat, 01 Jan 2000 01:00:00 GMT');
header('Connection: close');
header('Script-Info: MJPG-ReStreamer');
header("Content-Type: multipart/x-mixed-replace; boundary=$boundaryOut");
echo "--$boundaryOut\r\n";
flush();

/******************************************************************************
Description.: Output image to stream
Input Value.: $img is JPEG encoded image
Return Value: -
******************************************************************************/
function OutputImage($img) {
	global $boundaryOut;

	echo "Content-Type: image/jpeg\r\n".
		"Content-Length: ".strlen($img)."\r\n".
		"X-Timestamp: ".number_format(microtime(true), 6, '.', '')."\r\n".
		"\r\n".
		$img.
		"\r\n--$boundaryOut\r\n";

	flush();
	ob_flush();
	while (@ob_end_flush());
}

/******************************************************************************
Description.: Convert string to Image
Input Value.: $str is the message to write, linebreaks are not supported
              $background is JPEG encoded background image
Return Value: JPEG encoded image data
******************************************************************************/
function TextToImage($str, $background=NULL) {
	if( !is_null($background) ) {
		$img = imagecreatefromstring($background);
		$bgc = imagecolorallocate($img, 255, 255, 255);
		imagefilledrectangle($img, 10, 10, 640-10, 50, $bgc);
	} else {
		$img = imagecreatetruecolor(640, 480);
		$bgc = imagecolorallocate($img, 255, 255, 255);
		imagefilledrectangle($img, 10, 10, 640-10, 480-10, $bgc);
	}
	$tc  = imagecolorallocate($img, 0, 0, 0);
	imagestring($img, 1, 20, 20, $str, $tc);

	ob_start();
	imagejpeg($img, NULL, -1);
	$imgstr = ob_get_contents();
	ob_end_clean();

	return $imgstr;
}

/******************************************************************************
Description.: print a Debug message to stream
Input Value.: $str is the message
              $seconds is the duration how long the message is shown
Return Value: -
******************************************************************************/
function DebugMessage($str, $seconds=4) {
	global $debug;

	if( !$debug) return;

	for($i=0; $i<$seconds*5; $i++) {
		OutputImage(TextToImage("$i: ".$str));
		usleep(200*1000);
	}
}

/******************************************************************************
Description.: Retrieve single image from upstream camera, keep connection
Input Value.: -
Return Value: "false" in case of errors
              JPEG encoded image if function succeeds
******************************************************************************/
function GetImageFromUpstreamCamera() {
	global $auth, $host, $port, $url, $boundaryIn, $fingerprint;

	//filepointer for reading from upstream webcam
	static $fp = false;
	//buffer to keep remainder of previous data chunks
	static $buffer = '';

	//open filepointer to upstream camera if not already open
	if($fp === false) {
		DebugMessage("opening fp");
		/*
         * if the camera cert is self-signed, maybe you need to ignore TLS certificate details
         * WARNING: MITM is possible when setting verify... to false
         * 
         * Documentation is at: https://www.php.net/manual/en/context.ssl.php
         */
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
		DebugMessage("FP is ok");

		//request upstream camera data to send stream
		$out = "GET $url HTTP/1.1\r\n";
		$out .= "Host: $host\r\n";
		if($auth !== false)
			$out .= "Authorization: Basic ". base64_encode($auth) ."\r\n";
		$out .= "\r\n";
		$result = fwrite($fp, $out);
		if($result === false) {
			DebugMessage("Could not fwrite to upstream camera");
			return false;
		}
		DebugMessage("contacted upstream camera, send $result bytes");
	}

	//read data from upstream camera, return single picture
	while (!feof($fp)) {
		$buffer .= fgets($fp);
		$part=$buffer;

		//learn boundary string
		if ($boundaryIn === false) {
			$boundaryStart = strpos($buffer, 'Content-Type: multipart/x-mixed-replace; boundary=');
			if( $boundaryStart === false )
				continue; 
			$boundaryStart = $boundaryStart + strlen('Content-Type: multipart/x-mixed-replace; boundary=');
			$boundaryEnd = strpos($buffer, "\r\n", $boundaryStart);
			if( $boundaryEnd === false )
				continue;
			if ($boundaryStart >= $boundaryEnd)
				continue;
			$boundaryIn = substr($buffer, $boundaryStart, $boundaryEnd-$boundaryStart);
			//DebugMessage("found boundary $boundaryIn");
		}

		//extract single JPEG frame, alternatively we could also search EOI, SOI markers
		$part = substr($part, strpos($part, "--$boundaryIn") + strlen("--$boundaryIn"));
		$part = trim(substr($part, strpos($part, "\r\n\r\n")));
		$part = substr($part, 0, strpos($part, "--$boundaryIn"));

		//substr returns an emtpy string if the string could not be extracted
		//an image should not be smaller then this, so skip if too small
		if(strlen($part) <= 100)
			continue;

		//shorten/maintain the buffer
		$buffer = substr($buffer, strpos($buffer, $part) + strlen($part));

		//return a single image
		return $part;
	}

	return false;
}

/******************************************************************************
Description.: store image and timestamp in shared memory
Input Value.: $img is JPEG encoded image data
Return Value: -
******************************************************************************/
function WriteImageToSharedMemory($img) {
	global $shm, $shm_mutex_id;

	//Enter critical section for writing to shared memory, blocking
	$shm_mutex = sem_acquire($shm_mutex_id, false);

	//place image to shared memory
	if( !shm_put_var($shm, 0, $img) ) {
		DebugMessage("shm_put_var for image failed");
	}

	//place timestamp to shared memory
	if( !shm_put_var($shm, 1, microtime(true)) ) {
		DebugMessage("shm_put_var for timestamp failed");
	}

	//Leaving critical section
	if( !sem_release($shm_mutex_id) ) {
		DebugMessage("sem_release for image failed");
	}
}

/******************************************************************************
Description.: Retrieve single image from shared memory
Input Value.: $timeout in seconds
Return Value: "false" in case of errors
              "true" if image obtained, but it did not change from previous call
              JPEG encoded image data if everything is alright
******************************************************************************/
function GetImageFromSharedMemory($timeout = 5) {
	global $shm, $shm_mutex_id, $reader_mutex_id;
	$shm_mutex = false;
	static $timestamp_previous = 0;

	//Enter critical section for reading from shared memory
	$then = time();
	while(time()-$then < $timeout) {
		$shm_mutex = sem_acquire($shm_mutex_id, true);
		if ($shm_mutex) {
			break;
		}
		usleep(100);
	}
	if (!$shm_mutex) {
		DebugMessage("Timeout: could not get mutex for shared memory");
		return false;
	}

	//get timestamp from shared memory
	if( !shm_has_var($shm, 1) ) {
		DebugMessage("shm_put_var for timestamp is not there");
		$timestamp = 0;
	} else {
		$timestamp = shm_get_var($shm, 1);
	}

	//get image from shared memory, unless it is not updated
	if( !shm_has_var($shm, 0) ) {
		DebugMessage("shm_put_var for image is not there");
		$img = false;
	} else {
		if( microtime(true) - $timestamp > $timeout) {
			//is the image old?
			$img = false;
		} elseif ( $timestamp_previous == $timestamp ) {
			//image did not change from previous time
			$img = true;
		} else {
			//yay, this is a new image
			$img = shm_get_var($shm, 0);

			//if image is emtpy writer is currently busy, try again later
			if(strlen($img) == 0) {
				$img = true;
			}
		}
		$timestamp_previous = $timestamp;
	}

	//Leaving critical section
	if( !sem_release($shm_mutex_id) ) {
		DebugMessage("sem_release for image failed");
	}

	return $img;
}

/******************************************************************************
Description.: determine if we are writer or reader
Input Value.: $timeout defines how old previous writer output must be,
              before we declare this instance to be a writer
Return Value: true for writer
              false for reader
******************************************************************************/
function RoleIsWriter($timeout = 5) {
	global $shm, $shm_mutex_id;

	//Enter critical section for accessing shared memory, blocking
	$shm_mutex = sem_acquire($shm_mutex_id, false);

	//get timestamp from shared memory
	if( !shm_has_var($shm, 1) ) {
		DebugMessage("shm_has_var for timestamp is not there");
		$timestamp = 0;
	} else {
		$timestamp = shm_get_var($shm, 1);
	}

	//access pid of writer
	if( !shm_has_var($shm, 2) ) {
		//there is no writer yet, so we start doing it, place our PID
		$writerPid = NULL;
	} else {
		$writerPid = shm_get_var($shm, 2);
	}

	$role = NULL;
	if( $writerPid == getmypid() ) {
		//we are already writer
		$role = true;
	} elseif (microtime(true) - $timestamp > $timeout) {
		DebugMessage("Timestamp is old, becoming writer: ". microtime(true) - $timestamp);
		//timestamp is old, we become a writer
		$role = true;
		shm_put_var($shm, 2, getmypid());
		//place an emtpy image, because that is all we can offer right now
		shm_put_var($shm, 0, "");
		//update timestamp to signal the new frame, which is empty
		shm_put_var($shm, 1, microtime(true));
	} else {
		//we will remain a reader
		$role = false;
	}

	//Leaving critical section
	if( !sem_release($shm_mutex_id) ) {
		DebugMessage("sem_release for image failed");
	}

	return $role;
}

/* 
 * writer is the one talking to the upstream camera
 * readers (normally) do not talk directly to the upstream camera
 */

//if we get the writer role we connect to the upstream camera
$role_writer = RoleIsWriter(5);

while(true) {
	if($role_writer) {
		DebugMessage("Role is Writer");
		//ignore_user_abort(true);

		while(($img = GetImageFromUpstreamCamera()) !== false) {
			if( $debug )
				$img = TextToImage("Writer: display image with size ".strlen($img), $img);

			WriteImageToSharedMemory($img);
			OutputImage($img);

			usleep(100*1000);
			if(connection_status() != CONNECTION_NORMAL)
				exit;
			set_time_limit(1);
		}

		$img = TextToImage("Writer: could not get image from upstream camera (".date("Y-m-d H:i:s").")");
		for($i=0; $i<10; $i++) {
			OutputImage($img);
			usleep(200*1000);
		}
	} else {
		DebugMessage("Role is Reader");

		while(true) {
			//try to get image from shared memory (with timeout)
			$img = GetImageFromSharedMemory(5);

			//try to become a writer, which suceeds if other writer exits
			$role_writer = RoleIsWriter(5);
			if( $role_writer ) {
				DebugMessage("Switching role to writer");
				break;
			}

			//true, we could read image, but it did not change or is empty
			if( $img === true ) {
				usleep(100*1000);
				continue;
			}

			//false, we are reader and have no image, timeout occured
			if ( $img === false ) {
				DebugMessage("Role is Reader, but reading image failed");
				usleep(100*1000);
				continue;
			}

			if( $debug )
				$img = TextToImage("Reader: display image with size ".strlen($img), $img);
			OutputImage($img);

			usleep(100*1000);
			set_time_limit(1);
		}
	}
}
