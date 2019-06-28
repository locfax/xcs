<?php

namespace Xcs;

class Context
{

    /**
     * @param $dsnId
     * @return mixed
     */
    public static function dsn($dsnId)
    {
        static $_dsns = [];
        $appKey = APPKEY;
        if (!isset($_dsns[$appKey])) {
            $dsns = App::mergeVars('dsn');
            foreach ($dsns as $key => $dsn) {
                $dsns[$key]['dsnkey'] = md5($appKey . '_' . $key . '_' . $dsn['driver'] . '_' . $dsn['dsn']); //连接池key
            }
            $_dsns[$appKey] = $dsns;
            if (!isset($_dsns[$appKey][$dsnId])) {
                $_dsns[$appKey][$dsnId] = [];
            }
            $dsns = null;
        }
        //如果没配置$dsnid 会报错
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
        $key = APPKEY . '.' . $name . '.' . $type;
        if (isset($_configs[$key])) {
            return $_configs[$key];
        }
        $file = PSROOT . '/config/' . strtolower($name) . '.' . $type . '.php';
        if (!is_file($file)) {
            $_configs[$key] = [];
            return [];
        }
        $_configs[$key] = include $file;
        return $_configs[$key];
    }

}
