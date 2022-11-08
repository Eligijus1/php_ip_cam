<?php
require_once('PhpIpCam/Helper.php');
require_once('PhpIpCam/JpegImageMaker.php');
require_once('PhpIpCam/SharedMemoryManager.php');

use PhpIpCam\Helper;
use PhpIpCam\JpegImageMaker;
use PhpIpCam\SharedMemoryManager;

$startProgramHighResolutionTime = hrtime(true);
$helper = new Helper();
$jpegImageMaker = new JpegImageMaker();
$sharedMemoryManager = new SharedMemoryManager();
$i = 0;

$helper->message("DEBUG", "JPEG generator stared.");

while (true) {
    $i++;

    try {
        $sharedMemoryManager->putDataToSharedMemory($jpegImageMaker->getImageWithSimpleText(date_format(new DateTime(), 'Y-m-d H:i:s') . ' A Simple Test 8 Text String ' . $i));
    } catch (Exception $ex) {
        $helper->message("ERROR", $ex->getMessage());
    }

    if ($i > 100) {
        echo PHP_EOL;
        break;
    }
}

$executionTime = $helper->getHighResolutionTimeEtaInMilliseconds($startProgramHighResolutionTime, hrtime(true));
$helper->message("DEBUG", "$i JPEG generator iterations finished. Program execution time in milliseconds: $executionTime.");