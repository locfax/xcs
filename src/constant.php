<?php

define('BASE_PATH', __DIR__ . '/');
define('APP_PATH', APP_ROOT . '/app/'); //app controller应用路径
define('DATA_PATH', APP_ROOT . '/gdata/'); //全局数据路径
define('LIB_PATH', APP_ROOT . '/library/'); //module路径

define('DATA_CACHE', DATA_PATH . 'cache/');
define('DATA_VIEW', DATA_PATH . 'tplcache/');
define('DATA_LANG', APP_ROOT . '/themes/lang/');
define('DATA_TPLDIR', APP_ROOT . '/themes/view/' . APP_KEY . '/'); //模板路径

define('FILE_READ_MODE', 0666); //所有者 所有组 具有读写
define('FILE_WRITE_MODE', 0777); //所有者 所有组 具有读写 执行
define('DIR_READ_MODE', 0666); //所有者 所有组 具有读写
define('DIR_WRITE_MODE', 0777); //所有者 所有组 具有读写 执行
