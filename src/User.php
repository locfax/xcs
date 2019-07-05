<?php

namespace Xcs;

class User
{
    const _USERKEY = 'TATA';
    const _ROLEKEY = 'ROLE';

    /**
     * @param array $userData
     * @param null $rolesData
     * @param int $left
     * @return bool
     */
    public static function setUser(array $userData, $rolesData = null, $left = 0)
    {
        if (!is_null($rolesData)) {
            $userData[self::_ROLEKEY] = is_array($rolesData) ? implode(',', $rolesData) : $rolesData;
        }
        $datakey = getini('auth/prefix') . self::_USERKEY;
        $ret = self::_setData($datakey, $userData, $left);
        return $ret;
    }

    /**
     * @return array
     */
    public static function getUser()
    {
        $datakey = getini('auth/prefix') . self::_USERKEY;
        $ret = self::_getData($datakey);
        return $ret;
    }

    public static function clearUser()
    {
        $datakey = getini('auth/prefix') . self::_USERKEY;
        self::_setData($datakey, '', -86400 * 365);
    }

    /**
     * @return mixed|null
     */
    public static function getRoles()
    {
        $user = self::getUser();
        return isset($user[self::_ROLEKEY]) ? $user[self::_ROLEKEY] : null;
    }

    /**
     * @return array
     */
    public static function getRolesArray()
    {
        $roles = self::getRoles();
        if (empty($roles)) {
            return [];
        }
        if (!is_array($roles)) {
            $roles = explode(',', $roles);
        }
        return array_map('trim', $roles);
    }

    /**
     * @param $key
     * @param array $data
     * @param int $ttl
     * @return bool
     */
    public static function setData($key, array $data, $ttl = 0)
    {
        return self::_setData($key, $data, $ttl);
    }

    /**
     * @param string $key
     * @return array
     */
    public static function getData($key)
    {
        return self::_getData($key);
    }

    /**
     * @return null|string
     */
    public static function getSid()
    {
        $type = getini('auth/method');
        if ('SESSION' == $type) {
            if (PHP_SESSION_NONE == session_status()) {
                $handle = getini('auth/handle');
                if ($handle) {
                    (new Session())->start();
                }
                session_start();
            }
            return session_id();
        }
        return null;
    }

    /**
     * @param $sid
     * @param $data
     * @return bool
     */
    public static function setSession($sid, $data = null)
    {
        if (is_null($data)) {
            return false;
        }
        return self::_setData('sid:' . $sid, $data, 0, 'SESSID');
    }

    /**
     * @param $sid
     * @return array
     */
    public static function getSession($sid)
    {
        return self::_getData('sid:' . $sid, 'SESSID');
    }

    /**
     * @param string $key
     * @param string $type
     * @return array
     */
    private static function _getData($key, $type = null)
    {
        $ret = '';
        if (is_null($type)) {
            $type = getini('auth/method');
        }
        if ('SESSION' == $type) {
            if (PHP_SESSION_NONE == session_status()) {
                $handle = getini('auth/handle');
                if ($handle) {
                    (new Session())->start();
                }
                session_start();
            }
            $ret = isset($_SESSION[$key]) ? $_SESSION[$key] : null;
            if ($ret) {
                $ret = json_decode($ret, true);
            }
        } elseif ('COOKIE' == $type) {
            $key = self::getCookieKey($key);
            $ret = isset($_COOKIE[$key]) ? json_decode(self::authCode($_COOKIE[$key], 'DECODE'), true) : null;
        } elseif ('SESSID' == $type) {
            static $handle = null;
            if (is_null($handle)) {
                $config = Context::dsn('session');
                $handle = getini('auth/session');
                $handle = '\\Xcs\\Cache\\' . ucfirst($handle);
                $handle = $handle::getInstance()->init($config);
            }
            $ret = $handle->get($key);
            if ($ret) {
                $ret = json_decode($ret, true);
            }
        }
        return $ret;
    }

    /**
     * @param string $key
     * @param array $val
     * @param int $life
     * @param null $type
     * @return bool
     */
    private static function _setData($key, $val, $life = 0, $type = null)
    {
        $ret = false;
        if (is_null($type)) {
            $type = getini('auth/method');
        }
        if ('SESSION' == $type) {
            if (PHP_SESSION_NONE == session_status()) {
                $handle = getini('auth/handle');
                if ($handle) {
                    (new Session())->start();
                }
                session_start();
            }
            if ($life >= 0) {
                $_SESSION[$key] = json_encode($val);
            } else {
                unset($_SESSION[$key]);
            }
            $ret = true;
        } elseif ('COOKIE' == $type) {
            $life = $life > 0 ? $life + time() : 0;
            $secure = (443 == $_SERVER['SERVER_PORT']) ? 1 : 0;
            $key = self::getCookieKey($key);
            $val = $val ? self::authCode(json_encode($val), 'ENCODE') : '';
            $ret = setcookie($key, $val, $life, getini('auth/path'), getini('auth/domain'), $secure);
        } elseif ('SESSID' == $type) {
            static $handle = null;
            if (is_null($handle)) {
                $config = Context::dsn('session');
                $handle = getini('auth/session');
                $handle = '\\Xcs\\Cache\\' . ucfirst($handle);
                $handle = $handle::getInstance()->init($config);
            }
            $ret = $handle->set($key, json_encode($val), $life);
        }
        return $ret;
    }

    /**
     * @param $var
     * @param bool $prefix
     * @param null $key
     * @return string
     */
    public static function getCookieKey($var, $prefix = true, $key = null)
    {
        if ($prefix) {
            if (is_null($key)) {
                $var = substr(md5(getini('auth/key')), -7, 7) . '_' . $var;
            } else {
                $var = $key . '_' . $var;
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
    public static function authCode($string, $operation = 'DECODE', $key = '', $expiry = 0)
    {
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

        $rndkey = [];
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