<?php

namespace Xcs\Helper;

/**
 * $ext = pathinfo($_FILES['postfile']['name'], PATHINFO_EXTENSION);
 * $image = imagegd::createFromFile($_FILES['postfile']['tmp_name'], $ext);
 */
class ImageGd
{

    /**
     * @param string $filename
     * @param string $ext
     * @return bool|HandleGd
     */
    public static function createFromFile(string $filename, string $ext = '')
    {
        if (!$ext) {
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
        }
        $fileExt = trim(strtolower($ext), '.');
        $ext2functions = [
            'jpg' => 'imagecreatefromjpeg',
            'jpeg' => 'imagecreatefromjpeg',
            'png' => 'imagecreatefrompng',
            'gif' => 'imagecreatefromgif',
            'bmp' => 'imagecreatefrombmp'
        ];
        if (!isset($ext2functions[$fileExt])) {
            return false;
        }
        $handle = call_user_func($ext2functions[$fileExt], $filename);
        return new HandleGd($handle);
    }

    public static function hex2rgb(string $color, string $default = 'ffffff'): array
    {
        $hex = trim($color, '#&Hh');
        $len = strlen($hex);
        if (3 == $len) {
            $hex = "{$hex[0]}{$hex[0]}{$hex[1]}{$hex[1]}{$hex[2]}{$hex[2]}";
        } elseif ($len < 6) {
            $hex = $default;
        }
        $dec = hexdec($hex);
        return [($dec >> 16) & 0xff, ($dec >> 8) & 0xff, $dec & 0xff];
    }

}