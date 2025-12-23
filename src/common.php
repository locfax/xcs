<?php

/**
 * @param string $variable
 * @param mixed|null $defVal
 * @param string $runFunc
 * @param bool $addslashes
 * @return mixed
 */
function getgpc(string $variable, $defVal = null, string $runFunc = '', bool $addslashes = true)
{
    $arr = explode('.', $variable);
    if (count($arr) == 2) {
        $tmp = strtoupper($arr[0]);
        $var = $arr[1];
    } else {
        $tmp = false;
        $var = $variable;
    }

    if ($tmp) {
        switch ($tmp) {
            case 'G':
                if ($var == '*') {
                    return $_GET;
                }
                if (!isset($_GET[$var])) {
                    return $defVal;
                }
                $value = $_GET[$var];
                break;
            case 'P':
                if ($var == '*') {
                    return $_POST;
                }
                if (!isset($_POST[$var])) {
                    return $defVal;
                }
                $value = $_POST[$var];
                break;
            default:
                return $defVal;
        }
    } else {
        if (isset($_GET[$var])) {
            $value = $_GET[$var];
        } elseif (isset($_POST[$var])) {
            $value = $_POST[$var];
        } else {
            return $defVal;
        }
    }

    if (is_array($value)) {
        foreach ($value as &$val) {
            gpc_value($val, $runFunc, $addslashes);
        }
    } else {
        gpc_value($value, $runFunc, $addslashes);
    }
    return $value;
}

/**
 * private
 * @param mixed $value
 * @param string $runFunc
 * @param bool $addslashes
 * @return void
 */
function gpc_value(&$value, string $runFunc, bool $addslashes): void
{
    if (empty($value)) {
        return;
    }

    if ($runFunc && strpos($runFunc, '|')) {
        $funds = explode('|', $runFunc);
        if ($addslashes) {
            $funds[] = 'addslashes';
        }
        foreach ($funds as $run) {
            if ('xss' == $run) {
                $value = is_numeric($value) ? $value : \Xcs\Helper\Xss::getInstance()->clean($value);
            } elseif ('addslashes' == $run) {
                $value = is_numeric($value) ? $value : addslashes($value);
            } else {
                $value = call_user_func($run, $value);
            }
        }
        return;
    }

    if ('xss' == $runFunc) {
        $value = is_numeric($value) ? $value : \Xcs\Helper\Xss::getInstance()->clean($value);
    } elseif ($runFunc) {
        $value = call_user_func($runFunc, $value);
    }

    if ($addslashes) {
        $value = is_numeric($value) ? $value : addslashes($value);
    }
}

/**
 * @param string $key
 * @return mixed
 */
function getini(string $key)
{
    $_CFG = \Xcs\App::mergeVars('cfg');
    $k = explode('/', $key);
    switch (count($k)) {
        case 1 :
            return $_CFG[$k[0]] ?? null;
        case 2:
            return $_CFG[$k[0]][$k[1]] ?? null;
        case 3:
            return $_CFG[$k[0]][$k[1]][$k[2]] ?? null;
        case 4:
            return $_CFG[$k[0]][$k[1]][$k[2]][$k[3]] ?? null;
        case 5:
            return $_CFG[$k[0]][$k[1]][$k[2]][$k[3]][$k[4]] ?? null;
        default:
            return null;
    }
}

/**
 * @param string $mainTpl
 * @param int $cacheTime
 * @param string $cacheFile
 * @param string $file
 */
function checkTplRefresh(string $mainTpl, int $cacheTime, string $cacheFile, string $file, $compress = true): void
{
    if (is_file(THEMES_VIEW . $mainTpl)) {
        $tplTime = filemtime(THEMES_VIEW . $mainTpl);
    } else {
        throw new \Error($mainTpl . ' 模板不存在');
    }
    if ($tplTime < $cacheTime) {
        return;
    }

    !is_dir(THEMES_CACHE) && mkdir(THEMES_CACHE);

    $template = new \Xcs\Template();
    $template->parse(THEMES_CACHE, THEMES_VIEW, $mainTpl, $cacheFile, $file, $compress);
}

/**
 * @param string $file
 * @param array $data
 * @param bool $returnTplFile
 * @param string $type
 * @return array|string
 */
