<?php

namespace Xcs;

class Session {

    private $ttl;
    private $db;
    private $prefix;

    public function __construct($prefix = "RSID:", $time = 1800) {
        $this->prefix = $prefix;
        $this->ttl = $time;
        //ini_set('session.gc_maxlifetime', 1440);
        session_module_name('user'); //session保存方式, 也可以在Php.ini中设置
        $this->save_handler();
    }

    private function save_handler() {
        session_set_save_handler(
            array(&$this, '_open'), //在运行session_start()时执行
            array(&$this, '_close'), //在脚本执行完成或调用session_write_close() 或 session_destroy()时被执行,即在所有session操作完后被执行
            array(&$this, '_read'), //在运行session_start()时执行,因为在session_start时,会去read当前session数据
            array(&$this, '_write'), //此方法在脚本结束和使用session_write_close()强制提交SESSION数据时执行
            array(&$this, '_destroy'), //在运行session_destroy()时执行
            array(&$this, '_gc') //redis 设置了ttl 会自动销毁, 所以gc里不做任何操作
        );
    }

    private function _open() {
        //在运行session_start()时连接redis数据库
        try {
            $this->db = DB::dbo('redis.cache');
        } catch (\Exception $ex) {

        }
    }

    private function _close() {

    }

    private function _read($id) {
        try {
            $id = $this->prefix . $id;
            $sessData = $this->db->get($id);
            $this->db->expire($id, $this->ttl); //重新设置ttl 防止超时
            return $sessData;
        } catch (\Exception $ex) {

        }
    }

    private function _write($id, $data) {
        try {
            $id = $this->prefix . $id;
            $this->db->set($id, $data);
            $this->db->expire($id, $this->ttl);
        } catch (\Exception $ex) {

        }
    }

    private function _destroy($id) {
        try {
            $this->db->del($this->prefix . $id);
        } catch (\Exception $ex) {

        }
    }

    private function _gc($max) {
        //一般不需要操作什么
    }

}
