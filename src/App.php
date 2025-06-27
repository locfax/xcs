<?php

namespace Xcs;

class App
{

    private static string $_dCTL = 'c';
    private static string $_dACT = 'a';
    private static string $_actionPrefix = 'act_';
    private static string $_controllerPrefix = 'Controller\\';
    private static array $_routes = [];

    /**
     * @param bool $refresh
     */
    public static function run(bool $refresh = false): void
    {
        if (!defined('APP_KEY')) {
            exit('APP_KEY not defined!');
        }

        if (isset($_GET['s'])) {
            $uri = trim(str_replace(['.html', '.htm'], '', $_GET['s']), '/');
        } else {
            $uri = $_SERVER['PHP_SELF'];
        }

        self::runFile($refresh);
        self::_dispatching($uri);
    }

    /**
     * @param bool $refresh
     */
    public static function runFile(bool $refresh = false): void
    {
        self::_rootNamespace('\\', APP_PATH);

        $preloadFile = RUNTIME_PATH . 'preload/runtime_' . APP_KEY . '_files.php';
        if (!is_file($preloadFile) || $refresh || DEBUG) {

            $files = [XCS_PATH . 'common.php']; //应用配置
            is_file(LIB_PATH . 'function.php') && array_push($files, LIB_PATH . 'function.php');
            is_file(LIB_PATH . APP_KEY . '.php') && array_push($files, LIB_PATH . APP_KEY . '.php');
            is_file(APP_ROOT . '/config/' . APP_KEY . '.inc.php') && array_push($files, APP_ROOT . '/config/' . APP_KEY . '.inc.php');
            is_file(APP_ROOT . '/config/common.php') && array_push($files, APP_ROOT . '/config/common.php');
            is_file(APP_ROOT . '/config/database.php') && array_push($files, APP_ROOT . '/config/database.php');

            if (DEBUG) {
                set_error_handler(function ($errno, $errStr, $errFile, $errLine) {
                    $error = [
                        ['file' => $errFile, 'line' => $errLine]
                    ];
                    echo ExUiException::showError('语法错误', $errStr, $error);
                });
                set_exception_handler(function ($ex) {
                    echo ExUiException::render(get_class($ex), $ex->getMessage(), $ex->getFile(), $ex->getLine(), true, $ex);
                });
                register_shutdown_function(function () {
                    $error = error_get_last();
                    if ($error) {
                        echo ExUiException::showError('致命异常', $error['message'], [$error]);
                    }
                });
                array_walk($files, function ($file) {
                    require $file;
                });
                return;
            }

            !is_dir(RUNTIME_PATH . 'preload') && mkdir(RUNTIME_PATH . 'preload');
            $preloadFile = self::_makeRunFile($files, $preloadFile);
        }

        $preloadFile && require $preloadFile;
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
    private static function _dispatching($uri): void
    {
        if (defined('ROUTE') && ROUTE) {
            self::_router($uri);
        }
        $controllerName = getgpc('g.' . self::$_dCTL, getini('site/defaultController'));
        $actionName = getgpc('g.' . self::$_dACT, getini('site/defaultAction'));
        $controllerName = preg_replace('/[^a-z\d_]+/i', '', $controllerName);
        $actionName = preg_replace('/[^a-z\d_]+/i', '', $actionName);

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
    private static function _execute($controllerName, $actionName): void
    {
        $controllerName = ucfirst($controllerName);
        $actionMethod = self::$_actionPrefix . $actionName;
        $controllerClass = self::$_controllerPrefix . $controllerName;
        //主动载入controller
        if (!self::_loadController($controllerName, $controllerClass)) {
            //控制器加载失败
            header('Content-Type: text/html; charset=UTF-8', true);
            echo self::_errCtrl($controllerName . ' 控制器不存在');
            return;
        }

        $controller = new $controllerClass();
        $result = $controller->init($controllerName, $actionName);
        if ($result) {
            if (!is_array($result)) {
                echo $result;
            } else {
                if ($result['type'] == 'text') {
                    header('Content-Type: text/html; charset=UTF-8', true);
                } elseif ($result['type'] == 'json') {
                    header('Content-Type: application/json; charset=UTF-8', true);
                }
                echo $result['content'];
            }
            return;
        }

        $result = call_user_func([$controller, $actionMethod]);
        if (!is_array($result)) {
            echo $result;
            return;
        }
        if ($result['type'] == 'text') {
            header('Content-Type: text/html; charset=UTF-8', true);
        } elseif ($result['type'] == 'json') {
            header('Content-Type: application/json; charset=UTF-8', true);
        }
        echo $result['content'];
    }

    /**
     * @param $args
     * @return array|string
     */
    private static function _errCtrl($args)
    {
        if (DEBUG) {
            if (self::isAjax()) {
                $res = [
                    'code' => 1,
                    'message' => 'error:' . $args,
                ];
                return self::response($res, 'json');
            }
            return ExUiException::showError('控制器', $args);
        } else {
            header('HTTP/1.1 404 Not Found');
            header('Status: 404 Not Found');
        }
        return '';
    }

    /**
     * @param string $args
     * @return string|array
     */
    public static function ErrACL(string $args)
    {
        if (self::isAjax()) {
            $res = [
                'code' => 1,
                'message' => 'error:' . $args,
            ];
            return self::response($res, 'json');
        }
        return ExUiException::showError('权限', $args);
    }

    /**
     * @param $controllerName
     * @param $controllerClass
     * @return bool
     */
    private static function _loadController($controllerName, $controllerClass): bool
    {
        if (class_exists($controllerClass, false) || interface_exists($controllerClass, false)) {
            return true;
        }
        $controllerFilename = APP_PATH . 'Controller/' . ucfirst(APP_KEY) . '/' . $controllerName . '.php';
        return is_file($controllerFilename) && require $controllerFilename;
    }

    /**
     * @param $uri
     * @return void
     */
    private static function _router($uri): void
    {
        if (str_contains($uri, 'index.php')) {
            $uri = substr($uri, strpos($uri, 'index.php') + 10);
        }

        if (!$uri) {
            return;
        }

        if (is_file(APP_PATH . 'Route/' . APP_KEY . '.php')) {
            self::$_routes = include(APP_PATH . 'Route/' . APP_KEY . '.php');
        }

        $match = false;
        foreach (self::$_routes as $key => $val) {
            $key = str_replace([':any', ':num'], ['[^/]+', '[0-9]+'], $key);
            if (preg_match('#^' . $key . '$#', $uri)) {
                if (str_contains($val, '$') && str_contains($key, '(')) {
                    $val = preg_replace('#^' . $key . '$#', $val, $uri);
                }
                $req = explode('/', $val);
                self::_setRequest($req);
                $match = true;
                break;
            }
        }

        if (!$match) {
            $req = explode('/', $uri);
            self::_setRequest($req);
        }
    }

    /**
     * @param array $req
     */
    private static function _setRequest(array $req): void
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
     * @param $namespace
     * @param $path
     * @return void
     */
    private static function _rootNamespace($namespace, $path): void
    {
        $namespace = trim($namespace, '\\');
        $path = rtrim($path, '/');

        $loader = function ($classname) use ($namespace, $path) {
            if ($namespace && stripos($classname, $namespace) !== 0) {
                return;
            }
            $file = trim(substr($classname, strlen($namespace)), '\\');
            $file = $path . '/' . str_replace('\\', '/', $file) . '.php';
            require $file;
        };

        spl_autoload_register($loader);
    }

    /**
     * @param $udi
     * @param array $params
     * @return string
     */
    public static function url($udi, array $params = []): string
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
    public static function vendor(string $class, string $ext = '.php', string $baseUrl = LIB_PATH): bool
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
            if (DEBUG && pathinfo($filename, PATHINFO_FILENAME) != pathinfo(realpath($filename), PATHINFO_FILENAME)) {
                return false;
            }
            require $filename;
            $_file[$key] = true;
            return true;
        }

        return false;
    }

