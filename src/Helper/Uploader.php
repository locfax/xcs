<?php

namespace Xcs\Helper;

class Uploader
{
    private array $_files = [];
    private int $_count = 0;

    /**
     * @param $tempFiles
     * @param bool $cascade
     * @return void
     */
    public function init($tempFiles, bool $cascade = true)
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
    }

    public function reset(): void
    {
        $this->_files = [];
        $this->_count = 0;
    }

    /**
     * @return int
     */
    public function getCount(): int
    {
        return $this->_count;
    }

    /**
     * @return array|bool
     */
    public function getFiles()
    {
        if (empty($this->_files)) {
            return false;
        }
        return $this->_files;
    }

    /**
     * @param $name
     * @return HandleFile|bool
     */
    public function getFile($name)
    {
        if (!isset($this->_files[$name])) {
            return false;
        }
        return $this->_files[$name];
    }

    /**
     * @param $name
     * @return bool
     */
    public function isFileExist($name): bool
    {
        return isset($this->_files[$name]);
    }

    /**
     * @param $destDir
     */
    public function batchMove($destDir): void
    {
        foreach ($this->_files as $file) {
            $file->move($destDir . '/' . $file->getFilename());
        }
    }

}
