<?php

//辅助函数

function dimplode($arr) {
    return "'" . implode("','", (array)$arr) . "'";
}

/*
 *
 * 屏蔽单双引号等
 * 提供给数据库搜索
 */
function input_char($text) {
    if (empty($text)) {
        return $text;
    }
    return htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8');
}

/*
*  屏蔽单双引号等
*  提供给html显示 或者 input输入框
*/
function input_text($text) {
    if (empty($text)) {
        return $text;
    }
    return htmlspecialchars(stripslashes($text), ENT_QUOTES, 'UTF-8');
}

/*
 *
 * function input_char 的还原
 */
function output_char($text) {
    if (empty($text)) {
        return $text;
    }
    return stripslashes(htmlspecialchars_decode($text, ENT_QUOTES));
}

/* qutotes get post cookie by \'
 * return string
 */
function daddslashes($string) {
    if (empty($string)) {
        return $string;
    }
    if (is_numeric($string)) {
        return $string;
    }
    if (is_array($string)) {
        return array_map('daddslashes', $string);
    }
    return addslashes($string);
}

/*
 * it's paire to daddslashes
 */
function dstripslashes($value) {
    if (empty($value)) {
        return $value;
    }
    if (is_numeric($value)) {
        return $value;
    }
    if (is_array($value)) {
        return array_map('dstripslashes', $value);
    }
    return stripslashes($value);
}

function strexists($str, $needle) {
    return !(false === strpos($str, $needle));
}

function array_index($arr, $col) {
    if (!is_array($arr)) {
        return $arr;
    }
    $rows = array();
    foreach ($arr as $row) {
        $rows[$row[$col]] = $row;
    }
    return $rows;
}

/* send header */

