<?php

namespace Xcs\Cache;

use Xcs\ExException;
use Xcs\Helper\FileHelper;
use Xcs\Traits\Singleton;

class File
{

    use Singleton;

    public bool $enable = false;

    /**
     * @return $this
     */
    public function init(): File
    {
        $this->enable = true;
        return $this;
    }

    private function filePath($hash)
    {
        $dir1 = substr($hash, 0, 2);  // 前两位作为第一级目录
        $dir2 = substr($hash, 2, 2);  // 接着两位作为第二级目录
        $dir3 = substr($hash, 4, 2);  // 再接着两位作为第三级目录
        return "$dir1/$dir2/$dir3";  // 拼接成文件路径
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function get(string $key)
    {
        $hashKey = md5($key);
        $cacheFile = CACHE_PATH . $this->filePath($hashKey) . '/' . $hashKey . '.php';
        if (file_exists($cacheFile)) {
            $data = file_get_contents($cacheFile);
            if (empty($data)) {
                return null;
            }
            $timeout = (int)substr($data, 8, 10);
            if ($timeout == 0 || $timeout > time()) {
                return unserialize(substr($data, 30));
            }
        }
        return null;
    }

    /**
     * @param string $key
     * @param array|string $val
     * @param int $ttl
     * @return false|int
     */
    public function set(string $key, $val, int $ttl = 0)
    {

        if ($ttl > 0) {
            $timeout = time() + $ttl;
        } else {
            //默认存储永久
            $timeout = 0;
        }

        $hashKey = md5($key);
        $path = CACHE_PATH . $this->filePath($hashKey);
        !file_exists($path) && mkdir($path, FILE_WRITE_MODE, true);

        $data = "<?php\n//" . sprintf('%010d', $timeout) . "\n exit();?>\n" . serialize($val);
        return $this->save($path . '/' . $hashKey . '.php', $data, FILE_WRITE_MODE);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function rm(string $key)
    {
        $hashKey = md5($key);
        $cacheFile = CACHE_PATH . $this->filePath($hashKey) . '/' . $hashKey . '.php';
        file_exists($cacheFile) && unlink($cacheFile);
        return true;
    }

    /**
     * @return void
     */
    public function clear()
    {
        $cacheDir = CACHE_PATH;
        $files = FileHelper::list_files($cacheDir);
        foreach ($files as $file) {
            file_exists($cacheDir . $file) && unlink($cacheDir . $file);
        }
    }

    /**
     * @param $filename
     * @param $content
     * @param $mode
     * @return false|int
     */
    private function save($filename, $content, $mode)
    {
        if (!file_exists($filename)) {
            touch($filename) && chmod($filename, FILE_WRITE_MODE); //读写执行
        }
        $ret = file_put_contents($filename, $content, LOCK_EX);
        if ($ret && FILE_WRITE_MODE != $mode) {
            chmod($filename, $mode);
        }
        return $ret;
    }

}
