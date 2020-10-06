<?php

namespace Xcs;

class App
{

    const _dCTL = 'c';
    const _dACT = 'a';
    const _controllerPrefix = 'Controller\\';
    const _actionPrefix = 'act_';

    static $routes = null;

    /**
     * @param bool $refresh
     */
    public static function run($refresh = false)
    {
        if (!defined('APP_KEY')) {
            exit('APP_KEY not defined!');
        }
        $preload = [APP_ROOT . '/config/' . APP_KEY . '.inc.php']; //应用配置
        self::runFile($preload, $refresh);
        if (isset($_GET['s'])) {
            $uri = trim(str_replace(['.htm', '.html'], '', $_GET['s']), '/');
        } else {
            $uri = $_SERVER['PHP_SELF'];
        }
        self::dispatching($uri);
    }

    /**
     * @param $preload
     * @param bool $refresh
     */
    public static function runFile($preload, $refresh = false)
    {
        $files = [
            APP_ROOT . '/config/database.php', //数据库配置
            BASE_PATH . 'common.php'
        ];
        if (defined('DEBUG') && DEBUG) {
            //测试模式
            set_error_handler(function ($errno, $errStr, $errFile = null, $errLine = null) {
                try {
                    throw new Exception\ExException($errStr, $errno);
                } catch (Exception\ExException $exception) {

                }
            });

            define('E_FATAL', E_ERROR | E_USER_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_RECOVERABLE_ERROR | E_PARSE);
            register_shutdown_function(function () {
                $error = error_get_last();
                if ($error && ($error["type"] === ($error["type"] & E_FATAL))) {
                    $errno = $error["type"];
                    $errStr = $error["message"];
                    try {
                        throw new Exception\ExException($errStr, $errno, 'systemError');
                    } catch (Exception\ExException $exception) {

                    }
                }
            });

            $files = array_merge($files, $preload);
            foreach ($files as $file) {
                include($file);
            }
        } else {
            //部署模式
            self::_runFile($preload, $files, $refresh);
        }
        self::rootNamespace('\\', APP_PATH);
    }

    /**
     * @param array $preload
     * @param array $files
     * @param bool $refresh
     */
    public static function _runFile($preload, $files, $refresh = false)
    {
        $preloadFile = DATA_PATH . 'preload/runtime_' . APP_KEY . '_files.php';
        if (!is_file($preloadFile) || $refresh) {
            $files = array_merge($files, $preload);
            $preloadFile = self::makeRunFile($files, $preloadFile);
        }
        $preloadFile && require($preloadFile);
    }

    /**
     * @param $runtimeFiles
     * @param $runFile
     * @return bool
     */
    public static function makeRunFile($runtimeFiles, $runFile)
    {
        $content = '';
        foreach ($runtimeFiles as $filename) {
            $data = php_strip_whitespace($filename);
            $content .= str_replace(['<?php', '?>', '<php_', '_php>'], ['', '', '<?php', '?>'], $data);
        }
        $fileDir = dirname($runFile);
        if (!is_dir($fileDir)) {
            mkdir($fileDir, FILE_WRITE_MODE);
        }
        if (!is_file($runFile)) {
            file_exists($runFile) && unlink($runFile); //可能是异常文件 删除
            touch($runFile) && chmod($runFile, 0777); //生成全读写空文件
        } elseif (!is_writable($runFile)) {
            chmod($runFile, FILE_WRITE_MODE); //全读写
        }
        $ret = file_put_contents($runFile, '<?php ' . $content, LOCK_EX);
        if ($ret) {
            return $runFile;
        }
        return false;
    }

    /**
     * @param string $uri
     * @return bool
     */
    public static function dispatching($uri)
    {
        if (defined('ROUTE') && ROUTE) {
            self::router($uri);
        }
        $_controllerName = getgpc('g.' . self::_dCTL, getini('site/defaultController'), 'strtolower');
        $_actionName = getgpc('g.' . self::_dACT, getini('site/defaultAction'), 'strtolower');
        $controllerName = preg_replace('/[^a-z0-9_]+/i', '', $_controllerName);
        $actionName = preg_replace('/[^a-z0-9_]+/i', '', $_actionName);
        if (defined('AUTH_ROLE') && AUTH_ROLE) {
            $ret = Rbac::check($controllerName, $actionName, AUTH_ROLE);
            if (!$ret) {
                $args = '没有权限访问 : ' . $controllerName . ' - ' . $actionName;
                return self::errACL($args);
            }
        }
        self::executeAction($controllerName, $actionName);
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }

