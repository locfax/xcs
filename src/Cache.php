<?php

namespace Xcs;

class Cache
{

    /**
     * @param string $key
     * @return mixed
     */
    public static function get($key = '')
    {
        $cacher = Cache\Cacher::getInstance();
        return $cacher->get($key);
    }

    /**
     * @param string $key
     * @param string $val
     * @param int $ttl
     * @return mixed
     */
    public static function set($key = '', $val = '', $ttl = 0)
    {
        $cacher = Cache\Cacher::getInstance();
        return $cacher->set($key, $val, $ttl);
    }

    /**
     * @param string $key
     * @return mixed
     */
    public static function rm($key = '')
    {
        $cacher = Cache\Cacher::getInstance();
        return $cacher->rm($key);
    }

    /**
     * @return mixed
     */
    public static function clear()
    {
        $cacher = Cache\Cacher::getInstance();
        return $cacher->clear();
    }

    /**
     * @return mixed
     */
    public static function close()
    {
        $cacher = Cache\Cacher::getInstance();
        return $cacher->close();
    }
}