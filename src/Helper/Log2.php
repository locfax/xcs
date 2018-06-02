<?php

namespace Xcs\Helper;

class Log2
{

    use \Xcs\Traits\Singleton;

    private $log_file_format = 'Y-m-d';

    private $log_file_dir;

    public function __construct($config = array())
    {
        if (!empty($config)) {
            foreach ($config as $var => $val) {
                if (property_exists($this, $var)) {
                    $this->$var = $val;
                }
            }
        }
    }

    public function putLog($str)
    {
        $str = "" . date("Y-m-d H:i:s", time()) . " Info: " . $str . " \r\n";
        $log_file = $this->log_file_dir . "/" . date($this->log_file_format, time()) . ".log";
        if (is_dir($this->log_file_dir) && is_writable($this->log_file_dir) == false) {
            chmod($this->log_file_dir, '7777');
        }
        file_put_contents($log_file, $str, FILE_APPEND);
        return true;
    }

}