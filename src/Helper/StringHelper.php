<?php

namespace Xcs\Helper;

use mysql_xdevapi\TableInsert;

class StringHelper
{

    /**
     * 随机字符
     * @param int $length
     * @return string
     */
    public static function random(int $length = 4): string
    {
        $reqId = '';
        $characters = ["A", "B", "C", "D", "E", "F", "G", "H", "J", "K", "L", "M", "N", "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y", "Z", "2", "3", "4", "5", "6", "7", "8", "9"];
        shuffle($characters);
        for (; strlen($reqId) < $length;) {
            $reqId .= $characters[rand(0, count($characters) - 1)];
        }
        return $reqId;
    }

    /**
     * @param int $length
     * @return string
     */
    public static function randomNum(int $length = 6): string
    {
        $reqId = '';
        $characters = ["1", "2", "3", "4", "5", "6", "7", "8", "9"];
        shuffle($characters);
        for (; strlen($reqId) < $length;) {
            $reqId .= $characters[rand(0, count($characters) - 1)];
        }
        return $reqId;
    }

    /**
     * @param int $length
     * @return string
     */
    public static function randomStr(int $length = 4): string
    {
        $reqId = '';
        $characters = ["A", "B", "C", "D", "E", "F", "G", "H", "J", "K", "L", "M", "N", "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y", "Z"];
        shuffle($characters);
        for (; strlen($reqId) < $length;) {
            $reqId .= $characters[rand(0, count($characters) - 1)];
        }
        return $reqId;
    }

    /**
     * cut string to set length
     * return string
     * @param string $string
     * @param int $length
     * @param bool $suffix
     * @param string $charset
     * @param int $start
     * @param string $dot
     * @return string
     */
    public static function cutStr(string $string, int $length, bool $suffix = true, string $charset = "utf-8", int $start = 0, string $dot = ' ...'): string
    {
        $str = str_replace(['&amp;', '&quot;', '&lt;', '&gt;'], ['&', '"', '<', '>'], $string);
        if (function_exists("mb_substr")) {
            $strCut = mb_substr($str, $start, $length, $charset);
            if (mb_strlen($str, $charset) > $length) {
                return $suffix ? $strCut . $dot : $strCut;
            }
            return $strCut;
        }
        $re = [];
        $match = [''];
        $re['utf-8'] = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
        $re['gb2312'] = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
        $re['gbk'] = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
        $re['big5'] = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
        preg_match_all($re[$charset], $str, $match);
        $slice = join("", array_slice((array)$match[0], $start, $length));
        $strCut = str_replace(['&', '"', '<', '>'], ['&amp;', '&quot;', '&lt;', '&gt;'], $slice);
        return $suffix ? $strCut . $dot : $strCut;
    }

    /**
     * @param string $string
     * @param int $length
     * @param int $out_slashes
     * @param int $html
     * @return array|string|string[]|null
     */
    public static function getStr(string $string, int $length, int $out_slashes = 0, int $html = 0)
    {
        $string = stripslashes($string);
        if ($html < 0) {
            $string = preg_replace("/(\<[^\<]*\>|\r|\n|\s|\[.+?\])/is", ' ', $string);
        } elseif ($html == 0) {
            $string = htmlspecialchars($string, ENT_QUOTES);
        }
        if ($length) {
            $string = self::cutStr($string, $length, '');
        }
        if ($out_slashes) {
            $string = addslashes($string);
        }
        return $string;
    }

    /**
     * @param string $in
     * @param string $out
     * @param string $string
     * @return array|false|string
     */
    public static function convert_encode(string $in, string $out, string $string)
    { // string change charset return string
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
     * @param string $in
     * @param string $out
     * @param mixed $string
     * @return array|false|string
     */
    public static function convert_char(string $in, string $out, $string)
    {
        // string change charset return mix
        if (is_array($string)) {
            $ret = [];
            foreach ($string as $str) {
                $ret[] = self::convert_char($in, $out, $str);
            }
            return $ret;
        }
        return self::convert_encode($in, $out, $string);
    }

}
