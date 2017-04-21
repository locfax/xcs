<?php

namespace Xcs\Helper;

class HandleUpload {

    private $_file = array();
    private $_name;

    public function __construct($struct, $name, $ix = false) {
        if ($ix !== false) {
            $s = array(
                'name' => $struct['name'][$ix],
                'type' => $struct['type'][$ix],
                'tmp_name' => $struct['tmp_name'][$ix],
                'error' => $struct['error'][$ix],
                'size' => $struct['size'][$ix],
            );
            $this->_file = $s;
        } else {
            $this->_file = $struct;
        }

        $this->_file['is_moved'] = false;
        $this->_name = $name;
    }

    /**
     * @param $name
     * @param $value
     */
    public function setAttribute($name, $value) {
        $this->_file[$name] = $value;
    }

    /**
     * @param $name
     * @return mixed
     */
    public function getAttribute($name) {
        return $this->_file[$name];
    }

    /**
     * @return mixed
     */
    public function getName() {
        return $this->_name;
    }

    /**
     * @return bool
     */
    public function isSuccessed() {
        return $this->_file['error'] == UPLOAD_ERR_OK;
    }

    /**
     * @return mixed
     */
    public function getError() {
        return $this->_file['error'];
    }

    /**
     * @return mixed
     */
    public function isMoved() {
        return $this->_file['is_moved'];
    }

    /**
     * @return mixed
     */
    public function getFilename() {
        return $this->_file['name'];
    }

    /**
     * @return string
     */
    public function getExt() {
        if ($this->isMoved()) {
            $ext = pathinfo($this->getNewPath(), PATHINFO_EXTENSION);
        } else {
            $ext = pathinfo($this->getFilename(), PATHINFO_EXTENSION);
        }
        return strtolower($ext);
    }

    /**
     * @return mixed
     */
    public function getSize() {
        return $this->_file['size'];
    }

    /**
     * @return mixed
     */
    public function getMimeType() {
        if (class_exists('finfo', false)) {
            $finfo = new \finfo(FILEINFO_MIME);
        } else {
            $finfo = null;
        }
        if (is_object($finfo)) {
            $_file = $this->getTmpName();
            $_mime = explode(';', $finfo->file($_file));
            $mime = $_mime[0];
        } else {
            $mime = $this->_file['type'];
        }
        return $mime;
    }

    /**
     * @return mixed
     */
    public function getTmpName() {
        return $this->_file['tmp_name'];
    }

    /**
     * @return mixed
     */
    public function getNewPath() {
        return $this->_file['new_path'];
    }

    /**
     * @param null $allowExts
     * @param null $maxSize
     * @return int
     */
    public function check($allowExts = null, $maxSize = null) {
        if (!$this->isSuccessed()) {
            return 1;
        }

        if ($allowExts) {
            if (strexists($allowExts, ',')) {
                $exts = explode(',', $allowExts);
            } elseif (strexists($allowExts, '/')) {
                $exts = explode('/', $allowExts);
            } elseif (strexists($allowExts, '|')) {
                $exts = explode('|', $allowExts);
            } else {
                $exts = array($allowExts);
            }

            $filename = $this->getFilename();
            $fileexts = explode('.', $filename);
            array_shift($fileexts);
            $count = count($fileexts);
            $passed = false;
            $exts = array_filter(array_map('trim', $exts), 'trim');
            foreach ($exts as $ext) {
                if ('.' == substr($ext, 0, 1)) {
                    $ext = substr($ext, 1);
                }
                $fileExt = implode('.', array_slice($fileexts, $count - count(explode('.', $ext))));
                if (strtolower($fileExt) == strtolower($ext)) {
                    $passed = true;
                    break;
                }
            }
            if (!$passed) {
                return 2;
            }
        }

        if ($maxSize && $this->getSize() > $maxSize) {
            return 3;
        }
        return 4;
    }

    /**
     * @param $destPath
     * @return bool
     */
    public function move($destPath) {
        $this->_file['is_moved'] = true;
        $this->_file['new_path'] = $destPath;
        return move_uploaded_file($this->_file['tmp_name'], $destPath);
    }

    /**
     * @param $destPath
     * @return bool
     */
    public function copy($destPath) {
        return copy($this->_file['tmp_name'], $destPath);
    }

    public function remove() {
        if ($this->isMoved()) {
            unlink($this->getNewPath());
        } else {
            unlink($this->getTmpName());
        }
    }

    public function removeMovedFile() {
        if ($this->isMoved()) {
            unlink($this->getNewPath());
        }
    }

}