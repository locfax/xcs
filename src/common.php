<?php

/**
 * @param $variable
 * @param null $defval
 * @param string $runfunc
 * @param bool $emptyrun
 * @return null
 */
function getgpc($variable, $defval = null, $runfunc = 'daddslashes', $emptyrun = false)
{
    if (1 == strpos($variable, '.')) {
        $tmp = strtoupper(substr($variable, 0, 1));
        $var = substr($variable, 2);
    } else {
        $tmp = false;
        $var = $variable;
    }
    $value = '';
    if ($tmp) {
        switch ($tmp) {
            case 'G':
                $type = 'GET';
                if (!isset($_GET[$var])) {
                    return $defval;
                }
                $value = $_GET[$var];
                break;
            case 'P':
                $type = 'POST';
                if (!isset($_POST[$var])) {
                    return $defval;
                }
                $value = $_POST[$var];
                break;
            case 'C':
                $type = 'COOKIE';
                if (!isset($_COOKIE[$var])) {
                    return $defval;
                }
                $value = $_COOKIE[$var];
                break;
            case 'S' :
                $type = 'SERVER';
                break;
            default:
                return $defval;
        }
    } else {
        if (isset($_GET[$var])) {
            $type = 'GET';
            if (!isset($_GET[$var])) {
                return $defval;
            }
            $value = $_GET[$var];
        } elseif (isset($_POST[$var])) {
            $type = 'POST';
            if (!isset($_POST[$var])) {
                return $defval;
            }
            $value = $_POST[$var];
        } else {
            return $defval;
        }
    }
    if (in_array($type, array('GET', 'POST', 'COOKIE'))) {
        return gpc_val($value, $runfunc, $emptyrun);
    } elseif ('SERVER' == $type) {
        return isset($_SERVER[$var]) ? $_SERVER[$var] : $defval;
    } else {
        return $defval;
    }
}

/**
 * @param $val
 * @param $runfunc
 * @param $emptyrun
 * @return string
 */
function gpc_val($val, $runfunc, $emptyrun)
{
    if ('' == $val) {
        return $emptyrun ? $runfunc($val) : '';
    }
    if ($runfunc && strpos($runfunc, '|')) {
        $funcs = explode('|', $runfunc);
        foreach ($funcs as $run) {
            if ('xss' == $run) {
                $val = \Xcs\Helper\Xss::getInstance()->clean($val);
            } else {
                $val = $run($val);
            }
        }
        return $val;
    }
    if ('xss' == $runfunc) {
        return \Xcs\Helper\Xss::getInstance()->clean($val);
    }
    if ($runfunc) {
        return $runfunc($val);
    }
    return $val;
}

/**
 * @param $key
 * @return null
 */
function getini($key)
{
    $_CFG = \Xcs\App::mergeVars('cfg');
    $k = explode('/', $key);
    switch (count($k)) {
        case 1:
            return isset($_CFG[$k[0]]) ? $_CFG[$k[0]] : null;
        case 2:
            return isset($_CFG[$k[0]][$k[1]]) ? $_CFG[$k[0]][$k[1]] : null;
        case 3:
            return isset($_CFG[$k[0]][$k[1]][$k[2]]) ? $_CFG[$k[0]][$k[1]][$k[2]] : null;
        case 4:
            return isset($_CFG[$k[0]][$k[1]][$k[2]][$k[3]]) ? $_CFG[$k[0]][$k[1]][$k[2]][$k[3]] : null;
        case 5:
            return isset($_CFG[$k[0]][$k[1]][$k[2]][$k[3]][$k[4]]) ? $_CFG[$k[0]][$k[1]][$k[2]][$k[3]][$k[4]] : null;
        default:
            return null;
    }
}

/**
 * @param $maintpl
 * @param $subtpl
 * @param $cachetime
 * @param $cachefile
 * @param $file
 */
function checktplrefresh($maintpl, $subtpl, $cachetime, $cachefile, $file)
{
    $tpldir = getini('data/tpldir');
    if (is_file($tpldir . $subtpl)) {
        $tpltime = filemtime($tpldir . $subtpl);
    } else {
        $tpltime = 0;
    }
    if ($tpltime < intval($cachetime)) {
        return;
    }
    $template = new \Xcs\Template();
    $template->parse(getini('data/_view'), $tpldir, $maintpl, $cachefile, $file);
}

/**
 * @param $file
 * @param bool $gettplfile
 * @return string
 */
function template($file, $gettplfile = false)
{
    $_tplid = getini('site/themes');
    $tplfile = $_tplid . '/' . $file . '.htm';
    if ($gettplfile) {
        return $tplfile;
    }
    $cachefile = strtolower(APPKEY) . '_' . $_tplid . '_' . str_replace('/', '_', $file) . '_tpl.php';
    $cachetpl = getini('data/_view') . $cachefile;
    $cachetime = is_file($cachetpl) ? filemtime($cachetpl) : 0;
    checktplrefresh($tplfile, $tplfile, $cachetime, $cachefile, $file);
    return $cachetpl;
}

/**
 * @param $udi
 * @param $param
 * @return string
 */
function url($udi, $param = array())
{
    $_udi = explode('/', $udi);
    $url = '?' . \Xcs\App::_dCTL . '=' . $_udi[0] . '&' . \Xcs\App::_dACT . '=' . $_udi[1];

    if (!empty($param)) {
        foreach ($param as $key => $val) {
            $url .= '&' . $key . '=' . $val;
        }
    }
    return SITEPATH . $url;
}

/**
 * @param $string
 * @return array|string
 */
function daddslashes($string)
{
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

/**
 * @param $value
 * @return array|string
 */
function dstripslashes($value)
{
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

/**
 * qutotes get post cookie by \char(21)'
 * @param $string
 * @return array|string
 */
function daddcslashes($string)
{
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
function dstripcslashes($value)
{
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
 * @param $text
 * @return string
 */
function input_char($text)
{
    if (empty($text)) {
        return $text;
    }
    if (is_numeric($text)) {
        return $text;
    }
    return htmlspecialchars(addslashes($text), ENT_QUOTES, 'UTF-8');
}

/**
 * @param $text
 * @return string
 */
function output_char($text)
{
    if (empty($text)) {
        return $text;
    }
    if (is_numeric($text)) {
        return $text;
    }
    return htmlspecialchars(stripslashes($text), ENT_QUOTES, 'UTF-8');
}

/**
 * @param $str
 * @param bool $url_encoded
 * @return null|string|string[]
 */
function remove_invisible_characters($str, $url_encoded = TRUE)
{
    $non_displayables = array();
    // every control character except newline (dec 10),
    // carriage return (dec 13) and horizontal tab (dec 09)
    if ($url_encoded) {
        $non_displayables[] = '/%0[0-8bcef]/i';    // url encoded 00-08, 11, 12, 14, 15
        $non_displayables[] = '/%1[0-9a-f]/i';    // url encoded 16-31
        $non_displayables[] = '/%7f/i';    // url encoded 127
    }
    $non_displayables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S';    // 00-08, 11, 12, 14-31, 127
    do {
        $str = preg_replace($non_displayables, '', $str, -1, $count);
    } while ($count);
    return $str;
}

/**
 * @param $utimeoffset
 * @return array
 */
function loctime($utimeoffset)
{
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
function dgmdate($timestamp, $format = 'dt', $utimeoffset = 999, $uformat = '')
{
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

/**
 * @param $var
 * @param int $halt
 * @param string $func
 */
function dump($var, $halt = 0, $func = 'p')
{
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
function post($table = false, $stop = false)
{
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