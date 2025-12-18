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
     * @param bool $option
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
            $this->enable = true;
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
     * @return mixed
     */
    public function get(string $key)
    {
        $json = $this->_link->get($key);
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
        $json = json_encode($data, JSON_UNESCAPED_SLASHES);
        if ($ttl > 0) {
            $ret = $this->_link->set($key, $json, $ttl);
        } else {
            $ret = $this->_link->set($key, $json);
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
     * @param string $key
     * @return int|\Redis
     */
    public function rm(string $key)
    {
        return $this->_link->del($key);
    }

    public function clear()
    {
        $this->_link->flushDB();
    }

}
