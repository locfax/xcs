<?php

namespace Xcs\Cache;

class Cache
{
    /**
     * @param string $key
     * @param bool $isPath
     * @return mixed
     */
    public static function FileGet(string $key, bool $isPath = false): mixed
    {
        return File::getInstance()->get($key, $isPath);
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @param bool $isPath
     * @return bool
     */
    public static function FileSet(string $key, mixed $value, int $ttl = 0, bool $isPath = false): bool
    {
        return File::getInstance()->set($key, $value, $ttl, $isPath);
    }

    /**
     * @param string $key
     * @return bool
     */
    public static function FileRm(string $key): bool
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
     * @return bool
     */
    public static function set(string $key, mixed $value, int $ttl = 0): bool
    {
        return Redis::getInstance()->set($key, $value, $ttl);
    }

    /**
     * @param string $key
     * @return bool
     */
    public static function rm(string $key): bool
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
