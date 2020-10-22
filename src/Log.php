<?php

namespace Xcs;

class Log
{

    use \Xcs\Traits\Singleton;

    public $log = null;

    public function __construct()
    {
        $logfile = DATA_PATH . 'debug.log';
        $this->log = new \Monolog\Logger('run');
        $this->log->pushHandler(new \Monolog\Handler\StreamHandler($logfile, \Monolog\Logger::WARNING));
        return $this->log;
    }

    /**
     * @param $data
     * @param string $code
     */
    public function log($data, $code = 'debug')
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