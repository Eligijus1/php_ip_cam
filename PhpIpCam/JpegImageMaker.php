<?php

namespace PhpIpCam;

class JpegImageMaker
{
    private string $fontPath;

    public function __construct(string $fontPath)
    {
        $this->fontPath = $fontPath;
        putenv('GDFONTPATH=' . $this->fontPath);// Set the environment variable for GD
    }

    public function getImageWithSimpleText(string $text): string
    {
        ob_start();
        $im = imagecreatetruecolor(1920, 1080);
        $textColor = imagecolorallocate($im, 233, 14, 91);
        //imagestring($im, 0, 5, 5, $text, $textColor);

        $font = $this->fontPath . DIRECTORY_SEPARATOR . 'arial.ttf';

        imagettftext($im, 20, 0, 11, 21, $textColor, $font, $text);
        imagejpeg($im);
        imagedestroy($im);
        return ob_get_clean();
    }
}