    /**
     * @param $controllerName
     * @param $actionName
     * @return bool
     */
    public static function executeAction($controllerName, $actionName)
    {
        $controllerName = ucfirst($controllerName);
        $actionMethod = self::_actionPrefix . $actionName;
        do {
            $controllerClass = self::_controllerPrefix . $controllerName;
            //主动载入controller
            if (!self::_loadController($controllerName, $controllerClass)) {
                break;
            }
            $controller = new $controllerClass($controllerName, $actionName);
            if (!$controller instanceof $controllerClass) {
                break;
            }
            call_user_func([$controller, $actionMethod]);
            $controller = null;
            return true;
        } while (false);
        //控制器加载失败
        self::errACT("The controller '" . $controllerName . '\' is not exists!');
    }

    /**
     * @param $group
     * @param null $vars
     * @return mixed
     */
    public static function mergeVars($group, $vars = null)
    {
        static $_CDATA = [APP_KEY => ['dsn' => null, 'cfg' => null, 'data' => null]];
        $appKey = APP_KEY;
        if (is_null($vars)) {
            return $_CDATA[$appKey][$group];
        }
        if (is_null($_CDATA[$appKey][$group])) {
            $_CDATA[$appKey][$group] = $vars;
        } else {
            $_CDATA[$appKey][$group] = array_merge($_CDATA[$appKey][$group], $vars);
        }
        return true;
    }

    /**
     * @param $args
     * @return bool
     */
    private static function errACT($args)
    {
        if (self::isAjax(true)) {
            $res = [
                'errcode' => 1,
                'errmsg' => '出错了！' . $args,
                'data' => ''
            ];
            return \Xcs\App::response($res, 'json');
        }
        $args = '出错了！' . $args;
        include template('404');
    }

    /**
     * @param $args
     * @return bool
     */
    private static function errACL($args)
    {
        if (self::isAjax(true)) {
            $res = [
                'errcode' => 1,
                'errmsg' => '出错了！' . $args,
                'data' => ''
            ];
            return \Xcs\App::response($res, 'json');
        }
        $args = '出错了！' . $args;
        include template('403');
    }

    /**
     * @param $controllerName
     * @param $controllerClass
     * @return bool
     */
    private static function _loadController($controllerName, $controllerClass)
    {
        if (class_exists($controllerClass, false) || interface_exists($controllerClass, false)) {
            return true;
        };
        $controllerFilename = APP_PATH . 'Controller/' . ucfirst(APP_KEY) . '/' . $controllerName . '.php';
        return is_file($controllerFilename) && include $controllerFilename;
    }

    /**
     * @param $uri
     * @return bool|void
     */
    public static function router($uri)
    {
        if (!$uri) {
            return;
        }
        if (strpos($uri, 'index.php') != false) {
            $uri = substr($uri, strpos($uri, 'index.php') + 10);
        }
        if (!self::$routes) {
            self::$routes = include(APP_ROOT . '/route/' . APP_KEY . '.php');
        }
        foreach (self::$routes as $key => $val) {
            $key = str_replace([':any', ':num'], ['[^/]+', '[0-9]+'], $key);
            if (preg_match('#^' . $key . '$#', $uri, $matches)) {
                if (strpos($val, '$') !== false && strpos($key, '(') !== false) {
                    $val = preg_replace('#^' . $key . '$#', $val, $uri);
                }
                $req = explode('/', $val);
                self::setRequest($req);
                break;
            }
        }
    }

    /**
     * @param $req
     */
    private static function setRequest($req)
    {
        $_GET[self::_dCTL] = array_shift($req);
        $_GET[self::_dACT] = array_shift($req);
        $paramNum = count($req);
        if (!$paramNum) {
            return;
        }
        for ($i = 0; $i < $paramNum; $i++) {
            $_GET[$req[$i]] = $req[$i + 1];
            $i++;
        }
    }

