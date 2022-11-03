<?php
require_once('PhpIpCam/Helper.php');
require_once('PhpIpCam/JpegImageMaker.php');
require_once('PhpIpCam/SharedMemoryManager.php');

use PhpIpCam\Helper;
use PhpIpCam\JpegImageMaker;
use PhpIpCam\SharedMemoryManager;

$helper = new Helper();
$jpegImageMaker = new JpegImageMaker();
$sharedMemoryManager = new SharedMemoryManager();
$i = 0;

$helper->message("DEBUG", "JPEG generator stared.");

while (true) {
    $i++;

    $sharedMemoryManager->putDataToSharedMemory($jpegImageMaker->getImageWithSimpleText(date_format(new DateTime(), 'Y-m-d H:i:s') . ' A Simple Test 8 Text String ' . $i));

    if ($i > 100) {
        echo PHP_EOL;
        break;
    }
}

$helper->message("DEBUG", "$i JPEG generator iterations finished.");