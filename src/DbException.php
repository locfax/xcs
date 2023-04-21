<?php

namespace Xcs;

use Exception;

class DbException extends Exception
{
    public function __construct($message = '', $code = 0)
    {
        parent::__construct($message, intval($code));
        ExUiException::render('数据库', $message, '', 0, $this, true);
    }
}