    /**
     * @param $namespace
     * @param $path
     */
    public static function rootNamespace($namespace, $path)
    {
        $namespace = trim($namespace, '\\');
        $path = rtrim($path, '/');
        $loader = function ($classname) use ($namespace, $path) {
            if ($namespace && stripos($classname, $namespace) !== 0) {
                return;
            }
            $file = trim(substr($classname, strlen($namespace)), '\\');
            $file = $path . '/' . str_replace('\\', '/', $file) . '.php';
            include $file;
        };
        spl_autoload_register($loader);
    }

    /**
     * 导入所需的类库
     * @param string $class 类库命名空间字符串
     * @param string $baseUrl 起始路径
     * @param string $ext 导入的文件扩展名
     * @return boolean
     */
    public static function vendor($class, $ext = '.php', $baseUrl = LIB_PATH)
    {
        static $_file = [];
        $key = $class . $baseUrl . $ext;
        $class = str_replace(['.', '#'], ['/', '.'], $class);

        if (isset($_file[$key])) { //如果已经include过，不需要再次载入
            return true;
        }

        // 如果类存在 则导入类库文件
        $filename = $baseUrl . $class . $ext;

        if (!empty($filename) && is_file($filename)) {
            // 开启调试模式Win环境严格区分大小写
            if (defined('DEBUG') && DEBUG && pathinfo($filename, PATHINFO_FILENAME) != pathinfo(realpath($filename), PATHINFO_FILENAME)) {
                return false;
            }
            include $filename;
            $_file[$key] = true;
            return true;
        }
        return false;
    }

    /**
     * @param $arr
     * @return string
     */
    public static function output_json($arr)
    {
        if (version_compare(PHP_VERSION, '5.4', '>=')) {
            return json_encode($arr, JSON_UNESCAPED_UNICODE);
        }
        $json = json_encode(self::urlencode($arr));
        return urldecode($json);
    }

    public static function output_nocache()
    {
        header("Expires: -1");
        header("Cache-Control: no-store, private, post-check=0, pre-check=0, max-age=0", false);
        header("Pragma: no-cache");
    }

    /**
     * @param bool $nocache
     */
    public static function output_start($nocache = true)
    {
        ob_end_clean();
        if (getini('site/gzip') && function_exists('ob_gzhandler')) { //whether start gzip
            ob_start('ob_gzhandler');
        } else {
            ob_start();
        }
        if ($nocache) {
            self::output_nocache();
        }
    }

    /**
     * @param bool $echo
     * @return mixed|string
     */
    public static function output_end($echo = false)
    {
        $content = ob_get_contents();
        ob_get_length() && ob_end_clean();
        $content = preg_replace("/([\\x01-\\x08\\x0b-\\x0c\\x0e-\\x1f])+/", ' ', $content);
        $content = str_replace([chr(0), ']]>'], [' ', ']]&gt;'], $content);
        if ($echo) {
            echo $content;
        } else {
            return $content;
        }
    }

    /**
     * @param $res
     * @param string $type
     * @return bool
     */
    public static function response($res, $type = 'json')
    {
        self::output_start();
        if ('html' == $type) {
            header("Content-type: text/html; charset=UTF-8");
        } elseif ('json' == $type) {
            header('Content-type: text/json; charset=UTF-8');
            $res = self::output_json($res);
        } elseif ('xml' == $type) {
            header("Content-type: text/xml");
            $res = '<?xml version="1.0" encoding="utf-8"?' . '>' . "\r\n" . '<root><![CDATA[' . $res;
        } elseif ('text' == $type) {
            header("Content-type: text/plain");
        } else {
            header("Content-type: text/html; charset=UTF-8");
        }
        echo $res;
        self::output_end(true);
        if ('xml' == $type) {
            echo ']]></root>';
        }
        return true;
    }


    /**
     * @param bool $retBool
     * @return bool
     */
    public static function isPost($retBool = true)
    {
        if ('POST' == getgpc('s.REQUEST_METHOD')) {
            return $retBool;
        }
        return !$retBool;
    }

