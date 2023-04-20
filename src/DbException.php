<?php

namespace Xcs;

class DbException extends ExException
{
    public function __construct($code = 0, $message = '', $file = '', $line = 0, $type = '数据库错误', $previous = null)
    {
        parent::__construct($code, $message, $file, $line, $type, $previous);
    }
}
