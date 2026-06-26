<?php

namespace Xcs\Cache;

use Xcs\Traits\Singleton;

class Redis
{
    use Singleton;

    private array $_config = [];
    private ?\Redis $_link = null;

    public function __construct()
    {
        $this->connect();
    }

    public function connect(): void
    {
        if ($this->_link) {
            return;
        }

        $config = getini('cache/redis');
        $this->_config = $config;

        $this->_link = new \Redis();
        $connect = $this->_link->connect($config['host'], $config['port'], $config['timeout']);

        if ($config['password']) {
            $connect = $this->_link->auth($config['password']);
        }

        if ($connect) {
            $this->_link->select($config['database'] ?? 0);
        }
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed
    {
        $handKey = $this->_config['prefix'] . ':' . $key;
        $json = $this->_link->get($handKey);
        if ($json) {
            $data = json_decode($json, true);
            return $data['data'];
        }
        return null;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @return bool
     */
    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        $data = ['data' => $value, 'timeout' => $ttl];
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $handKey = $this->_config['prefix'] . ':' . $key;
        if ($ttl > 0) {
            $ret = $this->_link->set($handKey, $json, $ttl);
        } else {
            $ret = $this->_link->set($handKey, $json);
        }
        return (bool)$ret;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function rm(string $key): bool
    {
        $handKey = $this->_config['prefix'] . ':' . $key;
        $ret = $this->_link->del($handKey);
        return (bool)$ret;
    }

    /**
     * @param string $key
     * @param int $value
     * @return bool
     */
    public function incrBy(string $key, int $value): bool
    {
        $ret = $this->_link->incrBy($key, $value);
        return (bool)$ret;
    }

}
