<?php

defined('APP_ROOT') or define('APP_ROOT', '');
defined('ROUTE') or define('ROUTE', true);
defined('APP_DSN') or define('APP_DSN', 'mysql');
defined('APP_KEY') or define('APP_KEY', 'Web');
defined('DEBUG') or define('DEBUG', true);
defined('DEBUG_EOL') or define('DEBUG_EOL', PHP_OS == 'Linux' ? "\n" : "\r\n");
defined('DS') or define('DS', DIRECTORY_SEPARATOR);

const XCS_PATH = __DIR__ . DS;  //框架路径
const APP_PATH = APP_ROOT . DS . 'app' . DS; //应用路径
const LIB_PATH = APP_PATH . 'Library' . DS; //function路径
const RUNTIME_PATH = APP_ROOT . DS . 'runtime' . DS; //全局数据路径

if (!defined('CACHE_PATH')) {
    define('CACHE_PATH', RUNTIME_PATH . 'cache' . DS); //缓存路径
}

const THEMES_CACHE = RUNTIME_PATH . 'tplcache' . DS; //模板解析路径
const THEMES_VIEW = APP_ROOT . DS . 'themes' . DS . APP_KEY . DS; //模板路径

const FILE_READ_MODE = 0644;
const FILE_WRITE_MODE = 0755;
const DIR_READ_MODE = 0644;
const DIR_WRITE_MODE = 0755;
