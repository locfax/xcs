<?php

namespace Xcs\Traits;

use Error;

trait Singleton
{
    protected static array $singleton_instances = [];

    public function __clone()
    {
        throw new Error('Cloning ' . __CLASS__ . ' is not allowed');
    }

    public static function getInstance()
    {
        $class = get_called_class();
        if (!isset(static::$singleton_instances[$class])) {
            static::$singleton_instances[$class] = new static();
        }
        return static::$singleton_instances[$class];
    }

    public static function clearInstance(): void
    {
        unset(static::$singleton_instances[get_called_class()]);
    }
}
