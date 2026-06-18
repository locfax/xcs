<?php

namespace Xcs;

use Error;

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
    public function init(string $controllerName, string $actionName): mixed
    {
        $this->_ctl = $controllerName;
        $this->_act = $actionName;
        $this->env();
        return $this->setup();
    }

    private function env(): void
    {
        $this->timestamp = $_SERVER['REQUEST_TIME'] ?: time();
        App::mergeVars('cfg', ['udi' => $this->_ctl . '/' . $this->_act]);
    }

    protected function setup()
    {
        return null;
    }

    /**
     * @param string $name
     * @param mixed $arguments
     * @return array
     */
    public function __call(string $name, mixed $arguments)
    {
        //动作不存在
        if (isAjax()) {
            $res = [
                'code' => 1,
                'message' => $name . ' not exists!',
            ];
            return $this->json($res, 404);
        }

        if (DEBUG) {
            throw new Error($name . " not exists!", 404);
        }

        return $this->html($name . " not exists!", 404);
    }

    protected function get(string $key = '', mixed $default = null): mixed
    {
        if ($key == '') {
            return getgpc('g.*');
        }

        return getgpc('g.' . $key, $default);
    }

    protected function post(string $key = '', mixed $default = null): mixed
    {
        if ($key == '') {
            return getgpc('p.*');
        }

        return getgpc('p.' . $key, $default);
    }

    /**
     * @param String $data
     * @param int $code
     * @return array
     */
    protected function html(string $data, int $code = 200): array
    {
        return ['type' => 'html', 'content' => $data, 'code' => $code];
    }

    /**
     * @param array $data
     * @param int $code
     * @return array
     */
    protected function json(array $data, int $code = 200): array
    {
        return ['type' => 'json', 'content' => json_encode($data, JSON_UNESCAPED_UNICODE), $code => $code];
    }


    /**
     * @param String $data
     * @param int $code
     * @return array
     */
    protected function text(string $data, int $code = 200): array
    {
        return ['type' => 'text', 'content' => $data, 'code' => $code];
    }

}
