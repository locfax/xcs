<?php

namespace Xcs\Traits;

use Xcs\ExException;

trait Singleton
{

    protected static $singleton_instances = [];

    /**
     * @throws ExException
     */
    public function __clone()
    {
        throw new ExException('__clone', 'Cloning ' . __CLASS__ . ' is not allowed');
    }

    /**
     * @return Singleton
     */
    public static function getInstance()
    {
        $class = get_called_class();
        if (!isset(static::$singleton_instances[$class])) {
            static::$singleton_instances[$class] = new static();
        }
        return static::$singleton_instances[$class];
    }

    public static function clearInstance()
    {
        $class = get_called_class();
        unset(static::$singleton_instances[$class]);
    }
}
