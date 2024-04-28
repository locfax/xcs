<?php

namespace Xcs\Cache;

use Xcs\Traits\Singleton;

class Redis
{

    use Singleton;

    public bool $enable = false;
    /**
     * @var \Redis
     */
    private \Redis $_link;

    /**
     * @param $config
     * @return $this
     */
    public function init($config): Redis
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

    public function close(): void
    {
        $this->_link->close();
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed
    {
        return $this->_link->get($key);
    }

    /**
     * @param string $key
     * @param array|string $value
     * @param int $ttl
     * @return bool|\Redis
     */
    public function set(string $key, array|string $value, int $ttl = 0): bool|\Redis
    {
        if ($ttl > 0) {
            $ret = $this->_link->set($key, $value, $ttl);
        } else {
            $ret = $this->_link->set($key, $value);
        }
        return $ret;
    }

    /**
     * @return bool
     */
    public function expire(): bool
    {
        return false;
    }

    /**
     * @param string $key
     * @return int|\Redis
     */
    public function rm(string $key): int|\Redis
    {
        return $this->_link->del($key);
    }

    public function clear(): void
    {
        //$this->_link->flushDB();
    }

}
