<?php

namespace PhpIpCam;

class JpegImageMaker
{
    public function getImageWithSimpleText(string $text): string
    {
        ob_start();
        $im = imagecreatetruecolor(1920, 1080);
        $text_color = imagecolorallocate($im, 233, 14, 91);
        imagestring($im, 0, 5, 5, $text, $text_color);
        imagejpeg($im);
        imagedestroy($im);
        return ob_get_clean();
    }
}