    /**
     * @param $res
     * @param string $type
     * @return array
     */
    public static function response($res, string $type = 'json'): array
    {
        if ('json' == $type) {
            if (is_array($res)) {
                $res = json_encode($res, JSON_UNESCAPED_UNICODE);
            }
        }
        return ['type' => $type, 'content' => $res];
    }

    /**
     * @param bool $retBool
     * @return bool
     */
    public static function isGet(bool $retBool = true): bool
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
    public static function isPost(bool $retBool = true): bool
    {
        if ('POST' == $_SERVER['REQUEST_METHOD']) {
            return $retBool;
        }
        return !$retBool;
    }

    /**
     * @return bool
     */
    public static function isAjax(): bool
    {
        $val = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        return 'XMLHttpRequest' == $val;
    }

    /**
     * @param string $message
     * @param string $after_action
     * @param string $url
     * @return array
     */
    public static function jsAlert(string $message = '', string $after_action = '', string $url = ''): array
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
        return ['type' => 'text', 'content' => $out];
    }

    /**
     * @param $url
     * @param int $delay
     * @param bool $js
     * @param bool $jsWrapped
     * @param bool $return
     * @return array|string
     */
    public static function redirect($url, int $delay = 0, bool $js = false, bool $jsWrapped = true, bool $return = false)
    {
        if (!$js) {
            if ($delay > 0) {
                echo <<<EOT
    <html lang="zh">
    <head>
    <title></title>
    <meta http-equiv="refresh" content="$delay;URL=$url" />
    </head>
    </html>
EOT;
            } else {
                header("Location: {$url}");
            }
            return '';
        }

        $out = '';
        if ($jsWrapped) {
            $out .= '<script language="javascript" type="text/javascript">';
        }
        if ($delay > 0) {
            $out .= "window.setTimeout(function () { document.location='$url'; }, {$delay});";
        } else {
            $out .= "document.location='$url';";
        }
        if ($jsWrapped) {
            $out .= '</script>';
        }
        if ($return) {
            return $out;
        }
        return ['type' => 'text', 'content' => $out];
    }

}
