<?php

namespace PhpIpCam;

class ImageFromUpstreamCameraExtractor
{
    private const BOUNDARY_START_DEFINITION_STRING = 'Content-Type: multipart/x-mixed-replace;boundary=';

    private string $host;
    private int $port;
    private string $url;
    private ?string $auth;
    private ?string $fingerprint;
    private string $lastDebugMessage;

    public function __construct(string $host, int $port, string $url, ?string $auth, ?string $fingerprint)
    {
        $this->host = $host;
        $this->port = $port;
        $this->url = $url;
        $this->auth = $auth;
        $this->fingerprint = $fingerprint;
        $this->lastDebugMessage = "";
    }

    /**
     * @return false|string
     */
    public function getImageFromUpstreamCamera()
    {
        $this->debugMessage("----------------------------- getImageFromUpstreamCamera started -------------------");
        $whileLoopCounter = 0;
        $boundaryStart = 0;
        $boundaryEnd = 0;
        $boundaryIn = null;
        //filepointer for reading from upstream webcam
        $fp = false;

        //buffer to keep remainder of previous data chunks
        $buffer = '';

        //open filepointer to upstream camera if not already open
        if ($fp === false) {
            $this->debugMessage("opening fp");
            /*
             * if the camera cert is self-signed, maybe you need to ignore TLS certificate details
             * WARNING: MITM is possible when setting verify... to false
             *
             * Documentation is at: https://www.php.net/manual/en/context.ssl.php
             */
            if ($this->fingerprint) {
                $this->debugMessage("connect to camera with fingerprint: " . $this->fingerprint);
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
                $this->debugMessage("Input failed (FP: " . json_encode($fp) . ", $errstr)");
                return false;
            }

            $this->debugMessage("FP is ok");

            //request upstream camera data to send stream
            $out = "GET {$this->url} HTTP/1.1\r\n";
            $out .= "Host: {$this->host}\r\n";
            if ($this->auth) {
                $out .= "Authorization: Basic " . base64_encode($this->auth) . "\r\n";
            }
            $out .= "\r\n";
            $result = fwrite($fp, $out);
            if ($result === false) {
                $this->debugMessage("Could not fwrite to upstream camera");
                return false;
            }
            $this->debugMessage("Contacted upstream camera, send $result bytes.");
        }

        $this->debugMessage("Begin read data from upstream camera and return single picture.");

        // Read data from upstream camera, return single picture:
        while (!feof($fp)) {
            $whileLoopCounter++;
            $buffer .= fgets($fp);
            $part = $buffer;

            // learn boundary string
            if (!$boundaryIn) {
                $boundaryStart = strpos($buffer, self::BOUNDARY_START_DEFINITION_STRING);

                if (!$boundaryStart) {
                    $this->debugMessage("$whileLoopCounter) boundaryStart not found.");
                    continue;
                }

                $boundaryStart = $boundaryStart + strlen(self::BOUNDARY_START_DEFINITION_STRING);
                $boundaryEnd = strpos($buffer, "\r\n", $boundaryStart);

                if ($boundaryEnd === false) {
                    $this->debugMessage("$whileLoopCounter) boundaryEnd is false.");
                    continue;
                }

                if ($boundaryStart >= $boundaryEnd) {
                    $this->debugMessage("$whileLoopCounter) boundaryStart >= boundaryEnd.");
                    continue;
                }

                $boundaryIn = substr($buffer, $boundaryStart, $boundaryEnd - $boundaryStart);

                $this->debugMessage("$whileLoopCounter) found boundary $boundaryIn");
            }

            // Debug:
            $debugData = fopen('Data/data_' . $whileLoopCounter . '.txt', 'w+');
            fwrite($debugData, $buffer);
            fclose($debugData);
            $this->debugMessage("$whileLoopCounter) boundaryIn=$boundaryIn; boundaryStart=$boundaryStart; boundaryEnd=$boundaryEnd.");

            //extract single JPEG frame, alternatively we could also search EOI, SOI markers
            $part = substr($part, strpos($part, "--$boundaryIn") + strlen("--$boundaryIn"));
            $part = trim(substr($part, strpos($part, "\r\n\r\n")));
            $part = substr($part, 0, strpos($part, "--$boundaryIn"));

            //substr returns an empty string if the string could not be extracted
            //an image should not be smaller than this, so skip if too small
            if (strlen($part) <= 100) {
                continue;
            }

            //shorten/maintain the buffer
            $buffer = substr($buffer, strpos($buffer, $part) + strlen($part));

            //return a single image
            return $part;
        }

        return false;
    }

    /**
     * @param string $string
     *
     * @return void
     */
    private function debugMessage(string $string): void
    {
        $this->lastDebugMessage = $string;
        file_put_contents("php://stdout", "\n$string");
    }
}