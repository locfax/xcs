<?php

namespace Xcs\Cache;

use \Xcs\Exception\Exception;

class File {

    use \Xcs\Traits\Singleton;

    public $enable = false;

    /**
     * @return $this
     * @throws Exception
     */
    public function init() {
        if (!is_dir(getini('data/_cache'))) {
            throw new Exception('路径:' . getini('data/_cache') . ' 不可写');
        }
        $this->enable = true;
        return $this;
    }

    public function close() {

    }

    /**
     * @param $key
     * @return null
     */
    public function get($key) {
        $cachefile = getini('data/_cache') . $key . '.php';
        if (is_file($cachefile)) {
            $data = include $cachefile;
            if ($data && $data['timeout'] > time()) {
                return $data['data'];
            }
            unlink($cachefile);
        }
        return null;
    }

    /**
     * @param $key
     * @param $val
     * @param int $ttl
     * @return bool|int
     */
    public function set($key, $val, $ttl = 0) {
        if ($ttl > 0) {
            $timeout = time() + $ttl;
        } else {
            //默认存储一个月
            $timeout = time() + 30 * 24 * 3600;
        }

        $cachefile = getini('data/_cache') . $key . '.php';
        $cachedata = "return array('data' => '{$val}', 'timeout' => {$timeout});";
        $content = "<?php \n//CACHE FILE, DO NOT MODIFY ME PLEASE!\n//Identify: " . md5($key . time()) . "\n\n{$cachedata}";
        return $this->save($cachefile, $content, FILE_WRITE_MODE);
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
        $cachefile = getini('data/_cache') . $key . '.php';
        if (file_exists($cachefile)) {
            unlink($cachefile);
        }
        return true;
    }

    /**
     * @return bool
     */
    public function clear() {
        $cachedir = getini('data/_cache');
        $files = \Xcs\Helper\File::list_files($cachedir);
        foreach ($files as $file) {
            unlink($cachedir . $file);
        }
        return true;
    }

    /**
     * @param $filename
     * @param $content
     * @param $mode
     * @return bool|int
     */
    public function save($filename, $content, $mode) {
        if (!is_file($filename)) {
            file_exists($filename) && unlink($filename);
            touch($filename) && chmod($filename, FILE_WRITE_MODE); //全读写
        }
        $ret = file_put_contents($filename, $content, LOCK_EX);
        if ($ret && FILE_WRITE_MODE != $mode) {
            chmod($filename, $mode);
        }
        return $ret;
    }

}
