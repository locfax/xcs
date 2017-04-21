<?php

//error_reporting(-1);
//ini_set('display_errors', 1);

define('APPKEY', 'api'); //应用关键字 用于识别配置
define('APPNAME', 'API'); //应用名称

define('PSROOT', dirname(__DIR__)); //根目录 protected source所在的目录
define('APPDSN', 'general'); //重要!!默认使用的数据库DSNID
define('ERRD', true); //SQL debug模式 true显示完整信息 false隐藏部分显示

require PSROOT . '/source/app.php'; //功能函数

//预加载自定义函数文件
$preload = array(
    LIBPATH . 'extra/api.php', //业务函数
);
App::runFile($preload);  //非MVC模式启动

$caches = getini('cache/default');
loadcache($caches);
