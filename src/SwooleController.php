<?php

namespace Xcs;

use Exception;

class SwooleController
{
    //swoole请求
    protected $request;

    //swoole回应
    protected $response;

    protected string $_ctl;
    protected string $_act;

    // REST允许的请求类型列表
    private array $allow_method = ['get', 'post', 'put', 'delete'];

    public function init($request, $response, $controllerName, $actionName)
    {
        $this->request = $request;
        $this->response = $response;

        $this->_ctl = $controllerName;
        $this->_act = $actionName;
    }

    /**
     * @param $name
     * @param $arguments
     * @throws Exception
     */
    public function __call($name, $arguments)
    {
        if ($this->request->isAjax()) {
            $res = [
                'code' => 1,
                'message' => 'Action ' . $name . '不存在!'
            ];
            $this->json($res);
        } else {
            $this->response('Action ' . $name . '不存在!');
        }
    }

    /**
     * @param String $data
     * @param int $code
     */
    protected function response(string $data = '', int $code = 200): void
    {
        if ($code !== 200) {
            $this->response->status($code, '');
        }
        $this->response->header('Content-Type', 'text/html; charset=UTF-8');
        $this->response->end($data);
    }

    /**
     * @param array $data
     * @param int $code
     */
    protected function json(array $data = [], int $code = 200): void
    {
        if ($code !== 200) {
            $this->response->status($code, '');
        }
        $this->response->header('Content-Type', 'application/json; charset=UTF-8');
        $data = $data ? json_encode($data) : '';
        $this->response->end($data);
    }
}
