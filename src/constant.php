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
 * 是否验证角色
 */
defined('AUTH_ROLE') or define('AUTH_ROLE', false);
/**
 * 默认的dsn
*/
defined('APP_DSN') or define('APP_DSN', 'default');

define('BASE_PATH', __DIR__ . '/');  //框架路径
define('APP_PATH', APP_ROOT . '/app/'); //应用路径
define('DATA_PATH', APP_ROOT . '/gdata/'); //全局数据路径
define('LIB_PATH', APP_PATH . 'Library/'); //module路径

define('DATA_CACHE', DATA_PATH . 'cache/'); //缓存路径
define('DATA_VIEW', DATA_PATH . 'tplcache/'); //模板解析路径
define('DATA_LANG', APP_ROOT . '/themes/lang/'); //语言包
define('DATA_TPLDIR', APP_ROOT . '/themes/view/' . APP_KEY . '/'); //模板路径

define('FILE_READ_MODE', 0644);
define('FILE_WRITE_MODE', 0755);
define('DIR_READ_MODE', 0644);
define('DIR_WRITE_MODE', 0755);
