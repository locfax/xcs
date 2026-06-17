<?php

namespace Xcs;

class User
{
    const UREY = '__uk';
    const ROLEY = '__ur';

    /**
     * @param array $userData
     * @param string $rolesData
     * @param int $life
     * @return bool
     */
    public static function set(array $userData, string $rolesData = '', int $life = 0): bool
    {
        if ($rolesData) {
            $userData[self::ROLEY] = $rolesData;
        }
        $dataKey = getini('auth/prefix') . self::UREY;
        return self::setData($dataKey, $userData, $life);
    }

    /**
     * @return mixed
     */
    public static function get()
    {
        $dataKey = getini('auth/prefix') . self::UREY;
        return self::getData($dataKey);
    }

    /**
     * @param string $token
     * @return bool
     */
    public static function setToken(string $token): bool
    {
        $dataKey = getini('auth/prefix') . self::UREY;
        return self::setData($dataKey, ['token' => $token], 0);
    }

    /**
     * @return string
     */
    public static function getToken(): string
    {
        $dataKey = getini('auth/prefix') . self::UREY;
        $data = self::getData($dataKey);
        return $data['token'] ?? '';
    }

    /**
     * @return void
     */
    public static function clear(): void
    {
        $dataKey = getini('auth/prefix') . self::UREY;
        self::setData($dataKey, [], -86400 * 365);
    }

    /**
     * @return mixed
     */
    public static function getRole(): mixed
    {
        $data = self::get();
        return $data[self::ROLEY] ?? null;
    }

    /**
     * @return array
     */
    public static function getRoleArray(): array
    {
        $roles = self::getRole();
        if (empty($roles)) {
            return [];
        }
        if (!is_array($roles)) {
            $roles = explode(',', $roles);
        }
        return array_map('trim', $roles);
    }

    /**
     * @param string $key
     * @return array
     */
    private static function getData(string $key): array
    {
        $ret = [];
        $type = getini('auth/method');
        if (!$type) {
            throw new \Error('auth method is empty');
        }

        if ('SESSION' == $type) {
            if (PHP_SESSION_NONE == session_status()) {
                session_start();
            }
            $ret = $_SESSION[$key] ?? [];
        } elseif ('COOKIE' == $type) {
            $key = self::getCookieKey($key);
            $ret = isset($_COOKIE[$key]) ? json_decode(self::authCode($_COOKIE[$key]), true) : [];
        }
        return $ret;
    }

    /**
     * @param string $key
     * @param array $val
     * @param int $life
     * @return bool
     */
    private static function setData(string $key, array $val, int $life = 0): bool
    {
        $ret = false;
        $type = getini('auth/method');
        if (!$type) {
            throw new \Error('auth method is empty');
        }

        if ('SESSION' == $type) {
            if (PHP_SESSION_NONE == session_status()) {
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
            $val = self::authCode(json_encode($val, JSON_UNESCAPED_UNICODE), 'ENCODE');

            $ret = setcookie($key, $val, $life, getini('auth/path'), getini('auth/domain'), $secure);
        }
        return $ret;
    }

    /**
     * @param string $var
     * @return string
     */
    private static function getCookieKey(string $var): string
    {
        return substr(md5(getini('auth/key')), -7) . '_' . $var;
    }

    /**
     * @param string $string
     * @param string $operation
     * @param string $key
     * @param int $expiry
     * @return string
     */
    public static function authCode(string $string, string $operation = 'DECODE', string $key = '', int $expiry = 0): string
    {
        static $hash_auth = null;
        if (is_null($hash_auth)) {
            $hash_key = getini('auth/key') ?: PHP_VERSION;
            $hash_auth = md5($hash_key . PHP_VERSION);
        }
        $timestamp = time();
        $cKey_length = 4;
        $_key = md5($key ?: $hash_auth);
        $key_a = md5(substr($_key, 0, 16));
        $key_b = md5(substr($_key, 16, 16));
        $key_c = 'DECODE' == $operation ? substr($string, 0, $cKey_length) : substr(md5(microtime()), -$cKey_length);

        $cryptKey = $key_a . md5($key_a . $key_c);
        $key_length = strlen($cryptKey);

        $_string = 'DECODE' == $operation ? base64_decode(substr($string, $cKey_length)) : sprintf('%010d', $expiry ? $expiry + $timestamp : 0) . substr(md5($string . $key_b), 0, 16) . $string;
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
            if (('0' == substr($result, 0, 10) || intval(substr($result, 0, 10)) - $timestamp > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26) . $key_b), 0, 16)) {
                return substr($result, 26);
            }
            return '';
        }

        return $key_c . str_replace('=', '', base64_encode($result));
    }
}