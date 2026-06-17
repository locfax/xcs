<?php

namespace Xcs;

class Context
{

    /**
     * @param string $dsnId
     * @return mixed
     * @throws ExException
     */
    public static function dsn(string $dsnId = 'mysql')
    {
        static $cacheDsn = [];
        if (empty($cacheDsn)) {
            $cacheDsn = App::mergeVars('dsn');
            if (!isset($cacheDsn[$dsnId])) {
                throw new ExException($dsnId . ' is not setting');
            }
        }
        return $cacheDsn[$dsnId];
    }

    /**
     * @param string $name
     * @param null $var
     * @param string $type
     * @return mixed
     * @throws ExException
     */
    public static function config(string $name, $var = null, string $type = 'inc')
    {
        static $CacheConfig = [];
        $key = $name . '.' . $type;
        if (isset($CacheConfig[$key])) {
            return $CacheConfig[$key];
        }
        $file = sprintf('%s/config/%s.%s.php', APP_ROOT, strtolower($name), $type);
        if (!is_file($file)) {
            throw new ExException($name . '.' . $type . '.php is not exists');
        }
        $CacheConfig[$key] = include $file;

        if (is_null($var)) {
            return $CacheConfig[$key];
        }
        return $CacheConfig[$key][$var];
    }

}
