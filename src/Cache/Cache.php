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
    public $enable;
    public $type;

    /**
     * Cache constructor.
     * @param null $handle
     * @throws ExException
     */
    public function __construct($handle = null)
    {
        $this->config = getini('cache');
        $this->prefix = $this->config['prefix'];
        $handle = $handle ?: $this->config['handle'];
        if (in_array($handle, ['file', 'memcache', 'redis'])) {
            $class = "\\Xcs\\Cache\\" . ucfirst($handle);
            if ($handle != 'file') {
                $config = Context::dsn($handle);
            } else {
                $config = null;
            }
            $this->handle = $class::getInstance()->init($config);
            $this->enable = $this->handle->enable;
            $this->type = $handle;
        } else {
            throw new ExException('不存在的缓存器');
        }
    }

    /**
     * @return bool
     */
    public function __destruct()
    {
        return $this->enable && $this->handle->close();
    }

    /**
     * @param string $key
     * @return mixed
     */
    public static function get($key)
    {
        return self::getInstance()->_get($key);
    }

    /**
     * @param string $key
     * @return mixed|null
     */
    private function _get($key)
    {
        if ($this->enable) {
            $data = $this->handle->get($this->_key($key));
            if (!$data) {
                return null;
            } else {
                return $data;
            }
        }
        return null;
    }

    /**
     * @param string $key
     * @param string $value
     * @param int $ttl
     * @return mixed
     */
    public static function set($key, $value, $ttl = 0)
    {
        return self::getInstance()->_set($key, $value, $ttl);
    }

    /**
     * @param string $key
     * @param string $value
     * @param int $ttl
     * @return bool
     */
    private function _set($key, $value, $ttl = 0)
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
    public static function rm($key)
    {
        return self::getInstance()->_rm($key);
    }

    /**
     * @param $key
     * @return bool
     */
    private function _rm($key)
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
    private function _clear()
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
    private function _key($str)
    {
        return $this->prefix . $str;
    }

}
