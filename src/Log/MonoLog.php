<?php

namespace Xcs\Log;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Xcs\Traits\Singleton;

class MonoLog
{

    use Singleton;

    public $log = null;

    public function __construct()
    {
        $logfile = DATA_PATH . 'debug.log';
        $this->log = new Logger('run');
        $this->log->pushHandler(new StreamHandler($logfile, Logger::WARNING));
        return $this->log;
    }

    /**
     * @param $data
     * @param string $code
     */
    public function log($data, string $code = 'debug')
    {
        if ($code == 'info') {
            $this->log->addInfo($data);
        } elseif ($code == 'warn') {
            $this->log->addWarning($data);
        } elseif ($code == 'error') {
            $this->log->addError($data);
        } else {
            $this->log->addDebug($data);
        }
    }

}