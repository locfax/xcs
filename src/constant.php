<?php
/**
 * 网站根目录
 */
defined('APP_ROOT') or define('APP_ROOT', '');
/**
 * 应用关键字
 */
defined('APP_KEY') or define('APP_KEY', 'web');
/**
 * 默认的dsn
 */
defined('APP_DSN') or define('APP_DSN', 'mysql');
/**
 * 是否在命令行运行
 */
defined('APP_CLI') or define('APP_CLI', false);
/**
 * 是否调试
 */
defined('DEBUG') or define('DEBUG', true);
/**
 * 换行
 */
defined('DEBUG_EOL') or define('DEBUG_EOL', PHP_OS == 'Linux' ? "\n" : "\r\n");

const XCS_PATH = __DIR__ . '/';  //框架路径
const APP_PATH = APP_ROOT . '/app/'; //应用路径
const DATA_PATH = APP_ROOT . '/runtime/'; //全局数据路径
const LIB_PATH = APP_PATH . 'Library/'; //module路径

const DATA_CACHE = DATA_PATH . 'cache/'; //缓存路径
const DATA_VIEW = DATA_PATH . 'tplcache/'; //模板解析路径
const DATA_LANG = APP_ROOT . '/themes/lang/'; //语言包
const DATA_TPLDIR = APP_ROOT . '/themes/' . APP_KEY . '/'; //模板路径

const FILE_READ_MODE = 0644;
const FILE_WRITE_MODE = 0755;
const DIR_READ_MODE = 0644;
const DIR_WRITE_MODE = 0755;
