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
    }

    /**
     * @param string $name
     * @param mixed $arguments
     * @return bool|void
     */
    public function __call(string $name, mixed $arguments)
    {
        //动作不存在
        if ($this->isAjax()) {
            $res = [
                'code' => 1,
                'message' => $name . ' 不存在!',
            ];
            return $this->json($res);
        }

        throw new \Error($name . " 不存在");
    }

    protected function get($key = null)
    {
        if ($key == null) {
            return getgpc('g.*');
        }
        return getgpc('g.' . $key);
    }

    protected function post($key = null)
    {
        if ($key == null) {
            return getgpc('p.*');
        }
        return getgpc('p.' . $key);
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
        return \Xcs\App::isAjax();
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
}
