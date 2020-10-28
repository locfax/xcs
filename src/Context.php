<?php

namespace Xcs;

class Context
{

    /**
     * @param string $dsnId
     * @return mixed|null
     */
    public static function dsn($dsnId = 'portal')
    {
        static $cacheDsn = [];
        $appKey = APP_KEY;
        if (!isset($cacheDsn[$appKey])) {
            $cacheDsn[$appKey] = App::mergeVars('dsn');
            if (!isset($cacheDsn[$appKey][$dsnId])) {
                new ExException("$dsnId is not setting 1");
                return null;
            }
        }
        //如果没配置$dsnid 会报错
        if (!isset($cacheDsn[$appKey][$dsnId])) {
            new ExException("$dsnId is not setting 2");
            return null;
        }
        return $cacheDsn[$appKey][$dsnId];
    }

    /**
     * @param $name
     * @param $type
     * @return bool|mixed
     */
    public static function config($name, $type = 'inc')
    {
        static $CacheConfig = [];
        $key = APP_KEY . '.' . $name . '.' . $type;
        if (isset($CacheConfig[$key])) {
            return $CacheConfig[$key];
        }
        $file = APP_ROOT . '/config/' . strtolower($name) . '.' . $type . '.php';
        if (!is_file($file)) {
            $CacheConfig[$key] = [];
            return [];
        }
        $CacheConfig[$key] = include $file;
        return $CacheConfig[$key];
    }

}
