<?php

namespace Xcs\Helper;

class Imagickd {

    public static function createFromFile($tempname) {
        //$fileext = trim(strtolower($ext), '.');
        try {
            $handle = new \Imagick($tempname);
        } catch (\ImagickException $ex) {
            return false;
        }
        return new HandleImagek($handle);
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