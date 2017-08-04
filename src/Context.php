<?php

namespace Xcs;

class Context {

    /**
     * @param $dsnid
     * @return mixed
     */
    public static function dsn($dsnid) {
        static $_dsns = array();
        if (!isset($_dsns[APPKEY])) {
            $dsns = App::mergeVars('dsn');
            foreach ($dsns as $key => $dsn) {
                $dsns[$key]['dsnkey'] = md5(APPKEY . '_' . $key . '_' . $dsn['driver'] . '_' . $dsn['dsn']); //连接池key
            }
            $_dsns[APPKEY] = $dsns;
            if (!isset($_dsns[APPKEY][$dsnid])) {
                $_dsns[APPKEY][$dsnid] = array();
            }
            $dsns = null;
        }
        //如果没配置$dsnid 会报错
        return $_dsns[APPKEY][$dsnid];
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
            return self::$_configs[$key];
        }
        $file = PSROOT . '/config/' . strtolower($name) . '.' . $type . '.php';
        if (!is_file($file)) {
            self::$_configs[$key] = array();
            return array();
        }
        self::$_configs[$key] = include $file;
        return self::$_configs[$key];
    }

}
