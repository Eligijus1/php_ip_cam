<?php
require_once('PhpIpCam/JpegSegment.php');
require_once('PhpIpCam/ImageFromUpstreamCameraExtractor.php');
require_once('PhpIpCam/TextToImageWriter.php');

use PhpIpCam\ImageFromUpstreamCameraExtractor;
use PhpIpCam\TextToImageWriter;

//$imageFromUpstreamCameraExtractor = new ImageFromUpstreamCameraExtractor('tcp://192.168.42.60', 4747, '/video?1920x1080', null, null);
$imageFromUpstreamCameraExtractor = new ImageFromUpstreamCameraExtractor('tcp://192.168.1.250', 4747, '/video?1920x1080', null, null);
$textToImageWriter = new TextToImageWriter();

try {
    $fileImageName = date_format(new DateTime(), 'Y-m-d_H_i_s') . '_data.jpg';
    if (!is_dir('Data')) {
        mkdir('Data', 0777, true);
    }
    $fileImageFullName = 'Data/' . $fileImageName;
    $img = $imageFromUpstreamCameraExtractor->getImageFromUpstreamCamera();

    $img = $textToImageWriter->textToImage("Some text.", $img);

    $file = fopen($fileImageFullName, 'w+');
    fwrite($file, $img);
    fclose($file);
} catch (Exception $ex) {
    echo $ex->getMessage();
}
