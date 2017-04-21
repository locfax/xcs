<?php

//系统级别函数

/**
 * @param $variable
 * @param null $defval
 * @param string $runfunc
 * @param bool $emptyrun
 * @return null
 */
function getgpc($variable, $defval = null, $runfunc = '', $emptyrun = false) {
    if (1 == strpos($variable, '.')) {
        $tmp = strtoupper(substr($variable, 0, 1));
        $var = substr($variable, 2);
    } else {
        $tmp = false;
        $var = $variable;
    }
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
                $val = \helper\Xss::getInstance()->clean($val);
            } else {
                $val = $run($val);
            }
        }
        return $val;
    }
    if ('xss' == $runfunc) {
        return \helper\Xss::getInstance()->clean($val);
    }
    if ($runfunc) {
        return $runfunc($val);
    }
    return $val;
}

//keypath  path1/path2/path3
function getini($key) {
    $_CFG = App::mergeVars('cfg');
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
 * 有模型的缓存  model/data/*.php
 * @param $cachekey
 * @param bool $reset
 * @return bool|mixed|string
 */
function modeldata($cachekey, $reset = false) {
    if (!$cachekey) {
        return false;
    }
    if (!$reset) {
        $data = \Context::cache('get', $cachekey);
        if (is_null($data)) {
            $dataclass = '\\model\\data\\' . $cachekey;
            $data = $dataclass::getInstance()->getdata();
            \Context::cache('set', $cachekey, output_json($data));
        } else {
            $data = json_decode($data, true);
        }
        return $data;
    } else {//重置缓存
        $dataclass = '\\model\\data\\' . $cachekey;
        $data = $dataclass::getInstance()->getdata();
        \Context::cache('set', $cachekey, output_json($data));
    }
}

//加载系统级别缓存
function loadcache($cachename, $reset = false) {
    if (!$cachename) {
        return null;
    }
    $data = sysdata($cachename, $reset);
    if ('settings' === $cachename && $data) {
        App::mergeVars('cfg', array('settings' => json_decode($data, true)));
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
        $data = \Context::cache('get', 'sys_' . $cachename);
        if (!$data) {
            $lost = $cachename;  //未取到数据
        }
    }
    if (is_null($lost)) {
        return $data; //取到全部数据 则返回
    }
    return \model\cache\SysData::lost($lost, $reset);
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
    if(is_file($tpldir . $subtpl)) {
        $tpltime = filemtime($tpldir . $subtpl);
    } else {
        $tpltime = 0;
    }
    if ($tpltime < intval($cachetime)) {
        return;
    }
    \base\Template::getInstance()->parse(getini('data/_view'), $tpldir, $maintpl, $cachefile, $file);
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
 * @return string
 */
function url($udi) {
    //$_path = getini('site/path');
    $_udis = explode('/', $udi);
    $url = '?' . App::_dCTL . '=' . $_udis[0] . '&' . App::_dACT . '=' . $_udis[1];
    for ($i = 2; $i < count($_udis); $i++) {
        $url .= '&' . $_udis[$i] . '=' . $_udis[$i + 1];
        $i++;
    }
    return SITEPATH . $url;
}

function floatvaldec($v, $dec = ',') {
    return floatval(str_replace(",", ".", preg_replace("[^-0-9$dec]", "", $v)));
}