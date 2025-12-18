<?php

namespace Xcs\Cache;

use Xcs\Context;
use Xcs\ExException;
use Xcs\Traits\Singleton;

class Cache
{

    use Singleton;

    private $config;
    private $prefix;
    private $handle;
    public bool $enable;
    public $type;

    /**
     * @param $handle
     * @throws ExException
     */
    public function __construct($handle = null)
    {
        $this->config = getini('cache');
        $this->prefix = $this->config['prefix'];
        $handle = $handle ?: $this->config['handle'];
        if (in_array($handle, ['file', 'memcache', 'redis'])) {
            $class = "\\Xcs\\Cache\\" . ucfirst($handle);
            if ($handle == 'redis') {
                $config = $this->config['redis'];
            } elseif ($handle == 'memcache') {
                $config = $this->config['memcache'];
            } elseif ($handle == 'file') {
                $config = null;
            } else {
                throw new ExException('缓存器必须是 file redis memcache');
            }
            $this->handle = $class::getInstance()->init($config);
            $this->enable = $this->handle->enable;
            $this->type = $handle;
        } else {
            throw new ExException('不存在的缓存器');
        }
    }

    public function __destruct()
    {
        $this->enable && $this->handle->close();
    }

    /**
     * @param string $key
     * @return mixed
     */
    public static function get(string $key)
    {
        return self::getInstance()->_get($key);
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function _get(string $key)
    {
        if ($this->enable) {
            $data = $this->handle->get($this->_key($key));
            if (!$data) {
                return null;
            }
            return $data;
        }
        return null;
    }

    /**
     * @param string $key
     * @param array|string $value
     * @param int $ttl
     * @return mixed
     */
    public static function set(string $key, $value, int $ttl = 0)
    {
        return self::getInstance()->_set($key, $value, $ttl);
    }

    /**
     * @param string $key
     * @param array|string $value
     * @param int $ttl
     * @return bool
     */
    public function _set(string $key, $value, int $ttl = 0): bool
    {
        $ret = false;
        if ($this->enable) {
            $ret = $this->handle->set($this->_key($key), $value, $ttl);
        }
        return $ret;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public static function rm(string $key)
    {
        return self::getInstance()->_rm($key);
    }

    /**
     * @param $key
     * @return bool
     */
    public function _rm($key): bool
    {
        $ret = false;
        if ($this->enable) {
            $ret = $this->handle->rm($this->_key($key));
        }
        return $ret;
    }

    /**
     * @return mixed
     */
    public static function clear()
    {
        return self::getInstance()->_clear();
    }

    /**
     * @return bool
     */
    public function _clear(): bool
    {
        $ret = false;
        if ($this->enable) {
            $ret = $this->handle->clear();
        }
        return $ret;
    }

    /**
     * @param $str
     * @return string
     */
    private function _key($str): string
    {
        return $this->prefix . '_' . $str;
    }

}
