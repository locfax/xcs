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
     * @return string
     */
    public static function render(string $title, string $message, string $file, int $line, bool $Trace = false, mixed $ex = null): string
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
     * @return string
     */
    public static function clear(mixed $message): string
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
     * @return string
     */
    public static function showError(string $title, string $message, mixed $phpMsg = ''): string
    {
        return '<pre>' . print_r($title, true) . '</pre><pre>' . print_r($message, true) . '</pre><pre>' . print_r($phpMsg, true) . '</pre>';
    }
}