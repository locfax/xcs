<?php

namespace Xcs;

class UID
{
    const _UREY = 'ur';
    const _ROLEY = 'ro';

    /**
     * @param array $userData
     * @param array $rolesData
     * @param int $life
     * @return bool
     */
    public static function setUser(array $userData, $rolesData = null, $life = 0)
    {
        if (!is_null($rolesData)) {
            $userData[self::_ROLEY] = is_array($rolesData) ? implode(',', $rolesData) : $rolesData;
        }
        $dataKey = getini('auth/prefix') . self::_UREY;
        return self::_setData($dataKey, $userData, $life);
    }

    /**
     * @return mixed
     */
    public static function getUser()
    {
        $dataKey = getini('auth/prefix') . self::_UREY;
        return self::_getData($dataKey);
    }

    /**
     * @return void
     */
    public static function clearUser()
    {
        $dataKey = getini('auth/prefix') . self::_UREY;
        self::_setData($dataKey, null, -86400 * 365);
    }

    /**
     * @return mixed|null
     */
    public static function getRoles()
    {
        $data = self::getUser();
        return isset($data[self::_ROLEY]) ? $data[self::_ROLEY] : null;
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
     * @param null $type
     * @return bool
     */
    public static function setData($key, array $data, $ttl = 0, $type = null)
    {
        return self::_setData('data:' . $key, $data, $ttl, $type);
    }

    /**
     * @param string $key
     * @param null $type
     * @return mixed
     */
    public static function getData($key, $type = null)
    {
        return self::_getData('data:' . $key, $type);
    }

    /**
     * @param string $key
     * @param string|null $type
     * @return mixed
     */
    private static function _getData($key, $type = null)
    {
        $ret = [];
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
        } elseif ('COOKIE' == $type) {
            $key = self::getCookieKey($key);
            $ret = isset($_COOKIE[$key]) ? json_decode(self::authCode($_COOKIE[$key]), true) : null;
        }
        return $ret;
    }

    /**
     * @param string $key
     * @param mixed $val
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
                $_SESSION[$key] = $val;
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
                $var = substr(md5(getini('auth/key')), -7) . '_' . $var;
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
        $cKey_length = 4;
        $_key = md5($key ?: $hash_auth);
        $keya = md5(substr($_key, 0, 16));
        $keyb = md5(substr($_key, 16, 16));
        $keyc = 'DECODE' == $operation ? substr($string, 0, $cKey_length) : substr(md5(microtime()), -$cKey_length);

        $cryptKey = $keya . md5($keya . $keyc);
        $key_length = strlen($cryptKey);

        $_string = 'DECODE' == $operation ? base64_decode(substr($string, $cKey_length)) : sprintf('%010d', $expiry ? $expiry + $timestamp : 0) . substr(md5($string . $keyb), 0, 16) . $string;
        $string_length = strlen($_string);

        $result = '';
        $box = range(0, 255);

        $rndKey = [];
        for ($i = 0; $i <= 255; $i++) {
            $rndKey[$i] = ord($cryptKey[$i % $key_length]);
        }

        for ($j = $i = 0; $i < 256; $i++) {
            $j = ($j + $box[$i] + $rndKey[$i]) % 256;
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