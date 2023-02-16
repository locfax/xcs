<?php

namespace Xcs\Helper;

class FileHelper
{

    /**
     * @param $path
     * @param int $mode
     * @return bool
     */
    public static function mk_dir($path, int $mode = DIR_WRITE_MODE): bool
    {
        if (!is_dir($path)) {
            return mkdir($path, $mode, true);
        }
        return true;
    }

    /**
     * @param $path
     * @return bool
     */
    public static function rm_dir($path): bool
    {
        $dir = realpath($path);
        if ('' == $dir || '/' == $dir || (3 == strlen($dir) && ':\\' == substr($dir, 1))) {
            return false;
        }
        if (false !== ($dh = opendir($dir))) {
            while (false !== ($file = readdir($dh))) {
                if ('.' == $file || '..' == $file) {
                    continue;
                }
                $path = $dir . '/' . $file;
                if (is_dir($path)) {
                    if (!self::rm_dir($path)) {
                        return false;
                    }
                } else {
                    unlink($path);
                }
            }
            closedir($dh);
            rmdir($dir);
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $dir
     */
    public static function clear_dir($dir)
    {
        $d = dir($dir);
        while (($f = $d->read())) {
            if ($f == '.' || $f == '..') {
                continue;
            }
            if (is_file($dir . '/' . $f)) {
                if ($f == 'unzip.php') {
                    continue;
                } elseif ($f == 'build.pz') {
                    continue;
                } elseif ($f == 'logsql.usql') {
                    continue;
                } elseif ($f == 'himitsu.php') {
                    continue;
                }
                unlink($dir . '/' . $f);
            } elseif (is_dir($dir . '/' . $f)) {
                self::clear_dir($dir . '/' . $f);
                rmdir($dir . '/' . $f);
            }
        }
        $d->close();
    }

    /**
     * 遍历文件目录
     * @param string $dir
     * @param bool $dirFile
     * @param bool $md5
     * @param bool $root
     * @return array
     */
    public static function list_files(string $dir, bool $dirFile = false, bool $md5 = true, bool $root = true): array
    {
        static $return = [];
        if ($root) {
            $return = [];
        }
        $filePoint = opendir($dir);
        while (($target = readdir($filePoint)) !== false) {
            if ("." == $target || ".." == $target) {
                continue;
            }
            if (is_dir($dir . $target)) {
                self::list_files($dir . $target . '/', $dirFile, $md5, false);
            } else {
                $file = $dirFile ? $dir . $target : $target;
                if ($md5) {
                    $return[md5_file($dir . $target)] = $file;
                } else {
                    $return[] = $file;
                }
            }
        }
        closedir($filePoint);
        return $return;
    }

}
