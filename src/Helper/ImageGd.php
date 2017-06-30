<?php

namespace Xcs\Helper;

/*
 * $ext = pathinfo($_FILES['postfile']['name'], PATHINFO_EXTENSION);
 * $image = imagegd::createFromFile($_FILES['postfile']['tmp_name'], $ext);
 */

class ImageGd {

    /**
     * @param $filename
     * @param string $ext
     * @return bool|HandleGd
     */
    public static function createFromFile($filename, $ext = '') {
        if (!$ext) {
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
        }
        $fileext = trim(strtolower($ext), '.');
        $ext2functions = array(
            'jpg' => 'imagecreatefromjpeg',
            'jpeg' => 'imagecreatefromjpeg',
            'png' => 'imagecreatefrompng',
            'gif' => 'imagecreatefromgif',
            'bmp' => 'imagecreatefrombmp'
        );
        if (!isset($ext2functions[$fileext])) {
            return false;
        }
        $handle = call_user_func($ext2functions[$fileext], $filename);
        return new HandleGd($handle);
    }

    public static function hex2rgb($color, $default = 'ffffff') {
        $hex = trim($color, '#&Hh');
        $len = strlen($hex);
        if (3 == $len) {
            $hex = "{$hex[0]}{$hex[0]}{$hex[1]}{$hex[1]}{$hex[2]}{$hex[2]}";
        } elseif ($len < 6) {
            $hex = $default;
        }
        $dec = hexdec($hex);
        return array(($dec >> 16) & 0xff, ($dec >> 8) & 0xff, $dec & 0xff);
    }

}