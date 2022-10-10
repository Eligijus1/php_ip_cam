<?php
$boundaryOut = "MyMultipartBoundaryDoNotStumble";
$seconds = 5;

$i = 0;
$im = imagecreatetruecolor(120, 20);
$text_color = imagecolorallocate($im, 233, 14, 91);
imagestring($im, 1, 5, 5, 'A Simple Text String ' . $i, $text_color);
imagejpeg($im);
imagedestroy($im);

//for ($i = 0; $i < $seconds * 5; $i++) {
//
//    $imageData = TextToImage('A Simple Text String ' . $i);
//    //OutputImage($imageData);
//    //imagedestroy($im);
//    usleep(200 * 1000);
//}

/******************************************************************************
 * Description.: Output image to stream
 * Input Value.: $img is JPEG encoded image
 * Return Value: -
 ******************************************************************************/
function OutputImage($img)
{
    global $boundaryOut;

    echo "Content-Type: image/jpeg\r\n" .
        "Content-Length: " . strlen($img) . "\r\n" .
        "X-Timestamp: " . number_format(microtime(true), 6, '.', '') . "\r\n" .
        "\r\n" .
        $img .
        "\r\n--$boundaryOut\r\n";

    flush();
    ob_flush();
    while (@ob_end_flush()) {
        ;
    }
}

/******************************************************************************
 * Description.: Convert string to Image
 * Input Value.: $str is the message to write, linebreaks are not supported
 * $background is JPEG encoded background image
 * Return Value: JPEG encoded image data
 ******************************************************************************/
function TextToImage($str, $background = null)
{
    if (!is_null($background)) {
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
    imagejpeg($img, null, -1);
    $imgstr = ob_get_contents();
    ob_end_clean();

    return $imgstr;
}