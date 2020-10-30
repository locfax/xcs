<?php

namespace Xcs\Helper;

class Uploader
{

    private $_files = [];
    private $_count = 0;

    /**
     * @param $tempFiles
     * @param bool $cascade
     * @return $this|null
     */
    public function init($tempFiles, $cascade = true)
    {
        $this->reset();

        if (!is_array($tempFiles)) {
            return null;
        }
        foreach ($tempFiles as $field => $struct) {
            if (!isset($struct['error'])) {
                continue;
            }
            if (is_array($struct['error'])) {
                $arr = [];
                for ($i = 0; $i < count($struct['error']); $i++) {
                    if ($struct['error'][$i] != UPLOAD_ERR_NO_FILE) {
                        $arr[] = new HandleFile($struct, $field, $i);
                        if (!$cascade) {
                            $this->_files["{$field}{$i}"] = $arr[count($arr) - 1];
                        }
                    }
                }
                if ($cascade) {
                    $this->_files[$field] = $arr;
                }
            } else {
                if ($struct['error'] != UPLOAD_ERR_NO_FILE) {
                    $this->_files[$field] = new HandleFile($struct, $field);
                }
            }
        }
        $this->_count = count($this->_files);
        return $this;
    }

    public function reset()
    {
        $this->_files = [];
        $this->_count = 0;
    }

    /**
     * @return int
     */
    public function getCount()
    {
        return $this->_count;
    }

    /**
     * @return array|bool
     */
    public function getFiles()
    {
        $return = false;
        if (empty($this->_files)) {
            return $return;
        }
        return $this->_files;
    }

    /**
     * @param $name
     * @return HandleFile|bool
     */
    public function getFile($name)
    {
        $return = false;
        if (!isset($this->_files[$name])) {
            return $return;
        }
        return $this->_files[$name];
    }

    /**
     * @param $name
     * @return bool
     */
    public function isFileExist($name)
    {
        return isset($this->_files[$name]);
    }

    /**
     * @param $destDir
     */
    public function batchMove($destDir)
    {
        foreach ($this->_files as $file) {
            $file->move($destDir . '/' . $file->getFilename());
        }
    }

}
