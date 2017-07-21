<?php

namespace Controller;

class RestFul extends \Xcs\Controller {

    // 当前请求类型
    private $_method = null;
    //请求附带数据
    private $_data = null;
    // REST允许的请求类型列表
    private $allow_method = ['get', 'post'];
    // REST默认请求类型
    private $default_method = 'get';
    // REST允许输出的资源类型列表
    private $allow_output_type = [
        'json' => 'application/json'
    ];

    public function __construct($controllerName, $actionName) {
        parent::__construct($controllerName, $actionName);
        // 请求方式检测
        $method = strtolower(getgpc('s.REQUEST_METHOD'));
        if (!in_array($method, $this->allow_method)) {
            $method = $this->default_method;
        }
        $this->_method = $method;
        if ($method = 'post') {
            $json = file_get_contents('php://input');
            $this->_data = json_decode($json, true);
        }
    }

    // 发送Http状态信息
    private function http_status($code) {
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
     * 编码数据
     * @access protected
     * @param mixed $data 要返回的数据
     * @param String $type 返回类型 JSON XML
     * @return string
     */
    private function encode_data($data, $type = 'json') {
        if (empty($data)) {
            return '';
        }
        if ('json' == $type) {
            $data = output_json($data);
        }
        return $data;
    }

    /**
     * 设置页面输出的CONTENT_TYPE和编码
     * @access public
     * @param string $type content_type 类型对应的扩展名
     * @param string $charset 页面输出编码
     * @return void
     */
    private function content_type($type, $charset = '') {
        if (headers_sent()) {
            return;
        }
        if (empty($charset)) {
            $charset = getini('site/charset');
        }
        $type = strtolower($type);
        if (isset($this->allow_output_type[$type])) { //过滤content_type
            header('Content-Type: ' . $this->allow_output_type[$type] . '; charset=' . $charset);
        }
    }

    protected function data() {
        return $this->_data;
    }

    /**
     * 输出返回数据
     * @access protected
     * @param mixed $data 要返回的数据
     * @param String $type 返回类型 JSON XML
     * @param integer $code HTTP状态
     * @return void
     */
    protected function response($data, $code = 200) {
        $type = 'json';
        $this->http_status($code);
        $this->content_type($type);
        echo $this->encode_data($data, $type);
    }

}
