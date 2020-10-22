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
     */
    public function __construct($controllerName, $actionName)
    {
        $this->_ctl = $controllerName;
        $this->_act = $actionName;
        $this->init_var();
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
            $retarr = [
                'errcode' => 1,
                'errmsg' => 'Action ' . $name . '不存在!',
                'data' => ''
            ];
            return App::response($retarr);
        }
        $args = 'Action:' . $name . "不存在";
        include template('404');
    }

    /**
     * 初始变量
     */
    private function init_var()
    {
        $this->timestamp = getgpc('s.REQUEST_TIME') ?: time();
        if (filter_input(INPUT_GET, 'page')) {
            $_GET['page'] = max(1, filter_input(INPUT_GET, 'page'));
        }
    }

    /**
     * 权限验证
     */
    final function checkacl($controllerName, $actionName, $auth = AUTH_ROLE)
    {
        return Rbac::check($controllerName, $actionName, $auth);
    }

}