function dheader($_string, $replace = true, $http_response_code = 0) {
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
function dreferer($default = '') {
    $referer = getgpc('s.HTTP_REFERER');
    if (empty($referer)) {
        $referer = $default;
    }
    return strip_tags($referer);
}

/**
 * @param $utimeoffset
 * @return array
 */
function loctime($utimeoffset) {
    static $dtformat = null, $timeoffset = 8;
    if (is_null($dtformat)) {
        $dtformat = array(
            'd' => getini('settings/dateformat') ?: 'Y-m-d',
            't' => getini('settings/timeformat') ?: 'H:i:s'
        );
        $dtformat['dt'] = $dtformat['d'] . ' ' . $dtformat['t'];
        $timeoffset = getini('settings/timezone') ?: $timeoffset; //defualt is Asia/Shanghai
    }
    $offset = $utimeoffset == 999 ? $timeoffset : $utimeoffset;
    return array($offset, $dtformat);
}

/**
 * @param $timestamp
 * @param string $format
 * @param int $utimeoffset
 * @param string $uformat
 * @return string
 */
function dgmdate($timestamp, $format = 'dt', $utimeoffset = 999, $uformat = '') {
    if (!$timestamp) {
        return '';
    }
    $loctime = loctime($utimeoffset);
    $offset = $loctime[0];
    $dtformat = $loctime[1];
    $timestamp += $offset * 3600;
    if ('u' == $format) {
        $nowtime = time() + $offset * 3600;
        $todaytimestamp = $nowtime - $nowtime % 86400;
        $format = !$uformat ? $dtformat['dt'] : $uformat;
        $s = gmdate($format, $timestamp);
        $time = $nowtime - $timestamp;
        if ($timestamp >= $todaytimestamp) {
            if ($time > 3600) {
                return '<span title="' . $s . '">' . intval($time / 3600) . '&nbsp;小时前</span>';
            } elseif ($time > 1800) {
                return '<span title="' . $s . '">半小时前</span>';
            } elseif ($time > 60) {
                return '<span title="' . $s . '">' . intval($time / 60) . '&nbsp;分钟前</span>';
            } elseif ($time > 0) {
                return '<span title="' . $s . '">' . $time . '&nbsp;秒前</span>';
            } elseif (0 == $time) {
                return '<span title="' . $s . '">刚才</span>';
            } else {
                return $s;
            }
        } elseif (($days = intval(($todaytimestamp - $timestamp) / 86400)) >= 0 && $days < 7) {
            if (0 == $days) {
                return '<span title="' . $s . '">昨天&nbsp;' . gmdate('H:i', $timestamp) . '</span>';
            } elseif (1 == $days) {
                return '<span title="' . $s . '">前天&nbsp;' . gmdate('H:i', $timestamp) . '</span>';
            } else {
                return '<span title="' . $s . '">' . ($days + 1) . '&nbsp;天前</span>';
            }
        } elseif (gmdate('Y', $timestamp) == gmdate('Y', $nowtime)) {
            return '<span title="' . $s . '">' . gmdate('m-d H:i', $timestamp) . '</span>';
        } else {
            return $s;
        }
    }
    $format = isset($dtformat[$format]) ? $dtformat[$format] : $format;
    return gmdate($format, $timestamp);
}

function durlencode($value) {
    if (is_array($value)) {
        return array_map('durlencode', $value);
    }
    return $value ? urlencode($value) : $value;
}

/*
 * json encode
 */
function output_json($arr) {
    if (PHPVER >= 5.4) {
        return json_encode($arr, JSON_UNESCAPED_UNICODE);
    }
    $json = json_encode(durlencode($arr));
    return urldecode($json);
}

function output_nocache() {
    header("Expires: -1");
    header("Cache-Control: no-store, private, post-check=0, pre-check=0, max-age=0", false);
    header("Pragma: no-cache");
}

function output_start($nocache = true) {
    ob_end_clean();
    if (getini('site/gzip') && function_exists('ob_gzhandler')) { //whether start gzip
        ob_start('ob_gzhandler');
    } else {
        ob_start();
    }
    if ($nocache) {
        output_nocache();
    }
}

function output_end($echo = false) {
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

function rep_send($res, $type = 'json') {
    output_start();
    if ('html' == $type) {
        header("Content-type: text/html; charset=UTF-8");
    } elseif ('json' == $type) {
        header('Content-type: text/html; charset=UTF-8');
        $res = output_json($res);
    } elseif ('xml' == $type) {
        header("Content-type: text/xml");
        $res = '<?xml version="1.0" encoding="utf-8"?' . '>' . "\r\n" . '<root><![CDATA[' . $res;
    } elseif ('text' == $type) {
        header("Content-type: text/plain");
    } else {
        header("Content-type: text/html; charset=UTF-8");
    }
    echo $res;
    output_end(true);
    if ('xml' == $type) {
        echo ']]></root>';
    }
    return true;
}

/* javascript ,alert
 * return null;
 */

function js_alert($message = '', $after_action = '', $url = '') { //php turn to alert
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

function redirect($url, $delay = 0, $js = false, $jsWrapped = true, $return = false) {
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
        return;
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

function isrobot() {
    static $is_robot = null;
    if (isset($is_robot)) {
        return $is_robot;
    }
    $kw_spiders = 'Bot|Crawl|Spider|slurp|sohu-search|lycos|robozilla';
    $kw_browsers = 'MSIE|Netscape|Opera|Konqueror|Mozilla';
    if (!strexists(getgpc('s.HTTP_USER_AGENT'), 'http://') && preg_match("/($kw_browsers)/i", getgpc('s.HTTP_USER_AGENT'))) {
        $is_robot = false;
    } elseif (preg_match("/($kw_spiders)/i", getgpc('s.HTTP_USER_AGENT'))) {
        $is_robot = true;
    } else {
        $is_robot = false;
    }
    return $is_robot;
}

function ismobile() {
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

function clientip() {
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
 * @param $var
 * @param int $halt
 * @param string $func
 */
function dump($var, $halt = 0, $func = 'p') {
    echo '<style>.track {
      font-family:Verdana, Arial, Helvetica, sans-serif;
      font-size: 12px;
      background-color: #FFFFCC;
      padding: 10px;
      border: 1px solid #FF9900;
      }</style>';
    echo "<div class=\"track\">";
    echo '<pre>';
    if ('p' == $func) {
        print_r($var);
    } else {
        var_dump($var);
    }
    echo '</pre>';
    echo "</div>";
    if ($halt) {
        exit;
    }
}

/**
 * @param bool $table
 * @param bool $stop
 */
function dpost($table = false, $stop = false) {
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
        $tablecols = include getini('data/_fields') . 'to_' . $table . '.php';
        $str = "\$post = array(\n";
        foreach ($tablecols as $col => $arr) {
            $str .= "'" . $col . "'=> \$" . $k . ",\n";
        }
        $str .= "\n);";
        dump($str);
    }
    if ($stop) {
        exit;
    }
}