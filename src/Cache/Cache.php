<?php

namespace Xcs\Cache;

class Cache
{
    /**
     * @param string $key
     * @return mixed
     */
    public static function FileGet(string $key, $isPath = false): mixed
    {
        return File::getInstance()->get($key, $isPath);
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @return mixed
     */
    public static function FileSet(string $key, mixed $value, int $ttl = 0, $isPath = false): mixed
    {
        return File::getInstance()->set($key, $value, $ttl, $isPath);
    }

    /**
     * @param string $key
     * @return mixed
     */
    public static function FileRm(string $key): mixed
    {
        return File::getInstance()->rm($key);
    }

    /**
     * @param string $key
     * @return mixed
     */
    public static function get(string $key): mixed
    {
        return Redis::getInstance()->get($key);
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @return mixed
     */
    public static function set(string $key, mixed $value, int $ttl = 0): mixed
    {
        return Redis::getInstance()->set($key, $value, $ttl);
    }

    /**
     * @param string $key
     * @return mixed
     */
    public static function rm(string $key): mixed
    {
        return Redis::getInstance()->rm($key);
    }

    /**
     * @return bool
     */
    public static function clear(): bool
    {
        return Redis::getInstance()->clear();
    }

}
