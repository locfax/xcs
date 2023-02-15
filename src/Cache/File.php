<?php

namespace Xcs\Cache;

use Xcs\ExException;
use Xcs\Helper\FileHelper;
use Xcs\Traits\Singleton;

class File
{

    use Singleton;

    public $enable = false;

    /**
     * @return $this
     * @throws ExException
     */
    public function init(): File
    {
        !is_dir(DATA_CACHE) && mkdir(DATA_CACHE);
        if (!is_writeable(DATA_CACHE)) {
            throw new ExException('路径:' . DATA_CACHE . ' 不可写');
        }
        $this->enable = true;
        return $this;
    }

    public function close()
    {

    }

    /**
     * @param $key
     * @return mixed
     */
    public function get($key)
    {
        $cacheFile = DATA_CACHE . $key . '.php';
        if (is_file($cacheFile)) {
            $data = include $cacheFile;
            if ($data && ($data['timeout'] == 0 || $data['timeout'] > time())) {
                if ('json' == $data['type']) {
                    return json_decode($data['data'], true);
                }
                return $data['data'];
            }
            unlink($cacheFile);
        }
        return null;
    }

    /**
     * @param $key
     * @param $val
     * @param int $ttl
     * @return false|int
     */
    public function set($key, $val, int $ttl = 0)
    {
        if ($ttl > 0) {
            $timeout = time() + $ttl;
        } else {
            //默认存储永久
            $timeout = 0;
        }

        $type = 'string';
        if (is_array($val)) {
            $val = json_encode($val, JSON_UNESCAPED_UNICODE);
            $type = 'json';
        }

        $cacheFile = DATA_CACHE . $key . '.php';
        $cacheData = "return array('data' => '{$val}', 'type'=>'{$type}', 'timeout' => {$timeout});";
        $content = "<?php \n//CACHE FILE, DO NOT MODIFY ME PLEASE!\n//Identify: " . md5($key . time()) . "\n\n{$cacheData}";
        return $this->save($cacheFile, $content, FILE_WRITE_MODE);
    }

    /**
     * @param $key
     * @param int $ttl
     * @return bool
     */
    public function expire($key, int $ttl = 0): bool
    {
        return false;
    }

    /**
     * @param $key
     * @return bool
     */
    public function rm($key): bool
    {
        $cacheFile = DATA_CACHE . $key . '.php';
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
        return true;
    }

    /**
     * @return bool
     */
    public function clear(): bool
    {
        $cacheDir = DATA_CACHE;
        $files = FileHelper::list_files($cacheDir);
        foreach ($files as $file) {
            unlink($cacheDir . $file);
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
            touch($filename) && chmod($filename, FILE_WRITE_MODE); //读写执行
        }
        $ret = file_put_contents($filename, $content, LOCK_EX);
        if ($ret && FILE_WRITE_MODE != $mode) {
            chmod($filename, $mode);
        }
        return $ret;
    }

}
