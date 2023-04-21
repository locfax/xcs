<?php

namespace Xcs;

use Exception;

class ExException extends Exception
{
    public function __construct($message = '', $code = 0, $file = '', $line = 0, $trace = true)
    {
        parent::__construct($message, intval($code));
        ExUiException::render('异常信息', $message, $file, $line, $trace, $this);
    }
}