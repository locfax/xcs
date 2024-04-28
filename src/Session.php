<?php

namespace Xcs;

class Session
{
    private int $ttl;
    private $handle;
    private $prefix;

    public function start($prefix = "session:", $time = 1800): void
    {
        $this->prefix = $prefix;
        $this->ttl = $time;
        session_module_name(getini('auth/handle')); //session保存方式
        session_set_save_handler(
            [&$this, '_open'], //在运行session_start()时执行
            [&$this, '_close'], //在脚本执行完成或调用session_write_close() 或 session_destroy()时被执行,即在所有session操作完后被执行
            [&$this, '_read'], //在运行session_start()时执行,因为在session_start时,会去read当前session数据
            [&$this, '_write'], //此方法在脚本结束和使用session_write_close()强制提交SESSION数据时执行
            [&$this, '_destroy'], //在运行session_destroy()时执行
            [&$this, '_gc'] //redis 设置了ttl 会自动销毁, 所以gc里不做任何操作
        );
    }

    /**
     * @throws ExException
     */
    private function connect(): void
    {
        if (!$this->handle) {
            $_handle = '\\Xcs\\Cache\\' . ucfirst(getini('auth/handle'));
            $this->handle = $_handle::getInstance()->init(Context::dsn('session'));
        }
    }

    /**
     * @throws ExException
     */
    public function _open(): bool
    {
        $this->connect();
        return true;
    }

    public function _close(): bool
    {
        return true;
    }

    public function _read($id): string
    {
        $id = $this->prefix . $id;
        $data = $this->handle->get($id);
        if ($data !== false) {
            $this->ttl && $this->handle->set($id, $data, $this->ttl); //重新设置ttl 防止超时
            return $data;
        }
        return '';
    }

    public function _write($id, $data): bool
    {
        $this->handle->set($this->prefix . $id, $data, $this->ttl);
        return true;
    }

    public function _destroy($id): bool
    {
        $this->handle->rm($this->prefix . $id);
        return true;
    }

    public function _gc(): bool
    {
        return true;
    }

}
