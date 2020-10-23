<?php

namespace Xcs;

use Xcs\Exception\ExException;

class Context
{

    /**
     * @param string $dsnId
     * @return mixed|null
     * @throws ExException
     */
    public static function dsn($dsnId = 'portal')
    {
        static $_dsns = [];
        $appKey = APP_KEY;
        if (!isset($_dsns[$appKey])) {
            $_dsns[$appKey] = App::mergeVars('dsn');
            if (!isset($_dsns[$appKey][$dsnId])) {
                throw new ExException("$dsnId is not setting 1");
            }
        }
        //如果没配置$dsnid 会报错
        if (!isset($_dsns[$appKey][$dsnId])) {
            throw new ExException("$dsnId is not setting 2");
        }
        return $_dsns[$appKey][$dsnId];
    }

    /**
     * @param $name
     * @param $type
     * @return bool|mixed
     */
    public static function config($name, $type = 'inc')
    {
        static $_configs = [];
        $key = APP_KEY . '.' . $name . '.' . $type;
        if (isset($_configs[$key])) {
            return $_configs[$key];
        }
        $file = APP_ROOT . '/config/' . strtolower($name) . '.' . $type . '.php';
        if (!is_file($file)) {
            $_configs[$key] = [];
            return [];
        }
        $_configs[$key] = include $file;
        return $_configs[$key];
    }

}
