<?php

namespace Xcs;

class User {

    const _USERKEY = 'um_';
    const _ROLEKEY = 'roles';

    /**
     * @param $userData
     * @param string $uid
     * @param null $rolesData
     * @param int $left
     * @return bool
     */
    public static function setUser($uid = '', array $userData, $rolesData = null, $left = 0) {
        if (!is_null($rolesData)) {
            $userData[self::_ROLEKEY] = is_array($rolesData) ? implode(',', $rolesData) : $rolesData;
        }
        $datakey = self::_USERKEY . $uid;
        $ret = self::_setData($datakey, $userData, $left);
        return $ret;
    }

    /**
     * @param string $uid
     * @return null
     */
    public static function getUser($uid = '') {
        $datakey = self::_USERKEY . $uid;
        $ret = self::_getData($datakey);
        return $ret;
    }

    public static function clearUser($uid = '') {
        $datakey = self::_USERKEY . $uid;
        self::_setData($datakey, '', -86400 * 365);
    }

    /**
     * @param string $uid
     * @return null
     */
    public static function getRoles($uid = '') {
        $user = self::getUser($uid);
        return isset($user[self::_ROLEKEY]) ?
            $user[self::_ROLEKEY] :
            null;
    }


    /**
     * @param string $uid
     * @return array
     */
    public static function getRolesArray($uid = '') {
        $roles = self::getRoles($uid);
        if (empty($roles)) {
            return array();
        }
        if (!is_array($roles)) {
            $roles = explode(',', $roles);
        }
        return array_map('trim', $roles);
    }

    /**
     * @param $key
     * @param null $type
     * @return array
     */
    private static function _getData($key, $type = null) {
        $ret = '';
        if (is_null($type)) {
            $type = getini('auth/handle');
        }
        if ('SESSION' == $type) {
            if (PHP_SESSION_NONE == session_status()) {
                session_start();
            }
            $ret = isset($_SESSION[getini('auth/prefix') . $key]) ? $_SESSION[getini('auth/prefix') . $key] : null;
        } elseif ('COOKIE' == $type) {
            $key = self::getCookieKey($key);
            $ret = isset($_COOKIE[$key]) ? json_decode(self::authCode($_COOKIE[$key], 'DECODE'), true) : null;
        } elseif ('REDIS' == $type) {
            $redis = DB::dbo('redis.user');
            $data = $redis->get($key);
            $ret = $data ? $data : null;
        }
        return $ret;
    }


    /**
     * @param $key
     * @param $val
     * @param int $life
     * @param null $type
     * @return bool
     */
    private static function _setData($key, $val, $life = 0, $type = null) {
        $ret = false;
        if (is_null($type)) {
            $type = getini('auth/handle');
        }
        if ('SESSION' == $type) {
            if (PHP_SESSION_NONE == session_status()) {
                session_start();
            }
            if ($life >= 0) {
                $_SESSION[getini('auth/prefix') . $key] = $val;
            } else {
                unset($_SESSION[getini('auth/prefix') . $key]);
            }
            $ret = true;
        } elseif ('COOKIE' == $type) {
            $life = $life > 0 ? $life + time() : 0;
            $secure = (443 == $_SERVER['SERVER_PORT']) ? 1 : 0;
            $key = self::getCookieKey($key);
            $val = $val ? self::authCode(json_encode($val), 'ENCODE') : '';
            $ret = setcookie($key, $val, $life, getini('auth/path'), getini('auth/domain'), $secure);
        } elseif ('REDIS' == $type) {
            $redis = DB::dbo('redis.user');
            $ret = $redis->set(getini('auth/prefix') . $key, $val, $life);
        }
        return $ret;
    }

    /**
     * @param $var
     * @param bool $prefix
     * @param null $key
     * @return string
     */
    public static function getCookieKey($var, $prefix = true, $key = null) {
        if ($prefix) {
            if (is_null($key)) {
                $var = getini('auth/prefix') . substr(md5(getini('auth/key')), -7, 7) . '_' . $var;
            } else {
                $var = getini('auth/prefix') . $key . '_' . $var;
            }
        }
        return $var;
    }


    /**
     * @param $string
     * @param string $operation
     * @param string $key
     * @param int $expiry
     * @return string
     */
    public static function authCode($string, $operation = 'DECODE', $key = '', $expiry = 0) {
        static $hash_auth = null;
        if (is_null($hash_auth)) {
            $hash_key = getini('auth/key') ?: PHP_VERSION;
            $hash_auth = md5($hash_key . PHP_VERSION);
        }
        $timestamp = time();
        $ckey_length = 4;
        $_key = md5($key ?: $hash_auth);
        $keya = md5(substr($_key, 0, 16));
        $keyb = md5(substr($_key, 16, 16));
        $keyc = $ckey_length ? ('DECODE' == $operation ? substr($string, 0, $ckey_length) : substr(md5(microtime()), -$ckey_length)) : '';

        $cryptkey = $keya . md5($keya . $keyc);
        $key_length = strlen($cryptkey);

        $_string = 'DECODE' == $operation ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + $timestamp : 0) . substr(md5($string . $keyb), 0, 16) . $string;
        $string_length = strlen($_string);

        $result = '';
        $box = range(0, 255);

        $rndkey = array();
        for ($i = 0; $i <= 255; $i++) {
            $rndkey[$i] = ord($cryptkey[$i % $key_length]);
        }

        for ($j = $i = 0; $i < 256; $i++) {
            $j = ($j + $box[$i] + $rndkey[$i]) % 256;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }

        for ($a = $j = $i = 0; $i < $string_length; $i++) {
            $a = ($a + 1) % 256;
            $j = ($j + $box[$a]) % 256;
            $tmp = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            $result .= chr(ord($_string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
        }

        if ('DECODE' == $operation) {
            if ((0 == substr($result, 0, 10) || substr($result, 0, 10) - $timestamp > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26) . $keyb), 0, 16)) {
                return substr($result, 26);
            }
            return '';
        }
        return $keyc . str_replace('=', '', base64_encode($result));
    }
}