<?php

namespace Xcs\Cache;

use Xcs\ExException;
use Xcs\Traits\Singleton;

class Redis
{
    use Singleton;

    private array $_config;
    private ?\Redis $_link = null;

    public function __construct()
    {
        $config = getini('cache');
        if (empty($config['redis'])) {
            throw new ExException('cache.redis is empty');
        }
        $this->_config = $config;
        $this->_link = new \Redis;
        $connect = $this->_link->connect($config['redis']['host'], $config['redis']['port'], $config['redis']['timeout']);
        if (!$connect) {
            $this->_link = null;
            return;
        }
        if ($connect && $config['redis']['password']) {
            $connect = $this->_link->auth($config['redis']['password']);
        }
        if ($connect) {
            $this->_link->select($config['redis']['database'] ?? 0);
        }
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function get(string $key)
    {
        if (!$this->_link) {
            return null;
        }
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
     * @param array|string $value
     * @param int $ttl
     * @return bool|\Redis
     */
    public function set(string $key, $value, int $ttl = 0)
    {
        $data = ['data' => $value, 'timeout' => $ttl];
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $handKey = $this->_config['prefix'] . ':' . $key;
        if ($ttl > 0) {
            $ret = $this->_link->set($handKey, $json, $ttl);
        } else {
            $ret = $this->_link->set($handKey, $json);
        }
        return $ret;
    }

    public function incrBy(string $key, int $value)
    {
        return $this->_link->incrBy($key, $value);
    }

    /**
     * @param string $key
     * @return int|\Redis
     */
    public function rm(string $key)
    {
        $handKey = $this->_config['prefix'] . ':' . $key;
        return $this->_link->del($handKey);
    }

    public function clear()
    {
        $this->_link->flushDB();
    }

}
