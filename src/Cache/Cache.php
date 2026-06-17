<?php

namespace Xcs\Cache;

class Cache
{
    /**
     * @param string $key
     * @return mixed
     */
    public static function FileGet(string $key)
    {
        return \Xcs\Cache\File::getInstance()->get($key);
    }

    /**
     * @param string $kye
     * @param $value
     * @param $ttl
     * @return mixed
     */
    public static function FileSet(string $key, $value, $ttl = 0)
    {
        return \Xcs\Cache\File::getInstance()->set($key, $value, $ttl);
    }

    /**
     * @param string $key
     * @return mixed
     */
    public static function FileRm(string $key)
    {
        return \Xcs\Cache\File::getInstance()->rm($key);
    }

    /**
     * @param string $key
     * @return mixed
     */
    public static function get(string $key)
    {
        return \Xcs\Cache\Redis::getInstance()->get($key);
    }

    /**
     * @param string $key
     * @param array|string $value
     * @param int $ttl
     * @return mixed
     */
    public static function set(string $key, $value, int $ttl = 0)
    {
        return \Xcs\Cache\Redis::getInstance()->set($key, $value, $ttl);
    }

    /**
     * @param string $key
     * @return mixed
     */
    public static function rm(string $key)
    {
        return \Xcs\Cache\Redis::getInstance()->rm($key);
    }

    /**
     * @return mixed
     */
    public static function clear()
    {
        return \Xcs\Cache\Redis::getInstance()->clear();
    }

}
