<?php

namespace Xcs;

use Exception;

class ExException extends Exception
{

    public function __construct($code = 0, $message = '', $file = '', $line = 0, $type = 'Exception')
    {
        parent::__construct($message, intval($code));
        set_exception_handler(function () {
            //不用自带的显示异常
        });
        $this->exception($file, $line, $type);
    }

    /**
     * @param $file
     * @param $line
     * @param string $title
     */
    public function exception($file, $line, $title)
    {
        $errorMsg = $this->getMessage();
        $trace = $this->getTrace();
        krsort($trace);

        $trace[] = ['file' => $file, 'line' => $line, 'function' => 'break'];

        $phpMsg = [];
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
                $error['line'] = '';
                $error['file'] = '';
            }
            $phpMsg[] = ['file' => $error['file'], 'line' => $error['line'], 'function' => $error['function']];
        }
        $this->showError($title, $errorMsg, $phpMsg);
    }

    public function writeErrorLog()
    {
        return false; // 暂时不写入
    }

    public function clear($message)
    {
        if (defined('DEBUG') && DEBUG) {
            return is_object($message) ? '#object#' : htmlspecialchars($message);
        }
        return htmlspecialchars($message);
    }

    /**
     * 显示错误
     *
     * @param string $title 错误类型 db,system
     * @param string $errorMsg
     * @param mixed $phpMsg
     */
    public static function showError($title, $errorMsg, $phpMsg = '')
    {
        ob_get_length() && ob_end_clean();

        echo <<<EOT
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
 <title>$title</title>
 <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
 <meta name="ROBOTS" content="NOINDEX,NOFOLLOW,NOARCHIVE" />
 <style type="text/css">
 body { background-color: white; color: black; font-size: 9pt; font-family: "ff-tisa-web-pro-1", "ff-tisa-web-pro-2", "Helvetica Neue", Helvetica, "Lucida Grande", "Hiragino Sans GB", "Microsoft YaHei", \5fae\8f6f\96c5\9ed1, "WenQuanYi Micro Hei", sans-serif;}
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
  font-family: arial, sans-serif;
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
<div class='info'><pre>$errorMsg</pre></div>
EOT;
        if (!empty($phpMsg)) {
            $str = '<div class="info">';
            $str .= '<p><strong>Call Stack</strong></p>';
            if (is_array($phpMsg)) {
                $str .= '<table cellpadding="5" cellspacing="1" width="100%" class="table"><tbody>';
                $str .= '<tr class="bg2"><td>No.</td><td>File</td><td>Line</td><td>Code</td></tr>';
                foreach ($phpMsg as $k => $msg) {
                    $k++;
                    $str .= '<tr class="bg1">';
                    $str .= '<td>' . $k . '</td>';
                    $str .= '<td>' . str_replace(dirname(APP_ROOT), '..', $msg['file']) . '</td>';
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
