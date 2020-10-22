<?php

namespace Xcs;

class BaseObject implements Di\Configurable
{

    public function className()
    {
        return get_called_class();
    }

}