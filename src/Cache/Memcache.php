<?php

namespace Xcs\Cache;

use MemcachedException;
use Xcs\ExException;
use Xcs\Traits\Singleton;

class Memcache
{

    use Singleton;

    public bool $enable = false;
    /**
     * @var \Memcache
     */
    private \Memcache $_link;

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
        } catch (MemcachedException $ex) {
            throw new ExException('memcache初始化错误');
        }
        return $this;
    }

    public function link()
    {
        return $this->_link;
    }

    public function close()
    {
        $this->_link->close();
    }

    /**
     * @param string $key
     * @return array|false|string
     */
    public function get(string $key)
    {
        try {
            return $this->_link->get($key);
        } catch (MemcachedException $ex) {
            return false;
        }
    }

    /**
     * @param string $key
     * @param array|string $value
     * @param int $ttl
     * @return bool
     */
    public function set(string $key, $value, int $ttl = 0)
    {
        try {
            $data = $this->get($key);
            if ($data) {
                return $this->_link->set($key, $value, MEMCACHE_COMPRESSED, $ttl);
            } else {
                return $this->_link->add($key, $value, MEMCACHE_COMPRESSED, $ttl);
            }
        } catch (MemcachedException $ex) {
            return false;
        }
    }

    /**
     * @return bool
     */
    public function expire()
    {
        return false;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function rm(string $key)
    {
        try {
            return $this->_link->delete($key);
        } catch (MemcachedException $ex) {
            return false;
        }
    }

    public function clear()
    {
        return $this->_link->flush();
    }

}
