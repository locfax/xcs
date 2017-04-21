<?php

namespace helper;

class File extends \traits\Singleton {

    public function mk_dir($path, $mode = DIR_WRITE_MODE) {
        if (!is_dir($path)) {
            return mkdir($path, $mode, true);
        }
        return true;
    }

    public function rm_dir($path) {
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
                    if (!$this->rm_dir($path)) {
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

    function clear_dir($dir) {
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
                $this->clearDir($dir . '/' . $f);
                rmdir($dir . '/' . $f);
            }
        }
        $d->close();
    }


    /*
     * 遍历文件目录
     */

    public function list_files($dir, $dirfile = false, $md5 = true, $root = true) {
        static $return = array();
        if ($root) {
            $return = array();
        }
        $filepoint = opendir($dir);
        while (($target = readdir($filepoint)) !== false) {
            if ("." == $target || ".." == $target) {
                continue;
            }
            if (is_dir($dir . $target)) {
                $this->list_files($dir . $target . '/', $dirfile, $md5, false);
            } else {
                if ($md5) {
                    $file = $dirfile ? $dir . $target : $target;
                    $return[md5_file($dir . $target)] = $file;
                } else {
                    $file = $dirfile ? $dir . $target : $target;
                    $return[] = $file;
                }
            }
        }
        closedir($filepoint);
        return $return;
    }

}
