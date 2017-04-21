<?php

class Hook {

    /**
     * @param $fileName
     * @param string $absPath
     * @param string $ext
     * @return bool|string
     */
    private static function _filePath($fileName, $absPath = BASEPATH, $ext = 'php') {
        return $absPath . $fileName . '.' . $ext;
    }

    /**
     * @param $classPath
     * @return mixed
     */
    private static function _className($classPath) {
        return str_replace('/', "\\", $classPath);
    }

    /**
     * @param $fileName
     * @param bool $loadOnce
     * @param string $absPath
     * @param string $ext
     * @return bool|mixed
     */
    public static function loadFile($fileName, $loadOnce = true, $absPath = BASEPATH, $ext = 'php') {
        static $is_loaded = array();
        $file = self::_filePath($fileName, $absPath, $ext);
        $filemd5 = md5($file);
        if (isset($is_loaded[$filemd5]) && $loadOnce) {
            return true;
        }
        if (!is_file($file)) {
            return false;
        }
        $is_loaded[$filemd5] = true;
        return include $file;
    }

    /**
     * @param $classPath
     * @param null $className
     * @param string $absPath
     * @param string $ext
     * @return mixed|null
     * @throws Exception
     */
    public static function loadClass($classPath, $className = null, $absPath = BASEPATH, $ext = 'php') {
        if (is_null($className)) {
            $className = self::_className($classPath);
        }
        if (class_exists($className, false) || interface_exists($className, false)) {
            return true;
        };
        $file = self::_filePath($classPath, $absPath, $ext);
        if (!is_file($file) || !include $file) {
            return false;
        }
        if (class_exists($className, false) || interface_exists($className, false)) {
            return true;
        }
        return false;
    }

    /**
     * @param $classPath
     * @param null $className 区分大小写
     * @param mixed $classParam
     * @param string $absPath
     * @param string $ext
     * @return mixed
     * @throws Exception
     */
    public static function getClass($classPath, $className = null, $classParam = null, $absPath = BASEPATH, $ext = 'php') {
        static $instances = array();
        if (is_null($className)) {
            $className = self::_className($classPath);
        }
        if (isset($instances[$className])) {
            return $instances[$className];
        }
        if (!self::loadClass($classPath, $className, $absPath, $ext)) {
            return false;
        }
        $obj = new $className($classParam);
        if ($obj instanceof $className) {
            $instances[$className] = $obj;
            return $instances[$className];
        }
        return false;
    }

    /**
     * 加载助手
     * @param $name
     * @param bool $isClass
     * @return bool|mixed|null
     */
    public static function helper($name, $isClass = false) {
        if ($isClass) {
            return self::loadClass('helper/' . $name, "\\helper\\{$name}");
        }
        return self::loadFile('helper/' . $name);
    }

    /**
     * 加载model
     * @param $classPath
     * @param bool $isClass
     * @return bool|mixed|null
     */
    public static function model($classPath, $isClass = true) {
        if ($isClass) {
            return self::loadClass('model/' . $classPath . '.class', '\\model\\' . self::_className($classPath), APPPATH);
        }
        return self::loadFile('model/' . $classPath, true, APPPATH);
    }

    /**
     * 加载插件
     * @param $classPath
     * @param bool $isClass
     * @return bool|mixed|null
     */
    public static function plugin($classPath, $isClass = false) {
        if ($isClass) {
            return self::loadClass('plugin/' . $classPath . '.class', "\\plugin\\" . self::_className($classPath), APPPATH);
        }
        return self::loadFile('plugin/' . $classPath, true, APPPATH);
    }

    /**
     * @param $classPath
     * @param null $className 区分大小写
     * @param mixed $classParam
     * @param string $ext
     * @return mixed
     */
    public static function getVendor($classPath, $className = null, $classParam = null, $ext = 'php') {
        if (is_null($className)) {
            //无指定类名 使用标准类名
            $className = '\\vendor\\' . self::_className($classPath);
        }
        return self::getClass('vendor/' . $classPath, $className, $classParam, APPPATH , $ext);
    }

    /**
     * @param $classPath
     * @param null $className 区分大小写
     * @param string $ext
     * @return bool|mixed|null
     */
    public static function loadVendor($classPath, $className = null, $ext = 'php') {
        if (!is_null($className)) {
            //指定了类名 使用类名加载
            return self::loadClass('vendor/' . $classPath, $className, APPPATH , $ext);
        }
        return self::loadFile('vendor/' . $classPath, true, APPPATH , $ext);
    }

}
