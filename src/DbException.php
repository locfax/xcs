<?php

namespace Xcs;

class DbException extends ExException
{
    public function __construct($message = '', $code = 0)
    {
        parent::__construct($message, $code, '', 0, '数据库错误');
    }
}
