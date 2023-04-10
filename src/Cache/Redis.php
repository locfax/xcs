<?php

namespace Xcs\Cache;

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
     */
    public function init($config)
    {
        $this->_link = new \Redis();
        $connect = $this->_link->connect($config['host'], $config['port'], $config['timeout']);
        if ($connect && $config['password']) {
            $connect = $this->_link->auth($config['password']);
        }
        if ($connect) {
            $this->_link->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);
            $this->enable = true;
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
    public function set($key, $value, $ttl = 0)
    {
        $ret = $this->_link->set($key, $value);
        if ($ttl > 0) {
            $this->_link->expire($key, $ttl);
        }
        return $ret;
    }

    /**
     * @return bool
     */
    public function expire()
    {
        return false;
    }

    /**
     * @param $key
     * @return int|\Redis
     */
    public function rm($key)
    {
        return $this->_link->del($key);
    }

    public function clear()
    {
        $this->_link->flushDB();
    }

}
