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
    private \Memcached $_link;

    /**
     * @param $config
     * @return $this
     * @throws ExException
     */
    public function init($config)
    {
        try {
            $this->_link = new \Memcached;
            if ($config['timeout'] > 0) {
                $this->_link->setOption(\Memcached::OPT_CONNECT_TIMEOUT, $config['timeout'] * 1000);
            }
            $hosts = explode(',', $config['host']);
            $servers = [];
            foreach ($hosts as $host) {
                $servers[] = [$host, $config['port'], 1];
            }
            $this->_link->addServers($servers);
            if (!empty($config['username'])) {
                $this->_link->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);
                $this->_link->setSaslAuthData($config['username'], $config['password']);
            }
            $this->enable = true;

        } catch (MemcachedException $ex) {
            throw new ExException('memcache初始化错误');
        }
        return $this;
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
        if ($ttl > 0) {
            $timeout = time() + $ttl;
        } else {
            $timeout = 0;
        }

        try {
            $data = ['data' => $value, 'timeout' => $ttl];
            $json = json_encode($data, JSON_UNESCAPED_UNICODE);
            return $this->_link->set($key, $json, $timeout);
        } catch (MemcachedException $ex) {
            return false;
        }
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
