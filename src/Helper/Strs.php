<?php

namespace Xcs\Helper;

class Strs {

    /**
     * 随机字符
     * @param int $length
     * @return string
     */
    public static function random($length = 4) {
        $reqid = '';
        $characters = array("A", "B", "C", "D", "E", "F", "G", "H", "J", "K", "L", "M", "N", "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y", "Z", "2", "3", "4", "5", "6", "7", "8", "9");
        shuffle($characters);
        for (; strlen($reqid) < $length;) {
            $reqid .= $characters[rand(0, count($characters) - 1)];
        }
        return $reqid;
    }

    /**
     * qutotes get post cookie by \char(21)'
     * @param $string
     * @return array|string
     */
    public static function daddcslashes($string) {
        if (empty($string)) {
            return $string;
        }
        if (is_numeric($string)) {
            return $string;
        }
        if (is_array($string)) {
            return array_map('daddcslashes', $string);
        }
        return addcslashes($string, '');
    }

    /**
     * it's paire to daddcslashes
     * @param $value
     * @return array|string
     */
    public static function dstripcslashes($value) {
        if (empty($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return $value;
        }
        if (is_array($value)) {
            return array_map('dstripcslashes', $value);
        }
        return stripcslashes($value);
    }


    /**
     * cut string to set length
     * return string
     * @param $string
     * @param $length
     * @param bool $suffix
     * @param string $charset
     * @param int $start
     * @param string $dot
     * @return mixed|string
     */
    public static function cutstr($string, $length, $suffix = true, $charset = "utf-8", $start = 0, $dot = ' ...') {
        $str = str_replace(array('&amp;', '&quot;', '&lt;', '&gt;'), array('&', '"', '<', '>'), $string);
        if (function_exists("mb_substr")) {
            $strcut = mb_substr($str, $start, $length, $charset);
            if (mb_strlen($str, $charset) > $length) {
                return $suffix ? $strcut . $dot : $strcut;
            }
            return $strcut;
        }
        $re = array();
        $match = array('');
        $re['utf-8'] = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
        $re['gb2312'] = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
        $re['gbk'] = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
        $re['big5'] = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
        preg_match_all($re[$charset], $str, $match);
        $slice = join("", array_slice($match[0], $start, $length));
        $strcut = str_replace(array('&', '"', '<', '>'), array('&amp;', '&quot;', '&lt;', '&gt;'), $slice);
        return $suffix ? $strcut . $dot : $strcut;
    }

    /**
     * @param $string
     * @param $length
     * @param int $out_slashes
     * @param int $html
     * @return mixed|string
     */
    public static function getstr($string, $length, $out_slashes = 0, $html = 0) {
        $string = stripslashes($string);
        if ($html < 0) {
            $string = preg_replace("/(\<[^\<]*\>|\r|\n|\s|\[.+?\])/is", ' ', $string);
        } elseif ($html == 0) {
            $string = htmlspecialchars($string, ENT_QUOTES);
        }
        if ($length) {
            $string = self::cutstr($string, $length, '');
        }
        if ($out_slashes) {
            $string = addslashes($string);
        }
        return $string;
    }

    /**
     * @param $in
     * @param $out
     * @param $string
     * @return mixed|string
     */
    public static function convert_encode($in, $out, $string) { // string change charset return string
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($string, $out, $in);
            //return mb_convert_encoding($string, $out, $in);
        } elseif (function_exists('iconv')) {
            return iconv($in, $out . '//IGNORE', $string);
        } else {
            return $string;
        }
    }

    /**
     * @param $in
     * @param $out
     * @param $string
     * @return array|mixed|string
     */
    public static function convert_char($in, $out, $string) {
        // string change charset return mix
        if (is_array($string)) {
            $ret = array();
            foreach ($string as $str) {
                $ret[] = self::convert_char($in, $out, $str);
            }
            return $ret;
        }
        return self::convert_encode($in, $out, $string);
    }

}
