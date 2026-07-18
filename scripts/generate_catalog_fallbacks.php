<?php
$directory = dirname(__DIR__).'/public/assets/images/catalog';
if (!is_dir($directory)) mkdir($directory, 0777, true);
foreach (['item' => [52, 152, 219], 'monster' => [220, 53, 69]] as $type => $rgb) {
    foreach ([64, 128, 256] as $size) {
        $image = imagecreatetruecolor($size, $size);
        imagealphablending($image, false); imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        $background = imagecolorallocatealpha($image, $rgb[0], $rgb[1], $rgb[2], 25);
        $foreground = imagecolorallocatealpha($image, $rgb[0], $rgb[1], $rgb[2], 0);
        imagefilledellipse($image, $size / 2, $size / 2, $size * .82, $size * .82, $background);
        if ($type === 'item') {
            $margin = (int) ($size * .28);
            imagefilledrectangle($image, $margin, $margin, $size - $margin, $size - $margin, $foreground);
        } else {
            imagefilledellipse($image, $size / 2, $size * .46, $size * .38, $size * .38, $foreground);
            imagefilledellipse($image, $size / 2, $size * .76, $size * .55, $size * .24, $foreground);
        }
        imagewebp($image, $directory.'/default-'.$type.'-'.$size.'.webp', 82);
        imagedestroy($image);
    }
}
