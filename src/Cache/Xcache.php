<?php

namespace Xcs\Cache;

class Xcache {

    use \Xcs\Traits\Singleton;

    public $enable = false;

    /**
     * @return $this
     * @throws \Exception
     */
    public function init() {
        if (!function_exists('xcache_get')) {
            throw new \Xcs\Exception\ExException('xcache 扩展没安装?');
        }
        $this->enable = true;
        return $this;
    }

    public function close() {

    }

    /**
     * @param $key
     * @return mixed
     */
    public function get($key) {
        return xcache_get($key);
    }

    /**
     * @param $key
     * @param $value
     * @param int $ttl
     * @return bool
     */
    public function set($key, $value, $ttl = 0) {
        if ($ttl > 0) {
            return xcache_set($key, $value, $ttl);
        }
        return xcache_set($key, $value);
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
        return xcache_unset($key);
    }

    public function clear() {
        xcache_clear_cache(1, -1);
    }

}
