<?php

namespace Xcs\Cache;

use Xcs\Traits\Singleton;

class File
{
    use Singleton;

    private function filePath($hash): string
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
    public function get(string $key, bool $isPath = false): mixed
    {
        if ($isPath) {
            $cacheFile = APP_ROOT . DS . $key;
        } else {
            $config = getini('cache');
            $hashKey = md5($config['prefix'] . '_' . $key);
            $cacheFile = CACHE_PATH . $this->filePath($hashKey) . '/' . $config['prefix'] . '_' . $hashKey . '.php';
        }

        if (is_file($cacheFile)) {
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
     * @param mixed $val
     * @param int $ttl
     * @return false|int
     */
    public function set(string $key, mixed $val, int $ttl = 0, $isPath = false): bool|int
    {
        if ($ttl > 0) {
            $timeout = time() + $ttl;
        } else {
            //默认存储永久
            $timeout = 0;
        }

        if ($isPath) {
            $cacheFile = APP_ROOT . DS . $key;
        } else {
            $config = getini('cache');
            $hashKey = md5($config['prefix'] . '_' . $key);
            $path = CACHE_PATH . $this->filePath($hashKey);
            !file_exists($path) && mkdir($path, FILE_WRITE_MODE, true);
            $cacheFile = $path . '/' . $config['prefix'] . '_' . $hashKey . '.php';
        }

        $data = "<?php\n//" . sprintf('%010d', $timeout) . "\n exit();?>\n" . serialize($val);
        return $this->save($cacheFile, $data, FILE_WRITE_MODE);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function rm(string $key, $isPath = false): bool
    {
        if ($isPath) {
            $cacheFile = APP_ROOT . DS . $key;
        } else {
            $config = getini('cache');
            $hashKey = md5($config['prefix'] . '_' . $key);
            $cacheFile = CACHE_PATH . $this->filePath($hashKey) . '/' . $config['prefix'] . '_' . $hashKey . '.php';
        }

        return is_file($cacheFile) && unlink($cacheFile);
    }

    /**
     * @param $filename
     * @param $content
     * @param $mode
     * @return false|int
     */
    private function save($filename, $content, $mode): bool|int
    {
        if (!is_file($filename)) {
            touch($filename) && chmod($filename, FILE_WRITE_MODE); //读写执行
        }

        $ret = file_put_contents($filename, $content, LOCK_EX);
        if ($ret && FILE_WRITE_MODE != $mode) {
            chmod($filename, $mode);
        }

        return $ret;
    }

}
