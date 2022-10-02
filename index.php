<?php
require_once('PhpIpCam/Helper.php');
require_once('PhpIpCam/JpegSegment.php');
require_once('PhpIpCam/ImageFromUpstreamCameraExtractor.php');
require_once('PhpIpCam/TextToImageWriter.php');

use PhpIpCam\Helper;
use PhpIpCam\ImageFromUpstreamCameraExtractor;
use PhpIpCam\TextToImageWriter;

$startProgramDateTime = new DateTime();
$startProgramHighResolutionTime = hrtime(true);
$helper = new Helper();
//$imageFromUpstreamCameraExtractor = new ImageFromUpstreamCameraExtractor('tcp://192.168.42.60', 4747, '/video?1920x1080', null, null);
$imageFromUpstreamCameraExtractor = new ImageFromUpstreamCameraExtractor('tcp://192.168.1.250', 4747, '/video?1920x1080', null, null, $helper);
$textToImageWriter = new TextToImageWriter();

try {
    $fileImageName = date_format(new DateTime(), 'Y-m-d_H_i_s') . '_data.jpg';
    if (!is_dir('Data')) {
        mkdir('Data', 0777, true);
    }
    $fileImageFullName = 'Data/' . $fileImageName;

    $startImageFromUpstreamCameraReadTime = hrtime(true);
    $img = $imageFromUpstreamCameraExtractor->getImageFromUpstreamCamera();
    $helper->debugMessage('Image read from upstream camera time in milliseconds: ' . $helper->getHighResolutionTimeEtaInMilliseconds($startImageFromUpstreamCameraReadTime, hrtime(true)));

    //$img = $textToImageWriter->textToImage("Some text.", $img);

    $file = fopen($fileImageFullName, 'w+');
    fwrite($file, $img);
    fclose($file);

    $helper->debugMessage("Memory usage: " . round(memory_get_usage(true) / 1048576, 2) . ' Mb. done index.php. Total time: ' . $startProgramDateTime->diff(new \DateTime())->format('%H h. %i min. %s sec.') . '. Program execution time in milliseconds: ' . $helper->getHighResolutionTimeEtaInMilliseconds($startProgramHighResolutionTime, hrtime(true)));
    $helper->debugMessage("Done");
} catch (Exception $ex) {
    echo $ex->getMessage();
}
/*
Sometimes you need to calculate or track the memory used by your running script.
The memory_get_usage() function has a boolean parameter. The default is false. With the
default value (false), you can get the number of bytes used by your script. With the
parameter set to true you get the bytes pre-allocated for memory pages.
$mem = memory_get_usage();
$mem = memory_get_usage(true);
 */