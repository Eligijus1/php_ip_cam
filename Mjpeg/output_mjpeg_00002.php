<?php
require_once('../PhpIpCam/Helper.php');

use PhpIpCam\Helper;

$helper = new Helper();

# Used to separate multipart
$boundary = "my_mjpeg";

# We start with the standard headers. PHP allows us this much
header("Cache-Control: no-cache");
header("Cache-Control: private");
header("Pragma: no-cache");
header("Content-type: multipart/x-mixed-replace; boundary=$boundary");

# From here out, we no longer expect to be able to use the header() function
print "--$boundary\n";

# Set this so PHP doesn't time out during a long stream
set_time_limit(0);

# Disable Apache and PHP's compression of output to the client
//@apache_setenv('no-gzip', 1);
@ini_set('zlib.output_compression', 0);

# Set implicit flush, and flush all current buffers
@ini_set('implicit_flush', 1);
for ($i = 0; $i < ob_get_level(); $i++) {
    ob_end_flush();
}
ob_implicit_flush(1);

# The loop, producing one jpeg frame per iteration
$i = 0;
while (true) {
    $i++;
    # Per-image header, note the two new-lines
    print "Content-type: image/jpeg\n\n";

    # Make image:
    $im = imagecreatetruecolor(1920, 1080);
    $text_color = imagecolorallocate($im, 233, 14, 91);
    imagestring($im, 0, 5, 5, date_format(new DateTime(), 'Y-m-d H:i:s') . ' A Simple Text String ' . $i, $text_color);
    imagejpeg($im);
    imagedestroy($im);

    # The separator
    print "--$boundary\n";

    // 1-second sleep:
    //usleep(2 * 1000);
    sleep(1);
}