<?php

//系统级别函数

/**
 * @param $variable
 * @param null $defval
 * @param string $runfunc
 * @param bool $emptyrun
 * @return null
 */
function getgpc($variable, $defval = null, $runfunc = 'daddslashes', $emptyrun = false) {
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
function gpc_val($val, $runfunc, $emptyrun) {
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

//keypath  path1/path2/path3
function getini($key) {
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

//加载系统级别缓存
function loadcache($cachename, $reset = false) {
    if (!$cachename) {
        return null;
    }
    $data = sysdata($cachename, $reset);
    if ('settings' === $cachename && $data) {
        \Xcs\App::mergeVars('cfg', array('settings' => json_decode($data, true)));
        return true;
    }
    return json_decode($data, true);
}

/**
 * 系统级别缓存数据
 * @param $cachename
 * @param $reset
 * @return array|string
 */

function sysdata($cachename, $reset = false) {
    $lost = null;
    if ($reset) {
        $lost = $cachename; //强制设置为没取到
        $data = '[]';
    } else {
        $data = \Xcs\Context::cache('get', 'sys_' . $cachename);
        if (!$data) {
            $lost = $cachename;  //未取到数据
        }
    }
    if (is_null($lost)) {
        return $data; //取到全部数据 则返回
    }
    return \Xcs\Cache\SysData::lost($lost, $reset);
}

/**
 * @param $maintpl
 * @param $subtpl
 * @param $cachetime
 * @param $cachefile
 * @param $file
 */
function checktplrefresh($maintpl, $subtpl, $cachetime, $cachefile, $file) {
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
function template($file, $gettplfile = false) {
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
function url($udi, $param = array()) {
    $_udi = explode('/', $udi);
    $url = '?' . \Xcs\App::_dCTL . '=' . $_udi[0] . '&' . \Xcs\App::_dACT . '=' . $_udi[1];

    if (!empty($param)) {
        foreach ($param as $key => $val) {
            $url .= '&' . $key . '=' . $val;
        }
    }
    return SITEPATH . $url;
}

function floatvaldec($v, $dec = ',') {
    return floatval(str_replace(",", ".", preg_replace("[^-0-9$dec]", "", $v)));
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

/**
 * 数组 转 对象
 *
 * @param array $arr 数组
 * @return object|mixed
 */
function array_to_object($arr) {
    if (gettype($arr) != 'array') {
        return $arr;
    }
    foreach ($arr as $k => $v) {
        if (gettype($v) == 'array' || getType($v) == 'object') {
            $arr[$k] = (object)array_to_object($v);
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
function object_to_array($obj) {
    $obj = (array)$obj;
    foreach ($obj as $k => $v) {
        if (gettype($v) == 'object' || gettype($v) == 'array') {
            $obj[$k] = (array)object_to_array($v);
        }
    }
    return $obj;
}