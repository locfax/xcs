<?php

namespace Xcs\Cache;

use Xcs\ExException;
use Xcs\Traits\Singleton;

class Memcache
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
            $this->_link = new \Memcache();
            $connect = $this->_link->connect($config['host'], $config['port'], $config['timeout']);
            if ($connect) {
                $this->enable = true;
            }
        } catch (\MemcachedException $e) {
            throw new ExException('memcache初始化错误');
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
        } catch (\MemcachedException $e) {
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
            $data = $this->get($key);
            if ($data) {
                return $this->_link->set($key, $value, MEMCACHE_COMPRESSED, $ttl);
            } else {
                return $this->_link->add($key, $value, MEMCACHE_COMPRESSED, $ttl);
            }
        } catch (\MemcachedException $e) {
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
        } catch (\MemcachedException $e) {
            return false;
        }
    }

    public function clear()
    {
        return $this->_link->flush();
    }

}
