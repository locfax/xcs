<?php

namespace Xcs\Exception;

class Exception extends \Exception {

    public function __construct($message = '', $code = 0) {
        parent::__construct($message, $code);
        $this->exceptionError($this, 'Exception');
        set_exception_handler(function () {
        }); //不用自带的显示异常
    }

    public function systemError($exception) {
        $this->exceptionError($exception, 'SystemError');
    }

    /**
     * 代码执行过程回溯信息
     *
     * @static
     * @access public
     */
    public function debugBacktrace() {
        $skipFunc[] = 'handle_exception';
        $skipFunc[] = 'handle_error';
        $skipFunc[] = 'ErrorFunc::systemError';
        $skipFunc[] = 'ErrorFunc::debugBacktrace';
        $debugBacktrace = debug_backtrace();
        ksort($debugBacktrace);
        $phpMsg = array();
        foreach ($debugBacktrace as $error) {
            if (!isset($error['file'])) {
                // 利用反射API来获取方法/函数所在的文件和行数
                try {
                    if (isset($error['class'])) {
                        $reflection = new \ReflectionMethod($error['class'], $error['function']);
                    } else {
                        $reflection = new \ReflectionFunction($error['function']);
                    }
                    $error['file'] = $reflection->getFileName();
                    $error['line'] = $reflection->getStartLine();
                } catch (\ReflectionException $e) {
                    //$e->getCode();
                    continue;
                }
            }
            $func = isset($error['class']) ? $error['class'] : '';
            $func .= isset($error['type']) ? $error['type'] : '';
            $func .= isset($error['function']) ? $error['function'] : '';

            if (in_array($func, $skipFunc)) {
                continue;
            }
            $error['line'] = sprintf('%04d', $error['line']);
            $phpMsg[] = array('file' => $error['file'], 'line' => $error['line'], 'function' => $func);
        }
        return $phpMsg;
    }

    /**
     * 异常处理
     *
     * @static
     * @access public
     * @param $type
     * @param mixed $exception
     */
    public function exceptionError($exception, $type = 'Exception') {
        $errorMsg = $exception->getMessage();
        $trace = $exception->getTrace();
        krsort($trace);
        $trace[] = array('file' => $exception->getFile(), 'line' => $exception->getLine(), 'function' => 'break');
        $phpMsg = array();
        foreach ($trace as $error) {
            if (!empty($error['function'])) {
                $fun = '';
                if (!empty($error['class'])) {
                    $fun .= $error['class'] . $error['type'];
                }
                $fun .= $error['function'] . '(';
                if (!empty($error['args'])) {
                    $mark = '';
                    foreach ($error['args'] as $arg) {
                        $fun .= $mark;
                        if (is_array($arg)) {
                            $fun .= 'Array';
                        } elseif (is_bool($arg)) {
                            $fun .= $arg ? 'true' : 'false';
                        } elseif (is_int($arg)) {
                            $fun .= $arg;
                        } elseif (is_float($arg)) {
                            $fun .= $arg;
                        } else {
                            $fun .= $this->clear($arg);
                        }
                        $mark = ', ';
                    }
                }
                $fun .= ')';
                $error['function'] = $fun;
            }
            if (!isset($error['line'])) {
                continue;
            }
            $phpMsg[] = array('file' => $error['file'], 'line' => $error['line'], 'function' => $error['function']);
        }
        $this->showError($type, $errorMsg, $phpMsg);
    }

    public function writeErrorLog($message) {
        return false; // 暂时不写入
    }

    public function clear($message) {
        if (defined('ERRD') && ERRD) {
            return $message;
        } else {
            return htmlspecialchars(substr(str_replace(array("t", "r", "n"), " ", $message), 0, 10)) . (strlen($message) > 10 ? ' ...' : '') . "'";
        }
    }

    /**
     * 显示错误
     *
     * @static
     * @access public
     * @param string $type 错误类型 db,system
     * @param string $errorMsg
     * @param string $phpMsg
     */
    public static function showError($type, $errorMsg, $phpMsg = '') {
        ob_get_length() && ob_end_clean();

        $title = $type ? $type : 'System';
        echo <<<EOT
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
 <title>$title</title>
 <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
 <meta name="ROBOTS" content="NOINDEX,NOFOLLOW,NOARCHIVE" />
 <style type="text/css">
 body { background-color: white; color: black; font-size: 9pt/11pt; font-family: "ff-tisa-web-pro-1", "ff-tisa-web-pro-2", "Helvetica Neue", Helvetica, "Lucida Grande", "Hiragino Sans GB", "Microsoft YaHei", \5fae\8f6f\96c5\9ed1, "WenQuanYi Micro Hei", sans-serif;}
 #container {margin: 10px;}
 #message {width: 1024px; color: black;}
 .red {color: red;}
 a:link {font: 9pt/11pt verdana, arial, sans-serif; color: red;}
 a:visited {font: 9pt/11pt verdana, arial, sans-serif; color: #4e4e4e;}
 h1 {color: #FF0000; font: 18pt "Verdana"; margin-bottom: 0.5em;}
 .bg1 {background-color: #FFFFCC;}
 .bg2 {background-color: #EEEEEE;}
 .table {background: #AAAAAA; font: 11pt Menlo,Consolas,"Lucida Console"}
 .info {
  background: none repeat scroll 0 0 #F3F3F3;
  border: 0px solid #aaaaaa;
  border-radius: 10px 10px 10px 10px;
  color: #000000;
  font-size: 11pt;
  line-height: 160%;
  margin-bottom: 1em;
  padding: 1em;
 }

 .help {
  background: #F3F3F3;
  border-radius: 10px 10px 10px 10px;
  font: 12px verdana, arial, sans-serif;
  text-align: center;
  line-height: 160%;
  padding: 1em;
 }

 .sql {
  background: none repeat scroll 0 0 #FFFFCC;
  border: 1px solid #aaaaaa;
  color: #000000;
  font: arial, sans-serif;
  font-size: 9pt;
  line-height: 160%;
  margin-top: 1em;
  padding: 4px;
 }
 </style>
</head>
<body>
<div id="container">
<h1>$title</h1>
<div class='info'>$errorMsg</div>
EOT;
        if (!empty($phpMsg)) {
            $str = '<div class="info">';
            $str .= '<p><strong>tracing</strong></p>';
            if (is_array($phpMsg)) {
                $str .= '<table cellpadding="5" cellspacing="1" width="100%" class="table"><tbody>';
                $str .= '<tr class="bg2"><td>No.</td><td>File</td><td>Line</td><td>Code</td></tr>';
                foreach ($phpMsg as $k => $msg) {
                    $k++;
                    $str .= '<tr class="bg1">';
                    $str .= '<td>' . $k . '</td>';
                    $str .= '<td>' . str_replace(dirname(PSROOT), '..', $msg['file']) . '</td>';
                    $str .= '<td>' . $msg['line'] . '</td>';
                    $str .= '<td>' . $msg['function'] . '</td>';
                    $str .= '</tr>';
                }
                $str .= '</tbody></table>';
            } else {
                $str .= '<ul>' . $phpMsg . '</ul>';
            }
            $str .= '</div>';
            echo $str;
        }
        echo <<<EOT
</div>
</body>
</html>
EOT;
    }

}
