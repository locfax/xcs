<?php

namespace Xcs\Helper;

use Xcs\Cache\Cache;

class Locker
{

    /**
     * 进度加锁
     * @param string $process
     * @param int $ttl
     * @return bool
     */
    public static function isLocked(string $process, int $ttl = 0): bool
    {
        $_ttl = $ttl < 1 ? 600 : $ttl;
        if (self::status('get', $process)) {
            return true;
        }
        return self::tryLock($process, $_ttl);
    }

    /**
     * 进度解锁
     * @param string $process
     */
    public static function unLock(string $process)
    {
        self::status('rm', $process);
        self::cmd('rm', $process);
    }

    /**
     * 锁状态设置
     * @param string $action
     * @param string $process
     * @return bool
     */
    public static function status(string $action, string $process): bool
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
     * @param string $name
     * @param int $ttl
     * @return bool
     */
    private static function tryLock(string $name, int $ttl): bool
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
     * @param string $cmd
     * @param string $name
     * @param int $ttl
     * @return bool|string
     */
    private static function cmd(string $cmd, string $name, int $ttl = 0)
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
