<?php

namespace Xcs;

class RestFul extends \Xcs\Controller
{
    // 当前请求类型
    private $_method = null;
    // REST允许的请求类型列表
    private $allow_method = ['get', 'post', 'put', 'delete'];

    public function __construct($controllerName, $actionName)
    {
        parent::__construct($controllerName, $actionName);
        // 请求方式检测
        $method = strtolower(getgpc('s.REQUEST_METHOD', 'get'));
        if (!in_array($method, $this->allow_method)) {
            $method = 'get';
        }
        $this->_method = $method;
        $this->request();
    }

    /**
     * @param $name
     * @param $arguments
     * @return bool
     */
    public function __call($name, $arguments)
    {
        //动作不存在
        $retarr = array(
            'errcode' => 1,
            'errmsg' => 'Action ' . $name . '不存在!',
        );
        Util::rep_send($retarr);
    }

    /**
     * @return mixed|null
     */
    protected function rawdata()
    {
        return file_get_contents('php://input');
    }

    /**
     * @param $code
     */
    private function http_status($code)
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
            header('Status:' . $code . ' ' . $_status[$code]);
        }
    }

    /**
     * 输出返回数据
     * @access protected
     * @param mixed $data 要返回的数据
     * @param String $type 返回类型 JSON XML
     * @param integer $code HTTP状态
     * @return void
     */
    protected function response($data, $code = 200, $type = "json")
    {
        $this->http_status($code);
        Util::rep_send($data, $type);
    }

    protected function request()
    {
        if ('get' == $this->_method) {
            call_user_func(array($this, '_' . $this->_act . '_get'));
        } elseif ('post' == $this->_method) {
            call_user_func(array($this, '_' . $this->_act . '_post'));
        } elseif ('put' == $this->_method) {
            call_user_func(array($this, '_' . $this->_act . '_put'));
        } elseif ('delete' == $this->_method) {
            call_user_func(array($this, '_' . $this->_act . '_delete'));
        }
    }
}
