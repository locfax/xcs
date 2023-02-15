<?php

namespace Xcs\Di;

use ReflectionException;
use Xcs\App;
use Xcs\ExException;

/**
 * Instance represents a reference to a named object in a dependency injection (DI) container or a service locator.
 *
 * You may use [[get()]] to obtain the actual object referenced by [[id]].
 *
 * Instance is mainly used in two places:
 *
 * - When configuring a dependency injection container, you use Instance to reference a class name, interface name
 *   or alias name. The reference can later be resolved into the actual object by the container.
 * - In classes which use service locator to obtain dependent objects.
 *
 * The following example shows how to configure a DI container with Instance:
 *
 * ```php
 * $container = new \di\Container;
 * $container->set('cache', [
 *     'class' => 'caching\DbCache',
 *     'db' => Instance::of('db')
 * ]);
 * $container->set('db', [
 *     'class' => '\db\Connection',
 *     'dsn' => 'sqlite:path/to/file.db',
 * ]);
 * ```
 *
 * And the following example shows how a class retrieves a component from a service locator:
 *
 * ```php
 * class DbCache extends Cache
 * {
 *     public $db = 'db';
 *
 *     public function init()
 *     {
 *         parent::init();
 *         $this->db = Instance::ensure($this->db, 'db\Connection');
 *     }
 * }
 * ```
 */
class Instance
{
    /**
     * @var string the component ID, class name, interface name or alias name
     */
    public $id;


    /**
     * Constructor.
     * @param string $id the component ID
     */
    protected function __construct(string $id)
    {
        $this->id = $id;
    }

    /**
     * Creates a new Instance object.
     * @param string $id the component ID
     * @return Instance the new Instance object.
     */
    public static function of(string $id): Instance
    {
        return new static($id);
    }

    /**
     * Resolves the specified reference into the actual object and makes sure it is of the specified type.
     *
     * The reference may be specified as a string or an Instance object. If the former,
     * it will be treated as a component ID, a class/interface name or an alias, depending on the container type.
     *
     * If you do not specify a container, the method will first try `::$app` followed by `::$container`.
     *
     * For example,
     *
     * ```php
     * use \db\Connection;
     *
     * // returns db
     * $db = Instance::ensure('db', Connection::className());
     * // returns an instance of Connection using the given configuration
     * $db = Instance::ensure(['dsn' => 'sqlite:path/to/my.db'], Connection::className());
     * ```
     *
     * @param object|string|array|static $reference an object or a reference to the desired object.
     * You may specify a reference in terms of a component ID or an Instance object.
     * Starting from version 2.0.2, you may also pass in a configuration array for creating the object.
     * If the "class" value is not specified in the configuration array, it will use the value of `$type`.
     * @param string|null $type the class/interface name to be checked. If null, type check will not be performed.
     * @param Container|null $container the container. This will be passed to [[get()]].
     * @return object the object referenced by the Instance, or `$reference` itself if it is an object.
     * @throws ReflectionException
     */
    public static function ensure($reference, string $type = null, Container $container = null)
    {
        if (is_array($reference)) {
            $class = $reference['class'] ?? $type;
            if (!$container instanceof Container) {
                $container = App::$container;
            }
            unset($reference['class']);
            $component = $container->get($class, [], $reference);
            if ($type === null || $component instanceof $type) {
                return $component;
            }

            new ExException('Invalid data type: ' . $class . '. ' . $type . ' is expected.');
            return null;
        } elseif (empty($reference)) {
            new ExException('The required component is not specified.');
            return null;
        }

        if (is_string($reference)) {
            $reference = new static($reference);
        } elseif ($type === null || $reference instanceof $type) {
            return $reference;
        }

        if ($reference instanceof self) {
            $component = $reference->get($container);
            if ($type === null || $component instanceof $type) {
                return $component;
            }

            new ExException('"' . $reference->id . '" refers to a ' . get_class($component) . " component. $type is expected.");
            return null;
        }

        $valueType = is_object($reference) ? get_class($reference) : gettype($reference);
        new ExException("Invalid data type: $valueType. $type is expected.");
    }

    /**
     * Returns the actual object referenced by this Instance object.
     * @param $container //the container used to locate the referenced object.
     * @return mixed the actual object referenced by this Instance object.
     * @throws ReflectionException
     */
    public function get($container): object
    {
        if ($container) {
            return $container->get($this->id);
        }
        return App::$container->get($this->id);
    }

    /**
     * Restores class state after using `var_export()`.
     *
     * @param array $state
     * @return Instance
     * @see var_export()
     */
    public static function __set_state(array $state)
    {
        if (!isset($state['id'])) {
            new ExException('Failed to instantiate class "Instance". Required parameter "id" is missing');
            return null;
        }
        return new self($state['id']);
    }
}
