<?php

namespace Xcs;

class Controller
{

    //当前控制器
    protected $_ctl;
    //当前动作
    protected $_act;
    //时间戳
    protected $timestamp;

    /**
     * 初始执行
     * @param $controllerName
     * @param $actionName
     */
    public function __construct($controllerName, $actionName)
    {
        $this->_ctl = $controllerName;
        $this->_act = $actionName;
        $this->env();
        $this->init();
    }

    /**
     * @param $name
     * @param $arguments
     * @return bool
     */
    public function __call($name, $arguments)
    {
        //动作不存在
        if (App::isAjax(true)) {
            $res = [
                'code' => 1,
                'msg' => 'Action ' . $name . '不存在!',
            ];
            return App::response($res);
        }
        $args = 'Action:' . $name . "不存在";
        template('404', ['args' => $args]);
    }

    protected function init()
    {

    }

    /**
     * 初始变量
     */
    private function env()
    {
        $this->timestamp = $_SERVER['REQUEST_TIME'] ?: time();
    }

    /**
     * 权限验证
     * @param $controllerName
     * @param $actionName
     * @param bool $auth
     * @return bool
     */
    final function checkAcl($controllerName, $actionName, bool $auth = AUTH_ROLE): bool
    {
        return Rbac::check($controllerName, $actionName, $auth);
    }

}
