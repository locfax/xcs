<?php

namespace Xcs;

use Exception;

class SwooleController
{
    //swoole请求
    protected $request;
    //swoole回应
    protected $response;
    //时间戳
    protected int $timestamp;

    protected string $_ctl;
    protected string $_act;

    public function init($request, $response, $controllerName, $actionName)
    {
        $this->request = $request;
        $this->response = $response;

        $this->_ctl = $controllerName;
        $this->_act = $actionName;
        $this->env();

        $result = $this->setup();
        if ($result) {
            return $result;
        }
    }

    /**
     * @param $name
     * @param $arguments
     * @throws Exception
     */
    public function __call($name, $arguments)
    {
        if ($this->isAjax()) {
            $res = [
                'code' => 1,
                'message' => $name . '不存在!'
            ];
            return $this->json($res);
        }

        if (DEBUG) {
            return $this->response($name . '不存在!');
        }

        return '';
    }

    protected function get($key = null)
    {
        if ($key == null) {
            return $this->request->get;
        }
        return $this->request->get[$key] ?? '';
    }

    protected function post($key = null)
    {
        if ($key == null) {
            return $this->request->post;
        }
        return $this->request->post[$key] ?? '';
    }

    /**
     * @param String $data
     * @param int $code
     */
    protected function response(string $data = '')
    {
        return ['type' => 'text', 'content' => $data];
    }

    /**
     * @param array $data
     * @param int $code
     */
    protected function json(array $data = [])
    {
        return ['type' => 'json', 'content' => json_encode($data)];
    }

    protected function isAjax()
    {
        $val = $this->request->header['x-requested-with'] ?? '';
        return $val && ($val === 'xmlhttprequest');
    }

    /**
     * 初始变量
     */
    private function env(): void
    {
        $this->timestamp = $this->request->server['request_time'] ?? time();
        App::mergeVars('cfg', ['udi' => strtolower($this->_ctl) . '/' . $this->_act]);
    }

    protected function setup()
    {
        return null;
    }
}
