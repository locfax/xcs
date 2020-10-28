<?php

namespace Xcs\Di;

/**
 * Class BaseObject
 * @package Xcs\Di
 */
class BaseObject implements Configurable
{

    public function className()
    {
        return get_called_class();
    }

}