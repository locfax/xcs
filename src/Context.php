<?php

namespace Xcs;

class Context {

    private static $_dsns = array();
    private static $_configs = array();

    /**
     * @param $dsnid
     * @return mixed
     */
    public static function dsn($dsnid) {
        if (!isset(self::$_dsns[APPKEY])) {
            $dsns = App::mergeVars('dsn');
            foreach ($dsns as $key => $dsn) {
                $dsns[$key]['dsnkey'] = md5(APPKEY . '_' . $key . '_' . $dsn['driver'] . '_' . $dsn['dsn']); //连接池key
            }
            self::$_dsns[APPKEY] = $dsns;
            if (!isset(self::$_dsns[APPKEY][$dsnid])) {
                self::$_dsns[APPKEY][$dsnid] = array();
            }
            $dsns = null;
        }
        //如果没配置$dsnid 会报错
        return self::$_dsns[APPKEY][$dsnid];
    }

    /**
     * @param $name
     * @param $type
     * @return bool|mixed
     */
    public static function config($name, $type = 'inc') {
        $key = APPKEY . '.' . $name . '.' . $type;
        if (isset(self::$_configs[$key])) {
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

    /**
     * @param string $data
     * @param int $code
     */
    public static function log($data, $code = 0) {
        $logfile = DATAPATH . 'log/run.log';
        Helper\Log::writeLog($logfile, $data);
    }
}
