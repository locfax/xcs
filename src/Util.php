<?php

namespace Xcs;

//辅助功能函数
class Util {

    public static function dimplode($arr) {
        return "'" . implode("','", (array)$arr) . "'";
    }

    public static function strexists($str, $needle) {
        return !(false === strpos($str, $needle));
    }

    public static function array_index($arr, $col) {
        if (!is_array($arr)) {
            return $arr;
        }
        $rows = array();
        foreach ($arr as $row) {
            $rows[$row[$col]] = $row;
        }
        return $rows;
    }

    /**
     * 数组 转 对象
     *
     * @param array $arr 数组
     * @return object|mixed
     */
    public static function array_to_object($arr) {
        if (gettype($arr) != 'array') {
            return $arr;
        }
        foreach ($arr as $k => $v) {
            if (gettype($v) == 'array' || getType($v) == 'object') {
                $arr[$k] = self::array_to_object($v);
            }
        }
        return (object)$arr;
    }

    /**
     * 对象 转 数组
     *
     * @param object $obj 对象
     * @return array
     */
    public static function object_to_array($obj) {
        $obj = (array)$obj;
        foreach ($obj as $k => $v) {
            if (gettype($v) == 'object' || gettype($v) == 'array') {
                $obj[$k] = self::object_to_array($v);
            }
        }
        return $obj;
    }

    /* send header */
    public static function header($_string, $replace = true, $http_response_code = 0) {
        $string = str_replace(array("\r", "\n"), array('', ''), $_string);
        if (!$http_response_code) {
            header($string, $replace);
        } else {
            header($string, $replace, $http_response_code);
        }
        return true;
    }

    /* get from url
     * return string
     * @param string $default
     */
    public static function referer($default = '') {
        $referer = getgpc('s.HTTP_REFERER');
        if (empty($referer)) {
            $referer = $default;
        }
        return strip_tags($referer);
    }

    public static function urlencode($value) {
        if (is_array($value)) {
            return array_map('self::urlencode', $value);
        }
        return $value ? urlencode($value) : $value;
    }

    /*
     * json encode
     */
    public static function output_json($arr) {
        if (PHPVER >= 5.4) {
            return json_encode($arr, JSON_UNESCAPED_UNICODE);
        }
        $json = json_encode(self::urlencode($arr));
        return urldecode($json);
    }

    public static function output_nocache() {
        header("Expires: -1");
        header("Cache-Control: no-store, private, post-check=0, pre-check=0, max-age=0", false);
        header("Pragma: no-cache");
    }

    public static function output_start($nocache = true) {
        ob_end_clean();
        if (getini('site/gzip') && function_exists('ob_gzhandler')) { //whether start gzip
            ob_start('ob_gzhandler');
        } else {
            ob_start();
        }
        if ($nocache) {
            self::output_nocache();
        }
    }

    public static function output_end($echo = false) {
        $content = ob_get_contents();
        ob_get_length() && ob_end_clean();
        $content = preg_replace("/([\\x01-\\x08\\x0b-\\x0c\\x0e-\\x1f])+/", ' ', $content);
        $content = str_replace(array(chr(0), ']]>'), array(' ', ']]&gt;'), $content);
        if ($echo) {
            echo $content;
        } else {
            return $content;
        }
    }

    public static function rep_send($res, $type = 'json') {
        self::output_start();
        if ('html' == $type) {
            header("Content-type: text/html; charset=UTF-8");
        } elseif ('json' == $type) {
            header('Content-type: text/html; charset=UTF-8');
            $res = self::output_json($res);
        } elseif ('xml' == $type) {
            header("Content-type: text/xml");
            $res = '<?xml version="1.0" encoding="utf-8"?' . '>' . "\r\n" . '<root><![CDATA[' . $res;
        } elseif ('text' == $type) {
            header("Content-type: text/plain");
        } else {
            header("Content-type: text/html; charset=UTF-8");
        }
        echo $res;
        self::output_end(true);
        if ('xml' == $type) {
            echo ']]></root>';
        }
        return true;
    }

    /* javascript ,alert
     * return null;
     */
    public static function js_alert($message = '', $after_action = '', $url = '') { //php turn to alert
        $out = "<script language=\"javascript\" type=\"text/javascript\">\n";
        if (!empty($message)) {
            $out .= "alert(\"";
            $out .= str_replace("\\\\n", "\\n", str_replace(array("\r", "\n"), array('', '\n'), $message));
            $out .= "\");\n";
        }
        if (!empty($after_action)) {
            $out .= $after_action . "\n";
        }
        if (!empty($url)) {
            $out .= "document.location.href=\"";
            $out .= $url;
            $out .= "\";\n";
        }
        $out .= "</script>";
        echo $out;
        return true;
    }

