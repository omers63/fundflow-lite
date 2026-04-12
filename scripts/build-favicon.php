<?php

/**
 * Generates public/favicon.png and public/favicon.ico (PNG payload, Vista+ compatible).
 * Run: php scripts/build-favicon.php
 */

$public = dirname(__DIR__) . '/public';
$size = 32;
$im = imagecreatetruecolor($size, $size);
imagesavealpha($im, true);
$t = imagecolorallocatealpha($im, 0, 0, 0, 127);
imagefill($im, 0, 0, $t);
$blue = imagecolorallocate($im, 37, 99, 235);
imagefilledrectangle($im, 4, 4, 27, 27, $blue);
$w = imagecolorallocate($im, 255, 255, 255);
imagefilledrectangle($im, 10, 10, 13, 24, $w);
imagefilledrectangle($im, 10, 10, 22, 13, $w);
imagefilledrectangle($im, 10, 16, 20, 19, $w);
imagesetthickness($im, 2);
imagearc($im, 24, 16, 10, 12, 200, 340, $w);

imagepng($im, $public . '/favicon.png');
ob_start();
imagepng($im);
$png = ob_get_clean();
imagedestroy($im);

$pngLen = strlen($png);
$offset = 22;
$header = pack('vvv', 0, 1, 1);
$entry = pack('CCCCvvVV', $size, $size, 0, 0, 1, 32, $pngLen, $offset);
file_put_contents($public . '/favicon.ico', $header . $entry . $png);

echo "Wrote {$public}/favicon.png and favicon.ico\n";
