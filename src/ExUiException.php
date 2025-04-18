<?php

namespace Xcs;

class ExUiException
{
    /**
     * @param string $title
     * @param string $message
     * @param string $file
     * @param int $line
     * @param bool $Trace
     * @param mixed $ex
     */
    public static function render(string $title, string $message, string $file, int $line, bool $Trace = false, mixed $ex = null)
    {
        $phpMsg = [];
        if ($Trace) {
            $trace = $ex->getTrace();
            krsort($trace);
            $trace[] = ['file' => $file, 'line' => $line, 'function' => 'break'];
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
                                $fun .= self::clear($arg);
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
                    $error['function'] = '<pre>' . $error['function'] . '</pre>';
                }
                $phpMsg[] = ['file' => $error['file'], 'line' => $error['line'], 'function' => $error['function']];
            }
        }
        return self::showError($title, $message, $phpMsg);
    }

    /**
     * @param mixed $message
     * @return mixed
     */
    public static function clear(mixed $message): mixed
    {
        if (DEBUG) {
            return is_object($message) ? get_class($message) : ($message ? htmlspecialchars($message) : '');
        }
        return $message ? htmlspecialchars($message) : '';
    }

    /**
     * 显示错误
     * @param string $title 错误类型 db,system
     * @param string $message
     * @param mixed $phpMsg
     */
    public static function showError(string $title, string $message, mixed $phpMsg = '')
    {
        $header = <<<EOT
<!DOCTYPE html>
<html>
<head>
 <title>$title</title>
 <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
 <style type="text/css">
 body { background-color: white; color: black; font-size: 9pt; font-family: "Microsoft YaHei", sans-serif;}
 #container {margin: 10px;}
 #message {width: 1024px; color: black;}
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
 </style>
</head>
<body>
<div id="container">
<h1>$title</h1>
<div class='info'><pre>$message</pre></div>
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
                    if (!empty($msg['function'])) {
                        $str .= '<td>' . $msg['function'] . '</td>';
                    } else {
                        $str .= '<td>0</td>';
                    }
                    $str .= '</tr>';
                }
                $str .= '</tbody></table>';
            } else {
                $str .= '<ul>' . $phpMsg . '</ul>';
            }
            $str .= '</div>';
            $body = $str;
        } else {
            $body = '';
        }
        $footer = <<<EOT
</div>
</body>
</html>
EOT;
        return $header . $body . $footer;
    }
}