<?php

namespace Xcs;

class App
{

    public static $_dCTL = 'c';
    public static $_dACT = 'a';
    public static $_actionPrefix = 'act_';

    const _controllerPrefix = 'Controller\\';

    private static $routes;

    /**
     * @param bool $refresh
     */
    public static function run($refresh = false)
    {
        if (!defined('APP_KEY')) {
            exit('APP_KEY not defined!');
        }

        if (isset($_GET['s'])) {
            $uri = trim(str_replace(['.htm', '.html'], '', $_GET['s']), '/');
        } else {
            $uri = $_SERVER['PHP_SELF'];
        }

        self::runFile($refresh);
        self::_dispatching($uri);
    }

    /**
     * @param bool $refresh
     */
    public static function runFile($refresh = false)
    {
        self::_rootNamespace('\\', APP_PATH);

        $preloadFile = DATA_PATH . 'preload/runtime_' . APP_KEY . '_files.php';
        if (!is_file($preloadFile) || $refresh) {

            $files = [BASE_PATH . 'common.php', APP_ROOT . '/config/' . APP_KEY . '.inc.php']; //应用配置

            is_file(LIB_PATH . 'function.php') && array_push($files, LIB_PATH . 'function.php');
            is_file(LIB_PATH . APP_KEY . '.php') && array_push($files, LIB_PATH . APP_KEY . '.php');

            is_file(APP_ROOT . '/config/database.php') && array_push($files, APP_ROOT . '/config/database.php');
            is_file(APP_ROOT . '/config/common.php') && array_push($files, APP_ROOT . '/config/common.php');

            if (defined('DEBUG') && DEBUG) {
                define('E_FATAL', E_ERROR | E_USER_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_RECOVERABLE_ERROR | E_PARSE);

                set_error_handler(function ($errno, $errStr, $errFile = '', $errLine = 0) {
                    new ExException($errStr, $errno, $errFile, $errLine, '语法解析错误');
                });
                register_shutdown_function(function () {
                    $error = error_get_last();
                    if ($error && ($error["type"] === ($error["type"] & E_FATAL))) {
                        new ExException($error['message'], $error['type'], $error['file'], $error['line'], '致命错误');
                    }
                });
                set_exception_handler(function ($e) {
                    new ExException($e->getMessage(), $e->getCode(), $e->getFile(), $e->getLine(), 'Exception');
                });
                array_walk($files, function ($file) {
                    include $file;
                });
                return;
            }

            !is_dir(DATA_PATH . 'preload') && mkdir(DATA_PATH . 'preload');
            $preloadFile = self::_makeRunFile($files, $preloadFile);
        }

        $preloadFile && include $preloadFile;
    }

    /**
     * @param $runtimeFiles
     * @param $runFile
     * @return mixed
     */
    private static function _makeRunFile($runtimeFiles, $runFile)
    {
        $content = '';
        foreach ($runtimeFiles as $filename) {
            $data = php_strip_whitespace($filename);
            $content .= str_replace(['<?php', '?>', '<php_', '_php>'], ['', '', '<?php', '?>'], $data);
        }

        $fileDir = dirname($runFile);
        if (!is_dir($fileDir)) {
            mkdir($fileDir, DIR_READ_MODE);
        }
        if (!is_file($runFile)) {
            file_exists($runFile) && unlink($runFile); //可能是异常文件 删除
            touch($runFile) && chmod($runFile, FILE_READ_MODE); //读写空文件
        }
        if (!is_writable($runFile)) {
            chmod($runFile, FILE_READ_MODE); //读写
        }
        if (file_put_contents($runFile, '<?php ' . $content, LOCK_EX)) {
            chmod($runFile, FILE_READ_MODE); //只读
            return $runFile;
        }

        return false;
    }

