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
     * @param string $controllerName
     * @param string $actionName
     */
    public function __construct(string $controllerName, string $actionName)
    {
        $this->_ctl = $controllerName;
        $this->_act = $actionName;
        $this->env();
        $this->init();
    }

    /**
     * @param string $name
     * @param mixed $arguments
     * @return bool|void
     */
    public function __call(string $name, $arguments)
    {
        //动作不存在
        if (App::isAjax()) {
            $res = [
                'code' => 1,
                'msg' => 'Action ' . $name . '不存在!',
            ];
            return App::response($res);
        }
        $message = 'Action:' . $name . "不存在";
        ExUiException::render('控制器', $message, '', 0, false);
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
        App::mergeVars('cfg', ['udi' => $this->_ctl . '/' . $this->_act]);
    }

    /**
     * 权限验证
     * @param string $controllerName
     * @param string $actionName
     * @param mixed $auth
     * @return bool
     */
    final function checkAcl(string $controllerName, string $actionName, $auth = AUTH_ROLE): bool
    {
        return Rbac::check($controllerName, $actionName, $auth);
    }

}