    /* redirect to rul
     * return null
     */
    public static function redirect($url, $delay = 0, $js = false, $jsWrapped = true, $return = false) {
        $_delay = intval($delay);
        if (!$js) {
            if (headers_sent() || $_delay > 0) {
                echo <<<EOT
    <html>
    <head>
    <meta http-equiv="refresh" content="{$_delay};URL={$url}" />
    </head>
    </html>
EOT;
            } else {
                header("Location: {$url}");
            }
            return null;
        }

        $out = '';
        if ($jsWrapped) {
            $out .= '<script language="javascript" type="text/javascript">';
        }
        if ($_delay > 0) {
            $out .= "window.setTimeout(function () { document.location='{$url}'; }, {$_delay});";
        } else {
            $out .= "document.location='{$url}';";
        }
        if ($jsWrapped) {
            $out .= '</script>';
        }
        if ($return) {
            return $out;
        }
        echo $out;
        return true;
    }

    public static function isrobot() {
        static $is_robot = null;
        if (isset($is_robot)) {
            return $is_robot;
        }
        $kw_spiders = 'Bot|Crawl|Spider|slurp|sohu-search|lycos|robozilla';
        $kw_browsers = 'MSIE|Netscape|Opera|Konqueror|Mozilla';
        if (!self::strexists(getgpc('s.HTTP_USER_AGENT'), 'http://') && preg_match("/($kw_browsers)/i", getgpc('s.HTTP_USER_AGENT'))) {
            $is_robot = false;
        } elseif (preg_match("/($kw_spiders)/i", getgpc('s.HTTP_USER_AGENT'))) {
            $is_robot = true;
        } else {
            $is_robot = false;
        }
        return $is_robot;
    }

    public static function ismobile() {
        static $is_mobile = null;
        if (isset($is_mobile)) {
            return $is_mobile;
        }
        $ua = getgpc('s.HTTP_USER_AGENT');
        if (empty($ua)) {
            $is_mobile = false;
        } elseif (strpos($ua, 'Mobile') !== false || strpos($ua, 'Android') !== false || strpos($ua, 'Silk/') !== false || strpos($ua, 'Kindle') !== false || strpos($ua, 'BlackBerry') !== false || strpos($ua, 'Opera Mini') !== false || strpos($ua, 'Opera Mobi') !== false) {
            $is_mobile = true;
        } else {
            $is_mobile = false;
        }
        return $is_mobile;
    }

    public static function clientip() {
        $ip = getgpc('s.REMOTE_ADDR');
        if (getgpc('s.HTTP_CLIENT_IP') && preg_match('/^([0-9]{1,3}\.){3}[0-9]{1,3}$/', getgpc('s.HTTP_CLIENT_IP'))) {
            $ip = getgpc('s.HTTP_CLIENT_IP');
        } elseif (getgpc('s.HTTP_X_FORWARDED_FOR') && preg_match_all('#\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}#s', getgpc('s.HTTP_X_FORWARDED_FOR'), $matches)) {
            foreach ($matches[0] AS $xip) {
                if (!preg_match('#^(10|172\.16|192\.168)\.#', $xip)) {
                    $ip = $xip;
                    break;
                }
            }
        }
        return $ip;
    }

     /**
     * @param bool $table
     * @param bool $stop
     */
    public static function dpost($table = false, $stop = false) {
        $str = '';
        $post = $_POST;
        foreach ($post as $k => $v) {
            $str .= "\$" . $k . "= getgpc('p." . $k . "');\n";
        }
        dump($str);
        if (!$table) {
            $str = "\$post = array(\n";
            foreach ($post as $k => $v) {
                $str .= "'" . $k . "'=> \$" . $k . ",\n";
            }
            $str .= "\n)";
            dump($str);
        } else {
            $tablecols = include getini('data/_fields') . $table . '.php';
            $str = "\$post = array(\n";
            foreach ($tablecols as $col => $arr) {
                $str .= "'" . $col . "'=> \$" . $arr . ",\n";
            }
            $str .= "\n);";
            dump($str);
        }
        if ($stop) {
            exit;
        }
    }

}