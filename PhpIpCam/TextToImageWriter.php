<?php

namespace PhpIpCam;

class TextToImageWriter
{
    public function textToImage(string $str, $background = NULL)
    {
        if ($background) {
            $img = imagecreatefromstring($background);
            $bgc = imagecolorallocate($img, 255, 255, 255);
            imagefilledrectangle($img, 10, 10, 640 - 10, 50, $bgc);
        } else {
            $img = imagecreatetruecolor(640, 480);
            $bgc = imagecolorallocate($img, 255, 255, 255);
            imagefilledrectangle($img, 10, 10, 640 - 10, 480 - 10, $bgc);
        }
        $tc = imagecolorallocate($img, 0, 0, 0);
        imagestring($img, 1, 20, 20, $str, $tc);

        ob_start();
        imagejpeg($img, NULL, -1);
        $newImage = ob_get_contents();
        ob_end_clean();

        return $newImage;
    }
}