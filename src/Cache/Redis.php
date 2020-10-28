<?php

namespace Xcs\Cache;

use Xcs\ExException;
use Xcs\Traits\Singleton;

class Redis
{

    use Singleton;

    public $enable = false;
    private $_link = null;

    /**
     * @param $config
     * @return $this
     * @throws ExException
     */
    public function init($config)
    {
        try {
            $this->_link = new \Redis();
            $connect = $this->_link->connect($config['host'], $config['port'], $config['timeout']);
            if ($connect && $config['password']) {
                $connect = $this->_link->auth($config['password']);
            }
            if ($connect) {
                $this->_link->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);
                $this->enable = true;
            }
        } catch (\RedisException $ex) {
            throw new ExException('redis初始化错误');
        }
        return $this;
    }

    public function close()
    {
        $this->_link && $this->_link->close();
    }

    /**
     * @param $key
     * @return bool
     */
    public function get($key)
    {
        try {
            return $this->_link->get($key);
        } catch (\RedisException $e) {
            return false;
        }
    }

    /**
     * @param $key
     * @param $value
     * @param int $ttl
     * @return bool
     */
    public function set($key, $value, $ttl = 0)
    {
        try {
            $ret = $this->_link->set($key, $value);
            if ($ttl > 0) {
                $this->_link->expire($key, $ttl);
            }
            return $ret;
        } catch (\RedisException $e) {
            return false;
        }
    }

    /**
     * @param $key
     * @param int $ttl
     * @return bool
     */
    public function expire($key, $ttl = 0)
    {
        return false;
    }

    /**
     * @param $key
     * @return bool
     */
    public function rm($key)
    {
        try {
            return $this->_link->delete($key);
        } catch (\RedisException $e) {
            return false;
        }
    }

    public function clear()
    {
        try {
            return $this->_link->flushDB();
        } catch (\RedisException $e) {
            return false;
        }
    }

}
