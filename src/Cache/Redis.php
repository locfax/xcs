<?php

namespace Xcs\Cache;

use \Xcs\Traits\Singleton;

class Redis extends Singleton {

    public $enable = false;
    private $_link = null;
    private $_plink = false;

    public function __destruct() {
        $this->close();
    }

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
                $connect = $this->_link->auth($config['login'] . "-" . $config['password'] . "-" . $config['database']);
            }
            if ($connect) {
                $this->_link->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);
                $this->enable = true;
            }
        } catch (\RedisException $ex) {

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
        } catch (\RedisException $e) {
            return false;
        }
    }

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
