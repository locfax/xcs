<?php

namespace Xcs;

class Context
{

    /**
     * @param string $dsnId
     * @return array
     * @throws ExException
     */
    public static function dsn(string $dsnId = 'mysql'): array
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
     * @param string $var
     * @param string $type
     * @return mixed
     * @throws ExException
     */
    public static function config(string $name, string $var = '', string $type = 'inc'): mixed
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

        if (empty($var)) {
            return $CacheConfig[$key];
        }
        return $CacheConfig[$key][$var];
    }

}
