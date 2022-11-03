<?php
require_once('PhpIpCam/Helper.php');

use PhpIpCam\Helper;

$helper = new Helper();

$helper->message("DEBUG", "write_mjpeg_00001.php begin.");

# The loop, producing one jpeg frame per iteration
$i = 0;
while (true) {
    $i++;

    # Make image:
    ob_start();
    $im = imagecreatetruecolor(1920, 1080);
    $text_color = imagecolorallocate($im, 233, 14, 91);
    imagestring($im, 0, 5, 5, date_format(new DateTime(), 'Y-m-d H:i:s') . ' A Simple Test 4 Text String ' . $i, $text_color);
    imagejpeg($im);
    imagedestroy($im);
    $imageData = ob_get_clean();

    putDataToMemory($imageData);

    $helper->message("DEBUG", "Iteration $i end.");

    if ($i > 100) {
        echo PHP_EOL;
        exit(0);
    }
    sleep(1);
}

/**
 * @param $data
 *
 * @return void
 * @throws Exception
 */
function putDataToMemory($data): void
{
    $key = ftok(realpath(dirname(__FILE__) . '/..') . '/index.php', 'a');

    // Allocate or attach to 1 MByte of shared memory:
    $shm = shm_attach($key, 1024 * 1024, 0600);
    if ($shm === false) {
        throw new Exception('Failed to attach shared memory.');
    }

    // Add data to memory:
    if (!shm_put_var($shm, 0, $data)) {
        throw new Exception('shm_put_var failed.');
    }
}
