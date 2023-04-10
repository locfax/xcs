<?php

namespace Xcs;

class Context
{

    /**
     * @param string $dsnId
     * @return mixed
     */
    public static function dsn($dsnId = 'default')
    {
        static $cacheDsn = [];
        if (empty($cacheDsn)) {
            $cacheDsn = App::mergeVars('dsn');
            if (!isset($cacheDsn[$dsnId])) {
                new ExException("$dsnId is not setting");
                return null;
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
            new ExException("$name.inc.php is not exists");
            return [];
        }
        $CacheConfig[$key] = include $file;

        if (is_null($var)) {
            return $CacheConfig[$key];
        }
        return $CacheConfig[$key][$var];
    }

}
