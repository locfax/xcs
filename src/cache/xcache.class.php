<?php

namespace cache;

class Xcache extends \traits\Singleton {

    public $enable = false;

    public function init() {
        if (!function_exists('xcache_get')) {
            throw new \event\Exception('xcache 扩展没安装?');
        }
        $this->enable = true;
        return $this;
    }

    public function close() {

    }

    public function get($key) {
        return xcache_get($key);
    }

    public function set($key, $value, $ttl = 0) {
        if ($ttl > 0) {
            return xcache_set($key, $value, $ttl);
        }
        return xcache_set($key, $value);
    }

    public function rm($key) {
        return xcache_unset($key);
    }

    public function clear() {
        xcache_clear_cache(1, -1);
    }

}