    /**
     * @param $uri
     * @return void
     */
    private static function _dispatching($uri)
    {
        if (defined('ROUTE') && ROUTE) {
            self::_router($uri);
        }
        $controllerName = getgpc('g.' . self::$_dCTL, getini('site/defaultController'));
        $actionName = getgpc('g.' . self::$_dACT, getini('site/defaultAction'));
        $controllerName = preg_replace('/[^a-z\d_]+/i', '', $controllerName);
        $actionName = preg_replace('/[^a-z\d_]+/i', '', $actionName);

        if (defined('AUTH_ROLE') && AUTH_ROLE) {
            $ret = Rbac::check($controllerName, $actionName, AUTH_ROLE);
            if (!$ret) {
                $args = '没有权限访问 : ' . $controllerName . ' - ' . $actionName;
                self::_errACL($args);
                return;
            }
        }

        self::_execute($controllerName, $actionName);

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }

    /**
     * @param $controllerName
     * @param $actionName
     * @return void
     */
    private static function _execute($controllerName, $actionName)
    {
        $controllerName = ucfirst($controllerName);
        $actionMethod = self::$_actionPrefix . $actionName;

        $controllerClass = self::_controllerPrefix . $controllerName;
        //主动载入controller
        if (!self::_loadController($controllerName, $controllerClass)) {
            //控制器加载失败
            self::_errCtrl($controllerName . ' 控制器不存在');
            return;
        }
        $controller = new $controllerClass($controllerName, $actionName);
        if (!$controller instanceof $controllerClass) {
            //控制器加载失败
            self::_errCtrl($controllerName . ' 控制器加载失败');
            return;
        }
        call_user_func([$controller, $actionMethod]);
        $controller = null;
    }

    /**
     * @param $args
     * @return void
     */
    private static function _errCtrl($args)
    {
        if (self::isAjax()) {
            $res = [
                'code' => 1,
                'msg' => 'error:' . $args,
            ];
            self::response($res);
            return;
        }
        new ExException($args, 0, '', 0, '控制器', false);
    }

