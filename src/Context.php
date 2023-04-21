<?php

namespace Xcs;

class Context
{

    /**
     * @param string $dsnId
     * @return mixed
     */
    public static function dsn($dsnId = 'mysql')
    {
        static $cacheDsn = [];
        if (empty($cacheDsn)) {
            $cacheDsn = App::mergeVars('dsn');
            if (!isset($cacheDsn[$dsnId])) {
                throw new ExException('notice', "$dsnId is not setting");
            }
        }
        return $cacheDsn[$dsnId];
    }

    /**
     * @param $name
     * @param null $var
     * @param string $type
     * @return mixed
     */
    public static function config($name, $var = null, $type = 'inc')
    {
        static $CacheConfig = [];
        $key = $name . '.' . $type;
        if (isset($CacheConfig[$key])) {
            return $CacheConfig[$key];
        }
        $file = APP_ROOT . '/config/' . strtolower($name) . '.' . $type . '.php';
        if (!is_file($file)) {
            throw new ExException('notice', "$name.inc.php is not exists");
        }
        $CacheConfig[$key] = include $file;

        if (is_null($var)) {
            return $CacheConfig[$key];
        }
        return $CacheConfig[$key][$var];
    }

}
