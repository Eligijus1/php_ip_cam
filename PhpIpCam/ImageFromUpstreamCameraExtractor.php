<?php

namespace PhpIpCam;

class ImageFromUpstreamCameraExtractor
{
    private const BOUNDARY_START_DEFINITION_STRING = 'Content-Type: multipart/x-mixed-replace;boundary=';
    private const SOI = "\xFF\xD8"; // Start of image
    private const EOI = "\xFF\xD9"; // End of image
    private const END_OF_FILE = "\xD9";
    private const INFINITE_LOOP_INTERRUPT = 2000;

    private string $host;
    private int $port;
    private string $url;
    private ?string $auth;
    private ?string $fingerprint;
    private Helper $helper;

    public function __construct(string $host, int $port, string $url, ?string $auth, ?string $fingerprint, Helper $helper)
    {
        $this->host = $host;
        $this->port = $port;
        $this->url = $url;
        $this->auth = $auth;
        $this->fingerprint = $fingerprint;
        $this->helper = $helper;
    }

    /**
     * @return false|string
     */
    public function getImageFromUpstreamCamera()
    {
        $this->helper->debugMessage("----------------------------- getImageFromUpstreamCamera started -------------------");
        $whileLoopCounter = 0;
        $boundaryStart = 0;
        $boundaryEnd = 0;
        $boundaryIn = null;
        //file pointer for reading from upstream webcam
        $fp = false;
        $startOfImagePosition = 0;
        $endOfFilePosition = 0;
        $endOfImagePosition = 0;

        //buffer to keep remainder of previous data chunks
        $buffer = '';

        //open filepointer to upstream camera if not already open
        if ($fp === false) {
            $this->helper->debugMessage("opening fp");
            /*
             * if the camera cert is self-signed, maybe you need to ignore TLS certificate details
             * WARNING: MITM is possible when setting verify... to false
             *
             * Documentation is at: https://www.php.net/manual/en/context.ssl.php
             */
            if ($this->fingerprint) {
                $this->helper->debugMessage("connect to camera with fingerprint: " . $this->fingerprint);
                $context = stream_context_create([
                    'ssl' => [
                        'verify_peer' => false,
                        //'allow_self_signed' => true,
                        //'peer_fingerprint' =>  $this->fingerprint , //could not make it work so far
                        'verify_peer_name' => false
                    ]
                ]);
                //establish connection to upstream camera with dodgy cert
                $fp = stream_socket_client("{$this->host}:{$this->port}", $errno, $errstr, ini_get("default_socket_timeout"), STREAM_CLIENT_CONNECT, $context);
            } else {
                //establish regular connection to upstream camera
                $fp = @fsockopen($this->host, $this->port, $errno, $errstr, ini_get("default_socket_timeout"));
            }

            if ($fp === false) {
                $this->helper->debugMessage("Input failed (FP: " . json_encode($fp) . ", $errstr)");
                return false;
            }

            $this->helper->debugMessage("FP is ok");

            // Request upstream camera data to send stream:
            $out = "GET {$this->url} HTTP/1.1\r\n";
            $out .= "Host: {$this->host}\r\n";
            if ($this->auth) {
                $out .= "Authorization: Basic " . base64_encode($this->auth) . "\r\n";
            }
            $out .= "\r\n";
            $result = fwrite($fp, $out);
            if ($result === false) {
                $this->helper->debugMessage("Could not fwrite to upstream camera");
                return false;
            }
            $this->helper->debugMessage("Contacted upstream camera, send $result bytes.");
        }

        $this->helper->debugMessage("Begin read data from upstream camera and return single picture.");

        // Read data from upstream camera, to extract and return single picture:
        while (!feof($fp)) {
            $whileLoopCounter++;
            $buffer .= fgets($fp);

            $startOfImagePosition = strpos($buffer, self::SOI);
            $endOfImagePosition = strpos($buffer, self::EOI);

            if ($startOfImagePosition > 0 && $endOfImagePosition > 0 && $endOfImagePosition > $startOfImagePosition) {
                $this->helper->debugMessage("$whileLoopCounter) Detected start of image file position $startOfImagePosition and end of image position $endOfImagePosition.");
                return substr($buffer, $startOfImagePosition, $endOfImagePosition - $startOfImagePosition + strlen(self::EOI));
            }

            // To avoid infinite loop, interrupt:
            if ($whileLoopCounter > self::INFINITE_LOOP_INTERRUPT) {
                $this->helper->debugMessage("$whileLoopCounter) Infinite loop protection invoked.");
                return false;
            }
        }

        return false;
    }
}