    /**
     * @param $args
     * @return void
     */
    private static function _errACL($args)
    {
        if (self::isAjax()) {
            $res = [
                'code' => 1,
                'msg' => 'error:' . $args,
            ];
            self::response($res);
            return;
        }
        new ExException($args, 0, '', 0, '权限', false);
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
        }
        $controllerFilename = APP_PATH . 'Controller/' . ucfirst(APP_KEY) . '/' . $controllerName . '.php';
        return is_file($controllerFilename) && include $controllerFilename;
    }

    /**
     * @param $uri
     * @return void
     */
    private static function _router($uri)
    {
        if (strpos($uri, 'index.php') !== false) {
            $uri = substr($uri, strpos($uri, 'index.php') + 10);
        }
        if (!$uri) {
            return;
        }

        if (!self::$routes) {
            self::$routes = include(APP_ROOT . '/route/' . APP_KEY . '.php');
        }

        $match = false;
        foreach (self::$routes as $key => $val) {
            if (is_array($val)) {
                foreach ($val as $k => $v) {
                    $uri = str_replace($key . '/', '', $uri);
                    $k = str_replace([':any', ':num'], ['[^/]+', '[0-9]+'], $k);
                    if (preg_match('#^' . $k . '$#', $uri, $matches)) {
                        if (strpos($v, '$') !== false && strpos($k, '(') !== false) {
                            $v = preg_replace('#^' . $k . '$#', $v, $uri);
                        }
                        $req = explode('/', $v);
                        self::_setRequest($req);
                        $match = true;
                        break;
                    }
                }
            } else {
                $key = str_replace([':any', ':num'], ['[^/]+', '[0-9]+'], $key);
                if (preg_match('#^' . $key . '$#', $uri, $matches)) {
                    if (strpos($val, '$') !== false && strpos($key, '(') !== false) {
                        $val = preg_replace('#^' . $key . '$#', $val, $uri);
                    }
                    $req = explode('/', $val);
                    self::_setRequest($req);
                    $match = true;
                    break;
                }
            }
        }
        if (!$match) {
            $req = explode('/', $uri);
            self::_setRequest($req);
        }
    }

    /**
     * @param $req
     */
    private static function _setRequest($req)
    {
        $_GET[self::$_dCTL] = array_shift($req);
        $_GET[self::$_dACT] = array_shift($req);
        $paramNum = count($req);
        if (!$paramNum || $paramNum % 2 !== 0) {
            return;
        }
        for ($i = 0; $i < $paramNum; $i++) {
            $_GET[$req[$i]] = $req[$i + 1];
            $i++;
        }
    }

    /**
     * @param string $namespace
     * @param string $path
     */
    private static function _rootNamespace($namespace, $path)
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
     * @param $udi
     * @param array $params
     * @return string
     */
    public static function url($udi, array $params = [])
    {
        $_udi = explode('/', $udi);
        if (count($_udi) < 2) {
            $url = '?' . self::$_dCTL . '=' . $_udi[0] . '&' . self::$_dACT . '=index';
        } else {
            $url = '?' . self::$_dCTL . '=' . $_udi[0] . '&' . self::$_dACT . '=' . $_udi[1];
        }

        if (!empty($params)) {
            foreach ($params as $key => $val) {
                $url .= '&' . $key . '=' . $val;
            }
        }
        return $url;
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
     * 导入所需的类库
     * @param string $class 类库命名空间字符串
     * @param string $ext 导入的文件扩展名
     * @param string $baseUrl 起始路径
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
     * @param array $arr
     * @return string
     */
    public static function output_json(array $arr)
    {
        return json_encode($arr, JSON_UNESCAPED_UNICODE);
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
        ob_get_length() && ob_end_clean();
        if (function_exists('ob_gzhandler')) { //whether start gzip
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
     * @return array|false|string|string[]|void
     */
    public static function output_end($echo = false)
    {
        $content = ob_get_contents();
        ob_get_length() && ob_end_clean();
        $content = preg_replace("/([\\x01-\\x08\\x0b-\\x0c\\x0e-\\x1f])+/", ' ', $content);
        $content = str_replace([chr(0), ']]>'], [' ', ']]&gt;'], $content);
        if (!$echo) {
            return $content;
        }
        echo $content;
    }

    /**
     * @param $res
     * @param string $type
     * @return bool
     */
    public static function response($res, $type = 'json')
    {
        self::output_nocache();
        if ('html' == $type) {
            header("Content-type: text/html; charset=UTF-8");
        } elseif ('json' == $type) {
            header('Content-type: text/json; charset=UTF-8');
            if (is_array($res)) {
                $res = self::output_json($res);
            }
        } elseif ('xml' == $type) {
            header("Content-type: text/xml");
            $res = '<?xml version="1.0" encoding="utf-8"?' . '>' . "\r\n" . '<root><![CDATA[' . $res;
        } elseif ('text' == $type) {
            header("Content-type: text/plain");
            if (is_array($res)) {
                $res = self::output_json($res);
            }
        } else {
            header("Content-type: text/html; charset=UTF-8");
        }
        echo $res;
        if ('xml' == $type) {
            echo ']]></root>';
        }

        return true;
    }

    /**
     * @param bool $retBool
     * @return bool
     */
    public static function isGet($retBool = true)
    {
        if ('GET' == $_SERVER['REQUEST_METHOD']) {
            return $retBool;
        }
        return !$retBool;
    }

    /**
     * @param bool $retBool
     * @return bool
     */
    public static function isPost($retBool = true)
    {
        if ('POST' == $_SERVER['REQUEST_METHOD']) {
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
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 'XMLHttpRequest' == $_SERVER['HTTP_X_REQUESTED_WITH']) {
            return $retBool;
        }
        return !$retBool;
    }

    /**
     * @param string $message
     * @param string $after_action
     * @param string $url
     * @return bool
     */
    public static function jsAlert($message = '', $after_action = '', $url = '')
    {
        //php turn to alert
        $out = "<script type=\"text/javascript\">\n";
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
     * @return bool|string|null
     */
    public static function redirect($url, $delay = 0, $js = false, $jsWrapped = true, $return = false)
    {
        $_delay = intval($delay);
        if (!$js) {
            if (headers_sent() || $_delay > 0) {
                echo <<<EOT
    <html>
    <head>
    <meta http-equiv="refresh" content="$_delay;URL=$url" />
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
            $out .= "window.setTimeout(function () { document.location='$url'; }, {$_delay});";
        } else {
            $out .= "document.location='$url';";
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

}
