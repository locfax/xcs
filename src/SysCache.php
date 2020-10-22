<?php

namespace Xcs;

class SysCache
{

    public static $dsn = 'general';

    //加载系统级别缓存
    public static function loadcache($cachename, $reset = false)
    {
        if (!$cachename) {
            return null;
        }

        $data = self::data($cachename, $reset);

        return json_decode($data, true);
    }

    /**
     * 系统级别缓存数据
     * @param $cachename
     * @param $reset
     * @return array|string
     */

    public static function data($cachename, $reset = false)
    {
        $lost = null;
        if ($reset) {
            $lost = $cachename; //强制设置为没取到
            $data = '[]';
        } else {
            $data = Cache::get('sys_' . strtolower($cachename));
            if (!$data) {
                $lost = $cachename;  //未取到数据
            }
        }

        if (is_null($lost)) {
            return $data; //取到全部数据 则返回
        }

        return self::lost($lost, $reset);
    }

    public static function lost($cachename, $reset = false)
    {
        if (!$reset) { //允许从数据库直接获取
            $syscache = DB::dbm(self::$dsn)->findOne('syscache', '*', ['cname' => 'sys_' . strtolower($cachename)]);
            if ($syscache) {
                Cache::set($syscache['cname'], stripslashes($syscache['data']));
                return $syscache['data'];
            }
        }

        //开始由缓存原始文件直接生成数据
        $cachem = '\\Model\\Cache\\' . ucfirst($cachename);
        $tmp = $cachem::getInstance()->getdata();
        if (!empty($tmp) && is_array($tmp)) {
            $data = App::output_json($tmp);
        } else {
            $data = '[]'; //标识为空
        }

        //保存到缓存mysql
        self::save('sys_' . strtolower($cachename), $data, false);

        //保存缓存到cacher
        Cache::set('sys_' . strtolower($cachename), $data);

        return $data;
    }

    public static function save($cachename, $data, $delcache = true)
    {
        //$delcache true 会清理该缓存，在下次需要时自动载入缓存
        if (is_array($data)) {
            $data = App::output_json($data);
        }

        //缓存入库
        $post = ['cname' => $cachename, 'ctype' => 1, 'dateline' => time(), 'data' => $data];
        DB::dbm(self::$dsn)->replace('syscache', $post);
        if (!$delcache) {
            return;
        }

        Cache::rm($cachename);
    }
}