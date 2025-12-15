<?php

namespace Xcs;

class Controller
{

    //当前控制器
    protected string $_ctl;
    //当前动作
    protected string $_act;
    //时间戳
    protected int $timestamp;

    /**
     * 初始执行
     * @param string $controllerName
     * @param string $actionName
     * @return mixed
     */
    public function init(string $controllerName, string $actionName)
    {
        $this->_ctl = $controllerName;
        $this->_act = $actionName;
        $this->env();
        $result = $this->setup();
        if ($result) {
            return $result;
        }
        return '';
    }

    /**
     * @param string $name
     * @param mixed $arguments
     * @return array
     */
    public function __call(string $name, $arguments)
    {
        //动作不存在
        if ($this->isAjax()) {
            $res = [
                'code' => 1,
                'message' => $name . ' 不存在!',
            ];
            return $this->json($res);
        }

        if (DEBUG) {
            throw new \Error($name . " 不存在");
        } else {
            $this->status(301);
            header('Location: /');
            return '';
        }
    }

    protected function get($key = null)
    {
        if ($key == null) {
            return getgpc('g.*');
        }
        return getgpc('g.' . $key, '');
    }

    protected function post($key = null)
    {
        if ($key == null) {
            return getgpc('p.*');
        }
        return getgpc('p.' . $key, '');
    }

    /**
     * @param String $data
     * @return string[]
     */
    protected function response(string $data = '')
    {
        return ['type' => 'text', 'content' => $data];
    }

    /**
     * @param array $data
     * @return array
     */
    protected function json(array $data = [])
    {
        return ['type' => 'json', 'content' => json_encode($data, JSON_UNESCAPED_UNICODE)];
    }

    protected function isAjax()
    {
        return App::isAjax();
    }

    /**
     * 初始变量
     */
    private function env(): void
    {
        $this->timestamp = $_SERVER['REQUEST_TIME'] ?: time();
        App::mergeVars('cfg', ['udi' => strtolower($this->_ctl) . '/' . $this->_act]);
    }

    protected function setup()
    {
        return null;
    }

    /**
     * @param $code
     */
    protected function status($code): void
    {
        static $_status = [
            // Informational 1xx
            100 => 'Continue',
            101 => 'Switching Protocols',
            // Success 2xx
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            // Redirection 3xx
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Moved Temporarily ', // 1.1
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            // 306 is deprecated but reserved
            307 => 'Temporary Redirect',
            // Client Error 4xx
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Request Entity Too Large',
            414 => 'Request-URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Requested Range Not Satisfiable',
            417 => 'Expectation Failed',
            // Server Error 5xx
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported',
            509 => 'Bandwidth Limit Exceeded'
        ];
        if (isset($_status[$code])) {
            header('HTTP/1.1 ' . $code . ' ' . $_status[$code]);
            // 确保FastCGI模式下正常
            header('Status: ' . $code . ' ' . $_status[$code]);
        }
    }

    protected function end(): void
    {

    }
}
