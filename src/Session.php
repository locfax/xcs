<?php

namespace Xcs;

class Session
{
    private $ttl;
    private $handle;
    private $prefix;

    public function start($prefix = "session:", $time = 1800)
    {
        $this->prefix = $prefix;
        $this->ttl = $time;
        session_module_name('user'); //session保存方式, 也可以在Php.ini中设置
        session_set_save_handler(
            array(&$this, '_open'), //在运行session_start()时执行
            array(&$this, '_close'), //在脚本执行完成或调用session_write_close() 或 session_destroy()时被执行,即在所有session操作完后被执行
            array(&$this, '_read'), //在运行session_start()时执行,因为在session_start时,会去read当前session数据
            array(&$this, '_write'), //此方法在脚本结束和使用session_write_close()强制提交SESSION数据时执行
            array(&$this, '_destroy'), //在运行session_destroy()时执行
            array(&$this, '_gc') //redis 设置了ttl 会自动销毁, 所以gc里不做任何操作
        );
        $this->connect();
    }

    private function connect()
    {
        if (!$this->handle) {
            $config = Context::dsn('session');
            $handle = getini('auth/handle');
            $handle = '\\Xcs\\Cache\\' . ucfirst($handle);
            $this->handle = $handle::getInstance()->init($config);
        }
    }

    public function _open()
    {

    }

    public function _close()
    {

    }

    public function _read($id)
    {
        $id = $this->prefix . $id;
        $sessData = $this->handle->get($id);
        $this->ttl && $this->handle->set($id, $sessData, $this->ttl); //重新设置ttl 防止超时
        return $sessData;
    }

    public function _write($id, $data)
    {
        $id = $this->prefix . $id;
        $this->handle->set($id, $data, $this->ttl);
    }

    public function _destroy($id)
    {
        $this->handle->rm($this->prefix . $id);
    }

    public function _gc($max)
    {

    }

}
