<?php

/**
 * @param mixed $variable
 * @param mixed $defVal
 * @param string $runFunc
 * @return mixed
 */
function getgpc($variable, $defVal = null, $runFunc = '')
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
                    return $defVal;
                }
                $value = $_GET[$var];
                break;
            case 'P':
                $type = 'POST';
                if (!isset($_POST[$var])) {
                    return $defVal;
                }
                $value = $_POST[$var];
                break;
            case 'C':
                $type = 'COOKIE';
                if (!isset($_COOKIE[$var])) {
                    return $defVal;
                }
                $value = $_COOKIE[$var];
                break;
            case 'S' :
                $type = 'SERVER';
                break;
            default:
                return $defVal;
        }
    } else {
        if (isset($_GET[$var])) {
            $type = 'GET';
            if (!isset($_GET[$var])) {
                return $defVal;
            }
            $value = $_GET[$var];
        } elseif (isset($_POST[$var])) {
            $type = 'POST';
            if (!isset($_POST[$var])) {
                return $defVal;
            }
            $value = $_POST[$var];
        } else {
            return $defVal;
        }
    }
    if (in_array($type, ['GET', 'POST', 'COOKIE'])) {
        if (is_array($value)) {
            array_walk_recursive($value, 'gpc_value', $runFunc);
        } else {
            gpc_value($value, 0, $runFunc);
        }
        return $value;
    } elseif ('SERVER' == $type) {
        return isset($_SERVER[$var]) ? $_SERVER[$var] : $defVal;
    } else {
        return $defVal;
    }
}

/**
 * private
 * @param $value
 * @param $key
 * @param $runFunc
 * @return array|bool|int|mixed|string|void
 */
function gpc_value(&$value, $key, $runFunc)
{
    if (empty($value)) {
        return;
    }

    if ($runFunc && strpos($runFunc, '|')) {
        $funds = explode('|', $runFunc);
        array_push($funds, 'addslashes');
        foreach ($funds as $run) {
            if ('xss' == $run) {
                $value = is_numeric($value) ? $value : Xcs\Helper\Xss::getInstance()->clean($value);
            } elseif ('addslashes' == $run) {
                $value = is_numeric($value) ? $value : addslashes($value);
            } else {
                $value = call_user_func($run, $value);
            }
        }
        return;
    }

    if ('xss' == $runFunc) {
        $value = is_numeric($value) ? $value : Xcs\Helper\Xss::getInstance()->clean($value);
    } elseif ($runFunc) {
        $value = call_user_func($runFunc, $value);
    }

    $value = is_numeric($value) ? $value : addslashes($value);
}

/**
 * @param $key
 * @return null
 */
