<?php

namespace Xcs\Cache;

use MemcachedException;
use Xcs\ExException;
use Xcs\Traits\Singleton;

class Memcached
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
            $json = $this->_link->get($key);
            if ($json) {
                $data = json_decode($json, true);
                return $data['data'];
            }
            return null;
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
            $data = ['data' => $value, 'timeout' => $ttl];
            $json = json_encode($data, JSON_UNESCAPED_UNICODE);
            return $this->_link->set($key, $json, MEMCACHE_COMPRESSED, $ttl);
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
