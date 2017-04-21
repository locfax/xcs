<?php

namespace cache;

class Memcache extends \traits\Singleton {

    public $enable = false;
    private $_link = null;
    private $_plink = false;

    public function __destruct() {
        $this->close();
    }

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

        }
        return $this;
    }

    public function close() {
        if (!$this->_plink) {
            $this->_link && $this->_link->close();
        }
    }

    public function get($key) {
        try {
            return $this->_link->get($key);
        } catch (\MemcachedException $e) {
            return false;
        }
    }

    public function set($key, $value, $ttl = 0) {
        try {
            if ($ttl > 0) {
                return $this->_link->set($key, $value, MEMCACHE_COMPRESSED, $ttl);
            }
            return $this->_link->set($key, $value);
        } catch (\MemcachedException $e) {
            return false;
        }
    }

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
