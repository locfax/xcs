<?php

namespace Xcs;

class Context {

    /**
     * @param $dsnid
     * @return mixed
     */
    public static function dsn($dsnid) {
        static $_dsns = array();
        $appkey = APPKEY;
        if (!isset($_dsns[$appkey])) {
            $dsns = App::mergeVars('dsn');
            foreach ($dsns as $key => $dsn) {
                $dsns[$key]['dsnkey'] = md5($appkey . '_' . $key . '_' . $dsn['driver'] . '_' . $dsn['dsn']); //连接池key
            }
            $_dsns[$appkey] = $dsns;
            if (!isset($_dsns[$appkey][$dsnid])) {
                $_dsns[$appkey][$dsnid] = array();
            }
            $dsns = null;
        }
        //如果没配置$dsnid 会报错
        return $_dsns[$appkey][$dsnid];
    }

    /**
     * @param $name
     * @param $type
     * @return bool|mixed
     */
    public static function config($name, $type = 'inc') {
        static $_configs = array();
        $key = APPKEY . '.' . $name . '.' . $type;
        if (isset($_configs[$key])) {
            return $_configs[$key];
        }
        $file = PSROOT . '/config/' . strtolower($name) . '.' . $type . '.php';
        if (!is_file($file)) {
            $_configs[$key] = array();
            return array();
        }
        $_configs[$key] = include $file;
        return $_configs[$key];
    }

}
