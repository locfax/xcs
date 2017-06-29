<?php

namespace Xcs\Helper;

class Log {

    const maxsize = 1024000; //最大文件大小1M

    //写入日志
    public static function writeLog($filename, $msg) {
        $res = array();
        $res['msg'] = $msg;
        $res['logtime'] = date("Y-m-d H:i:s", time());

        //如果日志文件超过了指定大小则备份日志文件
        if (file_exists($filename) && (abs(filesize($filename)) > self::maxsize)) {
            $newfilename = dirname($filename) . '/' . time() . '-' . basename($filename);
            rename($filename, $newfilename);
        }

        //如果是新建的日志文件，去掉内容中的第一个字符逗号
        if (file_exists($filename) && abs(filesize($filename)) > 0) {
            $content = "," . json_encode($res);
        } else {
            $content = json_encode($res);
        }

        //往日志文件内容后面追加日志内容
        file_put_contents($filename, $content, FILE_APPEND);
    }

    //读取日志
    public static function readLog($filename) {
        if (file_exists($filename)) {
            $content = file_get_contents($filename);
            $json = json_decode('[' . $content . ']', true);
        } else {
            $json = '{"msg":"The file does not exist."}';
        }
        return $json;
    }
}