<?php

namespace Xcs\Cache;

use \Xcs\Exception\ExException;

class Cacher
{

    use \Xcs\Traits\Singleton;

    private $config;
    private $prefix;
    private $cacher;
    public $enable;
    public $type;

    /**
     * Cacher constructor.
     * @param null $cacher
     * @throws ExException
     */
    public function __construct($cacher = null)
    {
        $this->config = getini('cache');
        $this->prefix = $this->config['prefix'];
        $cacher = $cacher ?: $this->config['cacher'];
        if (in_array($cacher, ['file', 'memcache', 'redis'])) {
            $class = "\\Xcs\\Cache\\" . ucfirst($cacher);
            if ($cacher != 'file') {
                $config = \Xcs\Context::dsn($cacher);
            } else {
                $config = null;
            }
            $this->cacher = $class::getInstance()->init($config);
            $this->enable = $this->cacher->enable;
            $this->type = $cacher;
        } else {
            throw new \Xcs\Exception\ExException('不存在的缓存器');
        }
    }

    /**
     * @return bool
     */
    public function close()
    {
        return $this->enable && $this->cacher->close();
    }

    /**
     * @param $key
     * @return mixed|null
     */
    public function get($key)
    {
        $ret = null;
        if ($this->enable) {
            $json = $this->cacher->get($this->_key($key));
            if (!$json) {
                return $ret;
            } else {
                $ret = json_decode($json, true);
                return $ret[0];
            }
        }
        return $ret;
    }

    /**
     * @param $key
     * @param $value
     * @param int $ttl
     * @return bool
     */
    public function set($key, $value, $ttl = 0)
    {
        $ret = false;
        if ($this->enable) {
            $data = [$value];
            $ret = $this->cacher->set($this->_key($key), \Xcs\App::output_json($data), $ttl);
        }
        return $ret;
    }

    /**
     * @param $key
     * @return bool
     */
    public function rm($key)
    {
        $ret = false;
        if ($this->enable) {
            $ret = $this->cacher->rm($this->_key($key));
        }
        return $ret;
    }

    /**
     * @return bool
     */
    public function clear()
    {
        $ret = false;
        if ($this->enable) {
            $ret = $this->cacher->clear();
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