function getini($key)
{
    $_CFG = Xcs\App::mergeVars('cfg');
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
 * @param $mainTpl
 * @param $subTpl
 * @param $cacheTime
 * @param $cacheFile
 * @param $file
 */
function checkTplRefresh($mainTpl, $subTpl, $cacheTime, $cacheFile, $file)
{
    $tplDir = DATA_TPLDIR;
    if (is_file($tplDir . $subTpl)) {
        $tplTime = filemtime($tplDir . $subTpl);
    } else {
        $tplTime = 0;
    }
    if ($tplTime < intval($cacheTime)) {
        return;
    }
    $template = new Xcs\Template();
    $template->parse(DATA_VIEW, $tplDir, $mainTpl, $cacheFile, $file);
}

/**
 * @param $file
 * @param bool $getTplFile
 * @return string
 */
function template($file, $getTplFile = false)
{
    $_tplId = getini('site/themes');
    $tplFile = $_tplId ? $_tplId . '/' . $file . '.htm' : $file . '.htm';
    if ($getTplFile) {
        return $tplFile;
    }
    $cacheFile = APP_KEY . '_' . $_tplId . '_' . str_replace('/', '_', $file) . '_tpl.php';
    $cacheTpl = DATA_VIEW . $cacheFile;
    $cacheTime = is_file($cacheTpl) ? filemtime($cacheTpl) : 0;
    checkTplRefresh($tplFile, $tplFile, $cacheTime, $cacheFile, $file);
    return $cacheTpl;
}

/**
 * @param $udi
 * @param $param
 * @return string
 */
function url($udi, $param = [])
{
    $_udi = explode('/', $udi);
    $url = '?' . Xcs\App::$_dCTL . '=' . $_udi[0] . '&' . Xcs\App::$_dACT . '=' . $_udi[1];

    if (!empty($param)) {
        foreach ($param as $key => $val) {
            $url .= '&' . $key . '=' . $val;
        }
    }
    return $url;
}

/**
 * 数组 转 对象
 *
 * @param array $arr 数组
 * @return object|mixed
 */
function array2object($arr)
{
    if (gettype($arr) != 'array') {
        return $arr;
    }
    foreach ($arr as $k => $v) {
        if (gettype($v) == 'array' || getType($v) == 'object') {
            $arr[$k] = array2object($v);
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
function object2array($obj)
{
    $obj = (array)$obj;
    foreach ($obj as $k => $v) {
        if (gettype($v) == 'object' || gettype($v) == 'array') {
            $obj[$k] = object2array($v);
        }
    }
    return $obj;
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
function char_input($text)
{
    if (empty($text)) {
        return $text;
    }
    if (is_numeric($text)) {
        return $text;
    }
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * @param $text
 * @return string
 */
function char_output($text)
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
 * @param $uTimeOffset
 * @return array
 */
function locTime($uTimeOffset)
{
    static $dtFormat = null, $timeOffset = 8;
    if (is_null($dtFormat)) {
        $dtFormat = [
            'd' => getini('settings/dateformat') ?: 'Y-m-d',
            't' => getini('settings/timeformat') ?: 'H:i:s'
        ];
        $dtFormat['dt'] = $dtFormat['d'] . ' ' . $dtFormat['t'];
        $timeOffset = getini('settings/timezone') ?: $timeOffset; //default is Asia/Shanghai
    }
    $offset = $uTimeOffset == 999 ? $timeOffset : $uTimeOffset;
    return [$offset, $dtFormat];
}

/**
 * @param $timestamp
 * @param string $format
 * @param int $uTimeOffset
 * @param string $uFormat
 * @return string
 */
function dgmdate($timestamp, $format = 'dt', $uTimeOffset = 999, $uFormat = '')
{
    if (!$timestamp) {
        return '';
    }
    $locTime = locTime($uTimeOffset);
    $offset = $locTime[0];
    $dtFormat = $locTime[1];
    $timestamp += $offset * 3600;
    if ('u' == $format) {
        $nowTime = time() + $offset * 3600;
        $todayTimestamp = $nowTime - $nowTime % 86400;
        $format = !$uFormat ? $dtFormat['dt'] : $uFormat;
        $s = gmdate($format, $timestamp);
        $time = $nowTime - $timestamp;
        if ($timestamp >= $todayTimestamp) {
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
        } elseif (($days = intval(($todayTimestamp - $timestamp) / 86400)) >= 0 && $days < 7) {
            if (0 == $days) {
                return '<span title="' . $s . '">昨天&nbsp;' . gmdate('H:i', $timestamp) . '</span>';
            } elseif (1 == $days) {
                return '<span title="' . $s . '">前天&nbsp;' . gmdate('H:i', $timestamp) . '</span>';
            } else {
                return '<span title="' . $s . '">' . ($days + 1) . '&nbsp;天前</span>';
            }
        } elseif (gmdate('Y', $timestamp) == gmdate('Y', $nowTime)) {
            return '<span title="' . $s . '">' . gmdate('m-d H:i', $timestamp) . '</span>';
        } else {
            return $s;
        }
    }
    $format = isset($dtFormat[$format]) ? $dtFormat[$format] : $format;
    return gmdate($format, $timestamp);
}

/**
 * @param $str
 * @param $needle
 * @return bool
 */
function dstrpos($str, $needle)
{
    return !(false === strpos($str, $needle));
}

/**
 * @return null
 */
function clientIp()
{
    $onlineIp = '';
    if (getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
        $onlineIp = getenv('HTTP_CLIENT_IP');
    } elseif (getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
        $onlineIp = getenv('HTTP_X_FORWARDED_FOR');
    } elseif (getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
        $onlineIp = getenv('REMOTE_ADDR');
    } elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
        $onlineIp = $_SERVER['REMOTE_ADDR'];
    }
    return $onlineIp;
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
 * @param bool $stop
 */
function post($stop = false)
{
    $str = '';
    $post = $_POST;
    foreach ($post as $k => $v) {
        $str .= "\$" . $k . "= getgpc('p." . $k . "');\n";
    }
    dump($str);

    $str = "\$post = array(\n";
    foreach ($post as $k => $v) {
        $str .= "'" . $k . "'=> \$" . $k . ",\n";
    }
    $str .= "\n)";
    dump($str);

    if ($stop) {
        exit;
    }
}