    /**
     * @param bool $retBool
     * @return bool
     */
    public static function isAjax($retBool = true)
    {
        if ('XMLHttpRequest' == getgpc('s.HTTP_X_REQUESTED_WITH')) {
            return $retBool;
        }
        return !$retBool;
    }

    /**
     * @param $_string
     * @param bool $replace
     * @param int $http_response_code
     * @return bool
     */
    public static function header($_string, $replace = true, $http_response_code = 0)
    {
        $string = str_replace(["\r", "\n"], ['', ''], $_string);
        if (!$http_response_code) {
            header($string, $replace);
        } else {
            header($string, $replace, $http_response_code);
        }
        return true;
    }

    /**
     * @param $arr
     * @return string
     */
    public static function implode($arr)
    {
        return "'" . implode("','", (array)$arr) . "'";
    }

    /**
     * @param $str
     * @param $needle
     * @return bool
     */
    public static function strPos($str, $needle)
    {
        return !(false === strpos($str, $needle));
    }

    /**
     * @param string $default
     * @return string
     */
    public static function referer($default = '')
    {
        $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        if (empty($referer)) {
            $referer = $default;
        }
        return strip_tags($referer);
    }

    /**
     * @param $value
     * @return array|string
     */
    public static function urlencode($value)
    {
        if (is_array($value)) {
            return array_map('self::urlencode', $value);
        }
        return $value ? urlencode($value) : $value;
    }

    /**
     * @param string $message
     * @param string $after_action
     * @param string $url
     * @return bool
     */
    public static function js_alert($message = '', $after_action = '', $url = '')
    { //php turn to alert
        $out = "<script language=\"javascript\" type=\"text/javascript\">\n";
        if (!empty($message)) {
            $out .= "alert(\"";
            $out .= str_replace("\\\\n", "\\n", str_replace(["\r", "\n"], ['', '\n'], $message));
            $out .= "\");\n";
        }
        if (!empty($after_action)) {
            $out .= $after_action . "\n";
        }
        if (!empty($url)) {
            $out .= "document.location.href=\"";
            $out .= $url;
            $out .= "\";\n";
        }
        $out .= "</script>";
        echo $out;
        return true;
    }

    /**
     * @param $url
     * @param int $delay
     * @param bool $js
     * @param bool $jsWrapped
     * @param bool $return
     * @return bool|null|string
     */
    public static function redirect($url, $delay = 0, $js = false, $jsWrapped = true, $return = false)
    {
        $_delay = intval($delay);
        if (!$js) {
            if (headers_sent() || $_delay > 0) {
                echo <<<EOT
    <html>
    <head>
    <meta http-equiv="refresh" content="{$_delay};URL={$url}" />
    </head>
    </html>
EOT;
            } else {
                header("Location: {$url}");
            }
            return null;
        }

        $out = '';
        if ($jsWrapped) {
            $out .= '<script language="javascript" type="text/javascript">';
        }
        if ($_delay > 0) {
            $out .= "window.setTimeout(function () { document.location='{$url}'; }, {$_delay});";
        } else {
            $out .= "document.location='{$url}';";
        }
        if ($jsWrapped) {
            $out .= '</script>';
        }
        if ($return) {
            return $out;
        }
        echo $out;
        return true;
    }

    /**
     * @return null
     */
    public static function clientIp()
    {
        $onlineIp = '';
        if (getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
            $onlineIp = getenv('HTTP_CLIENT_IP');
        } elseif (getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
            $onlineIp = getenv('HTTP_X_FORWARDED_FOR');
        } elseif (getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
            $onlineIp = getenv('REMOTE_ADDR');
        } elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
            $onlineIp = $_SERVER['REMOTE_ADDR'];
        }
        return $onlineIp;
    }

    /**
     * @return array|false|string
     */
    public static function client_ip()
    {
        $onlineIp = '';
        if (getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
            $onlineIp = getenv('REMOTE_ADDR');
        } elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
            $onlineIp = $_SERVER['REMOTE_ADDR'];
        } elseif (getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
            $onlineIp = getenv('HTTP_CLIENT_IP');
        } elseif (getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
            $onlineIp = getenv('HTTP_X_FORWARDED_FOR');
        }
        return $onlineIp;
    }
}