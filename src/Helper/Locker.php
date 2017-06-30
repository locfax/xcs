<?php

namespace Xcs\Helper;

class Locker {

    const dsn = 'general';

    /**
     * 进度加锁
     * @param $process
     * @param int $ttl
     * @return bool
     */
    public static function islocked($process, $ttl = 0) {
        $_ttl = $ttl < 1 ? 600 : intval($ttl);
        if (self::status('get', $process)) {
            return true;
        }
        return self::trylock($process, $_ttl);
    }

    /**
     * 进度解锁
     * @param $process
     */
    public static function unlock($process) {
        self::status('rm', $process);
        self::cmd('rm', $process);
    }

    /**
     * 锁状态设置
     * @param $action
     * @param $process
     * @return bool
     */
    public static function status($action, $process) {
        static $plist = array();
        switch ($action) {
            case 'set' :
                $plist[$process] = true;
                break;
            case 'get' :
                return isset($plist[$process]);
            case 'rm' :
                $plist[$process] = null;
                break;
        }
        return true;
    }

    /**
     * 尝试加锁
     * @param $name
     * @param $ttl
     * @return bool
     */
    private static function trylock($name, $ttl) {
        if (!self::cmd('get', $name)) {
            self::cmd('set', $name, $ttl);
            $ret = false;
        } else {
            $ret = true;
        }
        self::status('set', $name);
        return $ret;
    }

    /**
     * 加锁操作
     * @param $cmd
     * @param $name
     * @param int $ttl
     * @return bool|string
     */
    private static function cmd($cmd, $name, $ttl = 0) {
        if ('file' == getini('cache/cacher')) {
            return self::dblock($cmd, $name, $ttl);
        }
        return \Xcs\Context::cache($cmd, 'process_' . $name, time(), $ttl);
    }

    /**
     * @param $cmd
     * @param $name
     * @param int $ttl
     * @return bool|string
     */
    private static function dblock($cmd, $name, $ttl = 0) {
        $ret = '';
        $db = \Xcs\DB::dbo(self::dsn);
        switch ($cmd) {
            case 'set':
                $ret = $db->replace('common_process', array('processid' => $name, 'expiry' => time() + $ttl));
                break;
            case 'get':
                $ret = $db->findOne('common_process', '*', array('processid' => $name));
                if (empty($ret) || $ret['expiry'] < time()) {
                    $ret = false;
                } else {
                    $ret = true;
                }
                break;
            case 'rm':
                $ret = $db->remove('common_process', "processid='$name' OR expiry < " . time());
                break;
        }
        return $ret;
    }

}
