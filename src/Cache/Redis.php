<?php

namespace Xcs\Cache;

class Redis {

    use \Xcs\Traits\Singleton;

    public $enable = false;
    private $_link = null;
    private $_plink = false;

    public function __destruct() {
        $this->close();
    }

    /**
     * @param $config
     * @return $this
     * @throws \Exception
     */
    public function init($config) {
        try {
            $this->_link = new \Redis();
            if ($config['pconnect']) {
                $this->_plink = true;
                $server = 'pconnect';
            } else {
                $server = 'connect';
            }
            $connect = $this->_link->$server($config['host'], $config['port'], $config['timeout']);
            if ($connect && $config['password']) {
                $connect = $this->_link->auth($config['password']);
            }
            if ($connect) {
                $this->_link->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);
                $this->enable = true;
            }
        } catch (\RedisException $ex) {
            throw new \Xcs\Exception\ExException('redis初始化错误');
        }
        return $this;
    }

    public function close() {
        if (!$this->_plink) {
            $this->_link && $this->_link->close();
        }
    }

    /**
     * @param $key
     * @return bool
     */
    public function get($key) {
        try {
            return $this->_link->get($key);
        } catch (\RedisException $e) {
            return false;
        }
    }

    /**
     * @param $key
     * @param $value
     * @param int $ttl
     * @return bool
     */
    public function set($key, $value, $ttl = 0) {
        try {
            $ret = $this->_link->set($key, $value);
            if ($ttl > 0) {
                $this->_link->expire($key, $ttl);
            }
            return $ret;
        } catch (\RedisException $e) {
            return false;
        }
    }

    /**
     * @param $key
     * @param int $ttl
     * @return mixed
     */
    public function expire($key, $ttl = 0) {
        return $this->_link->expire($key, $ttl);
    }

    /**
     * @param $key
     * @return bool
     */
    public function rm($key) {
        try {
            return $this->_link->delete($key);
        } catch (\RedisException $e) {
            return false;
        }
    }

    public function clear() {
        try {
            return $this->_link->flushDB();
        } catch (\RedisException $e) {
            return false;
        }
    }

}
