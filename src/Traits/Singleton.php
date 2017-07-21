<?php

namespace Xcs\Traits;

use Xcs\Exception\Exception;

Trait Singleton {

    protected static $singleton_instances = array();

    public function __clone() {
        throw new Exception('Cloning ' . __CLASS__ . ' is not allowed');
    }

    /**
     * @param null $param
     * @return mixed
     */
    public static function getInstance($param = null) {
        $class = get_called_class();
        if (!isset(static::$singleton_instances[$class])) {
            static::$singleton_instances[$class] = new static($param);
        }
        return static::$singleton_instances[$class];
    }

    public static function clearInstance() {
        $class = get_called_class();
        unset(static::$singleton_instances[$class]);
    }
}