function template(string $file, array $data = [], bool $returnTplFile = false, string $type = 'html', $compress = true)
{
    $_tplId = getini('site/themes');
    $tplFile = $_tplId ? $_tplId . '/' . $file . '.htm' : $file . '.htm';
    if ($returnTplFile) {
        return $tplFile;
    }

    $cacheFile = APP_KEY . '_' . md5($_tplId . '_' . $file . '_host_' . $_SERVER['HTTP_HOST']) . '.php';
    $cacheTpl = THEMES_CACHE . $cacheFile;
    $cacheTime = is_file($cacheTpl) ? filemtime($cacheTpl) : 0;

    checkTplRefresh($tplFile, $cacheTime, $cacheFile, $file, $compress);

    if (!empty($data)) {
        extract($data);
    }

    ob_start();
    include $cacheTpl;
    $content = ob_get_contents();
    ob_get_length() && ob_end_clean();
    return ['type' => $type, 'content' => $content];
}

/**
 * 模板使用的url构造函数
 * @param string $udi
 * @param array $params
 * @return string
 */
function url(string $udi, array $params = []): string
{
    return \Xcs\App::url($udi, $params);
}

/**
 * 数组 转 对象
 *
 * @param array $arr 数组
 * @return array|object
 */
function array2object(array $arr)
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
function object2array(object $obj): array
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
 * @param mixed $string
 * @return float|array|int|string
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
 * @param mixed $value
 * @return float|array|int|string
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
 * quotes get post cookie by \char(21)'
 * @param mixed $string
 * @return float|array|int|string
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
 * it's pair to daddcslashes
 * @param mixed $value
 * @return float|array|int|string
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
 * @param mixed $text
 * @return float|int|string
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
 * @param mixed $text
 * @return float|int|string
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

if (!function_exists('locTime')) {
    /**
     * @param int $uTimeOffset
     * @return array
     */
    function locTime(int $uTimeOffset): array
    {
        static $dtFormat = null, $timeOffset = 8;
        if (is_null($dtFormat)) {
            $dtFormat = [
                'd' => getini('settings/dateFormat') ?: 'Y-m-d',
                't' => getini('settings/timeFormat') ?: 'H:i:s'
            ];
            $dtFormat['dt'] = $dtFormat['d'] . ' ' . $dtFormat['t'];
            $timeOffset = getini('settings/timezone') ?: $timeOffset; //default is Asia/Shanghai
        }
        $offset = $uTimeOffset == 999 ? $timeOffset : $uTimeOffset;
        return [$offset, $dtFormat];
    }
}


if (!function_exists('dgmdate')) {
    /**
     * @param int $timestamp
     * @param string $format
     * @param int $uTimeOffset
     * @param string $uFormat
     * @return string
     */

    function dgmdate(int $timestamp, string $format = 'dt', int $uTimeOffset = 999, string $uFormat = ''): string
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
                    return intval($time / 3600) . ' 小时前';
                } elseif ($time > 1800) {
                    return '半小时前';
                } elseif ($time > 60) {
                    return intval($time / 60) . ' 分钟前';
                } elseif ($time > 0) {
                    return $time . ' 秒前';
                } elseif (0 == $time) {
                    return '刚才';
                }
                return $s;
            } elseif (($days = intval(($todayTimestamp - $timestamp) / 86400)) >= 0 && $days < 7) {
                if (0 == $days) {
                    return '昨天 ' . gmdate('H:i', $timestamp);
                } elseif (1 == $days) {
                    return '前天 ' . gmdate('H:i', $timestamp);
                } else {
                    return ($days + 1) . ' 天前';
                }
            } elseif (gmdate('Y', $timestamp) == gmdate('Y', $nowTime)) {
                return gmdate('m-d H:i', $timestamp);
            }
            return $s;
        }
        $format = $dtFormat[$format] ?? $format;
        return gmdate($format, $timestamp);
    }
}


if (!function_exists('clientIp')) {
    /**
     * @return string
     */
    function clientIp(): string
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
}

if (!function_exists('str_contains')) {
    function str_contains($str, $need): bool
    {
        return strpos($str, $need) !== false;
    }
}

/**
 * @param mixed $var
 * @param int $halt
 * @param string $func
 */
function dump($var, int $halt = 0, string $func = 'p'): void
{

    echo '<pre>';
    if ('p' == $func) {
        print_r($var);
    } else {
        var_dump($var);
    }
    echo '</pre>';
    if ($halt) {
        exit;
    }
}

/**
 * @param bool $stop
 */
function post(bool $stop = false): void
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