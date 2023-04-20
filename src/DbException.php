<?php

namespace Xcs;

class DbException extends ExException
{
    public function __construct($message = '', $code = 0, $file = '', $line = 0, $type = '数据库错误')
    {
        parent::__construct($message, $code, $file, $line, $type);
    }
}
