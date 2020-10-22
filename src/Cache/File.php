<?php

namespace Xcs\Cache;

class File
{

    use \Xcs\Traits\Singleton;

    public $enable = false;

    /**
     * @return $this
     * @throws \Xcs\Exception\ExException
     */
    public function init()
    {
        if (!is_dir(DATA_CACHE)) {
            throw new \Xcs\Exception\ExException('路径:' . DATA_CACHE . ' 不可写');
        }
        $this->enable = true;
        return $this;
    }

    public function close()
    {

    }

    /**
     * @param $key
     * @return mixed|null
     */
    public function get($key)
    {
        $cachefile = DATA_CACHE . $key . '.php';
        if (is_file($cachefile)) {
            $data = include $cachefile;
            if ($data && ($data['timeout'] == 0 || $data['timeout'] > time())) {
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
    public function set($key, $val, $ttl = 0)
    {
        if ($ttl > 0) {
            $timeout = time() + $ttl;
        } else {
            //默认存储永久
            $timeout = 0;
        }

        $cachefile = DATA_CACHE . $key . '.php';
        $cachedata = "return array('data' => '{$val}', 'timeout' => {$timeout});";
        $content = "<?php \n//CACHE FILE, DO NOT MODIFY ME PLEASE!\n//Identify: " . md5($key . time()) . "\n\n{$cachedata}";
        return $this->save($cachefile, $content, FILE_WRITE_MODE);
    }

    /**
     * @param $key
     * @param int $ttl
     * @return bool
     */
    public function expire($key, $ttl = 0)
    {
        return false;
    }

    /**
     * @param $key
     * @return bool
     */
    public function rm($key)
    {
        $cachefile = DATA_CACHE . $key . '.php';
        if (file_exists($cachefile)) {
            unlink($cachefile);
        }
        return true;
    }

    /**
     * @return bool
     */
    public function clear()
    {
        $cachedir = DATA_CACHE;
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
     * @return false|int
     */
    public function save($filename, $content, $mode)
    {
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
