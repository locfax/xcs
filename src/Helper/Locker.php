<?php

namespace Xcs\Helper;

use Xcs\Cache\Cache;

class Locker
{

    /**
     * 进度加锁
     * @param $process
     * @param int $ttl
     * @return bool
     */
    public static function isLocked($process, int $ttl = 0): bool
    {
        $_ttl = $ttl < 1 ? 600 : intval($ttl);
        if (self::status('get', $process)) {
            return true;
        }
        return self::tryLock($process, $_ttl);
    }

    /**
     * 进度解锁
     * @param $process
     */
    public static function unLock($process)
    {
        self::status('rm', $process);
        self::cmd('rm', $process);
    }

    /**
     * 锁状态设置
     * @param $action
     * @param $process
     * @return bool
     */
    public static function status($action, $process): bool
    {
        static $plist = [];
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
    private static function tryLock($name, $ttl): bool
    {
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
    private static function cmd($cmd, $name, int $ttl = 0)
    {
        $ret = false;
        switch ($cmd) {
            case 'set':
                $ret = Cache::set('process_' . $name, time(), $ttl);
                break;
            case 'get':
                $ret = Cache::get('process_' . $name);
                break;
            case 'rm':
                $ret = Cache::rm('process_' . $name);
                break;
        }
        return $ret;
    }

}
