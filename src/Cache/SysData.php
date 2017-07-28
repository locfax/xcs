<?php

namespace Xcs\Cache;

class SysData {

    const dsn = 'general';

    public static function lost($cachename, $reset = false) {

        if (!$reset) { //从数据库直接取
            $syscache = \Xcs\DB::dbm(self::dsn)->findOne('common_syscache', '*', array('cname' => 'sys_' . $cachename));
            if ($syscache) {
                \Xcs\Context::cache('set', $syscache['cname'], stripslashes($syscache['data']));
                return $syscache['data'];
            }
        }

        //开始由缓存原始文件直接生成数据
        $cachem = '\\Model\\Cache\\' . ucfirst($cachename);
        $tmp = $cachem::getInstance()->getdata();
        if (!empty($tmp)) { //必须是数组
            $data = \Xcs\Util::output_json($tmp);
        } else {
            $data = '[]'; //标识为空
        }

        //保存到缓存mysql
        self::save('sys_' . $cachename, $data, false);

        //保存缓存到cacher
        \Xcs\Context::cache('set', 'sys_' . $cachename, $data);

        return $data;
    }

    public static function save($cachename, $data, $delcache = true) { //$delcache true 会清理该缓存，在下次需要时自动载入缓存
        if (is_array($data)) {
            $data = \Xcs\Util::output_json($data);
        } else {
            $data = trim($data);
        }
        //缓存入库
        $post = array('cname' => $cachename, 'ctype' => 1, 'dateline' => time(), 'data' => $data);
        \Xcs\DB::dbm(self::dsn)->replace('common_syscache', $post);
        if (!$delcache) {
            return;
        }
        \Xcs\Context::cache('rm', $cachename);
    }

}