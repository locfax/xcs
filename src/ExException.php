<?php

namespace Xcs;

use Exception;

class ExException extends Exception
{
    public function __construct($title = '异常信息', $message = '', $code = 0, $file = '', $line = 0, $trace = true)
    {
        parent::__construct($message, intval($code));
        ExUiException::render($title, $message . ' CODE:' . $code, $file, $line, $trace, $this);
    }
}