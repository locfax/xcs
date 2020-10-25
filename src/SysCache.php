<?php

namespace Xcs;

class SysCache
{

    public static $dsn = 'general';

    //加载系统级别缓存
    public static function loadcache($cacheName, $reset = false)
    {
        if (!$cacheName) {
            return null;
        }

        $data = self::data($cacheName, $reset);

        return json_decode($data, true);
    }

    /**
     * 系统级别缓存数据
     * @param $cacheName
     * @param $reset
     * @return array|string
     */

    public static function data($cacheName, $reset = false)
    {
        $lost = null;
        if ($reset) {
            $lost = $cacheName; //强制设置为没取到
            $data = '[]';
        } else {
            $data = Cache::get('sys_' . strtolower($cacheName));
            if (!$data) {
                $lost = $cacheName;  //未取到数据
            }
        }

        if (is_null($lost)) {
            return $data; //取到全部数据 则返回
        }

        return self::lost($lost, $reset);
    }

    public static function lost($cacheName, $reset = false)
    {
        if (!$reset) { //允许从数据库直接获取
            $syscache = DB::dbm(self::$dsn)->findOne('syscache', '*', ['cname' => 'sys_' . strtolower($cacheName)]);
            if ($syscache) {
                Cache::set($syscache['cname'], stripslashes($syscache['data']));
                return $syscache['data'];
            }
        }

        //开始由缓存原始文件直接生成数据
        $cachem = '\\Model\\Cache\\' . ucfirst($cacheName);
        $tmp = $cachem::getInstance()->getdata();
        if (!empty($tmp) && is_array($tmp)) {
            $data = App::output_json($tmp);
        } else {
            $data = '[]'; //标识为空
        }

        //保存到缓存mysql
        self::save('sys_' . strtolower($cacheName), $data, false);

        //保存缓存到cacher
        Cache::set('sys_' . strtolower($cacheName), $data);

        return $data;
    }

    public static function save($cacheName, $data, $delCache = true)
    {
        //$delcache true 会清理该缓存，在下次需要时自动载入缓存
        if (is_array($data)) {
            $data = App::output_json($data);
        }

        //缓存入库
        $post = ['cname' => $cacheName, 'ctype' => 1, 'dateline' => time(), 'data' => $data];
        DB::dbm(self::$dsn)->replace('syscache', $post);
        if (!$delCache) {
            return;
        }

        Cache::rm($cacheName);
    }
}