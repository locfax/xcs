<?php

namespace Xcs\Cache;

class Memcache {

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
            $this->_link = new \Memcache();
            if ($config['pconnect']) {
                $this->_plink = true;
                $server = 'pconnect';
            } else {
                $server = 'connect';
            }
            $connect = $this->_link->$server($config['host'], $config['port'], $config['timeout']);
            if ($connect) {
                $this->enable = true;
            }
        } catch (\MemcachedException $e) {
            throw new \Exception('memcache初始化错误');
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
    public function set($key, $value, $ttl = 0) {
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
    public function expire($key, $ttl = 0) {
        return false;
    }

    /**
     * @param $key
     * @return bool
     */
    public function rm($key) {
        try {
            return $this->_link->delete($key);
        } catch (\MemcachedException $e) {
            return false;
        }
    }

    public function clear() {
        return $this->_link->flush();
    }

}
