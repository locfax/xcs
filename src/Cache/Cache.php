<?php

namespace Xcs\Cache;

use Xcs\Context;
use Xcs\ExException;
use Xcs\Traits\Singleton;

class Cache
{

    use Singleton;

    private $prefix;
    private $handle;
    public bool $enable = false;

    /**
     * @param $handle
     * @throws ExException
     */
    public function __construct()
    {
        $config = getini('cache');
        $this->prefix = $config['prefix'];
        $handle = $config['handle'];
        if (empty($config[$handle])) {
            throw new ExException('缓存器' . $handle . '配置没找到');
        }
        if ($handle == 'memcached') {
            $this->handle = \Xcs\Cache\Memcached::getInstance()->init($config[$handle]);
            $this->enable = $this->handle->enable;
        } elseif ($handle == 'redis') {
            $this->handle = \Xcs\Cache\Redis::getInstance()->init($config[$handle]);
            $this->enable = $this->handle->enable;
        } else {
            throw new ExException('缓存器必须是 redis memcached');
        }
    }

    /**
     * @param string $key
     * @return mixed
     */
    public static function FileGet(string $key)
    {
        $config = getini('cache');
        return \Xcs\Cache\File::getInstance()->get($config['prefix'] . '_' . $key);
    }

    /**
     * @param string $kye
     * @param $value
     * @param $ttl
     * @return mixed
     */
    public static function FileSet(string $key, $value, $ttl = 0)
    {
        $config = getini('cache');
        return \Xcs\Cache\File::getInstance()->set($config['prefix'] . '_' . $key, $value, $ttl);
    }

    /**
     * @param string $key
     * @return mixed
     */
    public static function get(string $key)
    {
        return self::getInstance()->_get($key);
    }

    /**
     * @param string $key
     * @param array|string $value
     * @param int $ttl
     * @return mixed
     */
    public static function set(string $key, $value, int $ttl = 0)
    {
        return self::getInstance()->_set($key, $value, $ttl);
    }

    /**
     * @param string $key
     * @return mixed
     */
    public static function rm(string $key)
    {
        return self::getInstance()->_rm($key);
    }

    /**
     * @return mixed
     */
    public static function clear()
    {
        return self::getInstance()->_clear();
    }

    /**
     * @param string $key
     * @return mixed
     */
    private function _get(string $key)
    {
        if (!$this->enable) {
            return null;
        }

        $data = $this->handle->get($this->_key($key));
        if (!$data) {
            return null;
        }
        return $data;
    }

    /**
     * @param string $key
     * @param array|string $value
     * @param int $ttl
     * @return bool
     */
    private function _set(string $key, $value, int $ttl = 0): bool
    {
        $ret = false;
        if ($this->enable) {
            $ret = $this->handle->set($this->_key($key), $value, $ttl);
        }
        return $ret;
    }

    /**
     * @param $key
     * @return bool
     */
    private function _rm($key): bool
    {
        $ret = false;
        if ($this->enable) {
            $ret = $this->handle->rm($this->_key($key));
        }
        return $ret;
    }

    /**
     * @return bool
     */
    private function _clear(): bool
    {
        $ret = false;
        if ($this->enable) {
            $ret = $this->handle->clear();
        }
        return $ret;
    }

    /**
     * @param $str
     * @return string
     */
    private function _key($str): string
    {
        return $this->prefix . '_' . md5($str);
    }

}
