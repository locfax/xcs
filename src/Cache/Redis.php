<?php

namespace Xcs\Cache;

use Xcs\ExException;
use Xcs\Traits\Singleton;

class Redis
{

    use Singleton;

    public $enable = false;
    /**
     * @var \Redis
     */
    private $_link = null;

    /**
     * @param $config
     * @return $this
     * @throws ExException
     */
    public function init($config): Redis
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
        $this->_link && $this->_link = null;
    }

    /**
     * @param $key
     * @return mixed
     */
    public function get($key)
    {
        return $this->_link->get($key);
    }

    /**
     * @param $key
     * @param $value
     * @param int $ttl
     * @return mixed
     */
    public function set($key, $value, int $ttl = 0): bool
    {
        $ret = $this->_link->set($key, $value);
        if ($ttl > 0) {
            $this->_link->expire($key, $ttl);
        }
        return $ret;
    }

    /**
     * @param $key
     * @param int $ttl
     * @return bool
     */
    public function expire($key, int $ttl = 0): bool
    {
        return false;
    }

    /**
     * @param $key
     * @return mixed
     */
    public function rm($key): bool
    {
        return $this->_link->del($key);
    }

    public function clear()
    {
        return $this->_link->flushDB();
    }

}
