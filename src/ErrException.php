<?php

namespace Xcs;

use Exception;

class ErrException extends Exception
{
    public function __construct($message = '', $code = 0, $file = '', $line = 0)
    {
        parent::__construct($message, intval($code));
        ExUiException::render('语法解析', $message, $file, $line, true, $this);
    }
}