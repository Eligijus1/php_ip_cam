<?php

namespace PhpIpCam;

use DateTime;

class ImageFromUpstreamCameraExtractor
{
    private const BOUNDARY_START_DEFINITION_STRING = 'Content-Type: multipart/x-mixed-replace;boundary=';
    private const SOI = "\xFF\xD8"; // Start of image
    private const EOI = "\xFF\xD9"; // End of image
    private const END_OF_FILE = "\xD9";

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
        $startOfImagePosition = 0;
        $endOfFilePosition = 0;
        $endOfImagePosition = 0;

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

        //return $this->fromStream($fp);

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
            /*
            $debugData = fopen('Data/data_' . $whileLoopCounter . '.txt', 'w+');
            fwrite($debugData, $buffer);
            fclose($debugData);
            */

            /*
            // Read the first two characters
            $data = fread($fileHandle, 2);

            // Check that the first two characters are 0xFF 0xDA (SOI - Start of image)
            if ($data !== self::SOI) {
                throw new \Exception('Could not find SOI, invalid JPEG file.');
            }
            */

            $startOfImagePosition = strpos($buffer, self::SOI);
            $endOfFilePosition = strpos($buffer, self::END_OF_FILE);
            $endOfImagePosition = strpos($buffer, self::EOI);

            if ($startOfImagePosition > 0 && $endOfImagePosition > 0) {
                $this->debugMessage("Detected start of image file position $startOfImagePosition and end of image position $endOfImagePosition.");
                return substr($buffer, $startOfImagePosition, $endOfImagePosition - $startOfImagePosition);
                //substr($compressedData, 0, $eoiPos);
            }

//            // Note our use of ===.  Simply == would not work as expected
//            // because the position of 'a' was the 0th (first) character.
//            if ($pos === false) {
//                //echo "The string '$findme' was not found in the string '$mystring'";
//            } else {
//                //echo "The string '$findme' was found in the string '$mystring'";
//                //echo " and exists at position $pos";
//                $this->debugMessage("$whileLoopCounter) found " . self::SOI . " position: $pos.");
//            }

            $this->debugMessage("$whileLoopCounter) boundaryIn=$boundaryIn; boundaryStart=$boundaryStart; boundaryEnd=$boundaryEnd; startOfImagePosition=$startOfImagePosition; endOfFilePosition=$endOfFilePosition; endOfImagePosition=$endOfImagePosition.");

            // Extract single JPEG frame, alternatively we could also search EOI, SOI markers:
            $part = substr($part, strpos($part, "--$boundaryIn") + strlen("--$boundaryIn"));
            $part = trim(substr($part, strpos($part, "\r\n\r\n")));
            $part = substr($part, 0, strpos($part, "--$boundaryIn"));

            // To avoid infinite loop, interrupt:
            if ($whileLoopCounter > 2000) {
                $this->debugMessage("$whileLoopCounter) Infinite loop protection invoked.");
                return false;
            }

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
     * Load a JPEG from a stream.
     *
     * @param resource $fileHandle
     * @param string $filename
     *
     * @return mixed
     * @throws \Exception
     */
    public function fromStream($fileHandle, $filename = null)
    {
        try {
            // Read the first two characters
            $data = fread($fileHandle, 2);

            // Check that the first two characters are 0xFF 0xDA (SOI - Start of image)
            if ($data !== self::SOI) {
                throw new \Exception('Could not find SOI, invalid JPEG file.');
            }

            // Read the next two characters
            $data = fread($fileHandle, 2);

            // Check that the third character is 0xFF (Start of first segment header)
            if ($data[0] != "\xFF") {
                throw new \Exception('No start of segment header character, JPEG probably corrupted.');
            }

            $segments = [];
            $imageData = null;

            // Cycle through the file until, either an EOI (End of image) marker is hit or end of file is hit
            while (($data[1] != "\xD9") && (!feof($fileHandle))) {
                // Found a segment to look at.
                // Check that the segment marker is not a restart marker, restart markers don't have size or data
                if ((ord($data[1]) < 0xD0) || (ord($data[1]) > 0xD7)) {
                    $decodedSize = unpack('nsize', fread($fileHandle, 2)); // find segment size

                    $segmentStart = ftell($fileHandle); // segment start position
                    $segmentData = fread($fileHandle, $decodedSize['size'] - 2); // read segment data
                    $segmentType = ord($data[1]);

                    $segments[] = new JpegSegment($segmentType, $segmentStart, $segmentData);
                }

                // If this is a SOS (Start Of Scan) segment, then there is no more header data, the image data follows
                if ($data[1] == "\xDA") {
                    // read the rest of the file, reading 1mb at a time until EOF
                    $compressedData = '';
                    do {
                        $compressedData .= fread($fileHandle, 1048576);
                    } while (!feof($fileHandle));

                    // Strip off EOI and anything after
                    $eoiPos = strpos($compressedData, "\xFF\xD9");
                    $imageData = substr($compressedData, 0, $eoiPos);

                    break; // exit loop as no more headers available.
                } else {
                    // Not an SOS - Read the next two bytes - should be the segment marker for the next segment
                    $data = fread($fileHandle, 2);

                    // Check that the first byte of the two is 0xFF as it should be for a marker
                    if ($data[0] != "\xFF") {
                        throw new \Exception('No FF found, JPEG probably corrupted.');
                    }
                }
            }

            //return new self($imageData, $segments, $filename);
            return $imageData;

        } finally {
            fclose($fileHandle);
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
        file_put_contents("php://stdout", "\n" . date_format(new DateTime(), 'Y-m-d H:i:s') . " $string");
    }
}
// https://gist.github.com/megasaturnv/81279fca49f2f34b42e77815c9bb1eb8