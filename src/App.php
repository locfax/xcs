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
        self::dispatching($uri);
    }

    /**
     * @param bool $refresh
     */
    public static function runFile(bool $refresh = false): void
    {
        self::rootNamespace('\\', APP_PATH);

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
                    echo UiException::Error('语法错误', $errStr, $error);
                });
                set_exception_handler(function ($ex) {
                    echo UiException::Render(get_class($ex), $ex->getMessage(), $ex->getFile(), $ex->getLine(), true, $ex);
                });
                register_shutdown_function(function () {
                    $error = error_get_last();
                    if ($error) {
                        echo UiException::Error('致命异常', $error['message'], [$error]);
                    }
                });
                array_walk($files, function ($file) {
                    require $file;
                });
                return;
            }

            !is_dir(RUNTIME_PATH . 'preload') && mkdir(RUNTIME_PATH . 'preload');
            $preloadFile = self::makeRunFile($files, $preloadFile);
        }

        $preloadFile && require $preloadFile;
    }

    /**
     * @param $runtimeFiles
     * @param $runFile
     * @return mixed
     */
    private static function makeRunFile($runtimeFiles, $runFile)
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
    private static function dispatching($uri): void
    {
        if (defined('ROUTE') && ROUTE) {
            $flag = self::router($uri);
            if (!$flag) {
                $result = self::errCtrl('Error Router');
                self::printResult($result);
                return;
            }
        }
        $controllerName = getgpc('g.' . self::$_dCTL, getini('site/defaultController'));
        $actionName = getgpc('g.' . self::$_dACT, getini('site/defaultAction'));
        $controllerName = preg_replace('/[^a-z\d_]+/i', '', $controllerName);
        $actionName = preg_replace('/[^a-z\d_]+/i', '', $actionName);

        self::execute($controllerName, $actionName);

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }

    /**
     * @param $controllerName
     * @param $actionName
     * @return void
     */
    private static function execute($controllerName, $actionName): void
    {
        $controllerName = ucfirst($controllerName);
        $actionMethod = self::$_actionPrefix . $actionName;
        $controllerClass = self::$_controllerPrefix . $controllerName;

        header('Content-Type: text/html; charset=UTF-8');

        //载入controller
        if (!self::loadController($controllerName, $controllerClass)) {
            //控制器加载失败
            $result = self::errCtrl($controllerName . ' ctl not found');
            self::printResult($result);
            return;
        }

        $controller = new $controllerClass();
        $result = $controller->init($controllerName, $actionName);
        //setup有返回
        if ($result) {
            self::printResult($result);
            return;
        }

        //调用方法
        $result = call_user_func([$controller, $actionMethod]);
        //没找到action
        if ($result === false) {
            return;
        }
        //无需后续
        if (is_null($result)) {
            return;
        }

        self::printResult($result);
    }

    private static function printResult($result): void
    {

        if ($result['type'] == 'html') {

        } elseif ($result['type'] == 'json') {
            header('Content-Type: application/json; charset=UTF-8');
        } elseif ($result['type'] == 'text') {
            header('Content-Type: text/plain; charset=UTF-8');
        } else {
            throw new \Error('result type not supported');
        }

        echo $result['content'];
    }

    /**
     * @param $msg
     * @return array|string
     */
    private static function errCtrl($msg)
    {
        if (DEBUG) {
            if (isAjax()) {
                $data = [
                    'code' => 1,
                    'message' => $msg,
                ];
                return ['type' => 'json', 'content' => json_encode($data, JSON_UNESCAPED_UNICODE)];
            }
            header('HTTP/1.1 404 Not Found');
            return UiException::Error('Ctl', $msg);
        }
        return ['type' => 'html', 'content' => template('page/404', [], false, true)];
    }

    /**
     * @param string $msg
     * @return string|array
     */

    public static function errACL(string $msg)
    {
        if (isAjax()) {
            $res = [
                'code' => 1,
                'message' => $msg,
            ];
            return ['type' => 'json', 'content' => json_encode($data, JSON_UNESCAPED_UNICODE)];
        }
        header('HTTP/1.1 401 Unauthorized');
        return UiException::Error('Acl', $msg);
    }

    /**
     * @param $controllerName
     * @param $controllerClass
     * @return bool
     */
    private static function loadController($controllerName, $controllerClass): bool
    {
        if (class_exists($controllerClass, false) || interface_exists($controllerClass, false)) {
            return true;
        }
        $app = getini('site/app') ?: ucfirst(APP_KEY);
        $controllerFilename = sprintf('%sController/%s/%s.php', APP_PATH, $app, $controllerName);
        return is_file($controllerFilename) && include $controllerFilename;
    }

    /**
     * @param $controllerName
     */
    public static function controller($controllerName)
    {
        $controllerClass = self::$_controllerPrefix . $controllerName;
        if (class_exists($controllerClass, false) || interface_exists($controllerClass, false)) {
            return $controllerClass;
        }
        $app = getini('site/app') ?: ucfirst(APP_KEY);
        $controllerFilename = sprintf('%sController/%s/%s.php', APP_PATH, $app, $controllerName);
        if (is_file($controllerFilename)) {
            include $controllerFilename;
            return new $controllerClass();
        }
        return false;
    }

    /**
     * @param $uri
     * @return void
     */
    private static function router($uri): bool
    {
        if (str_contains($uri, 'index.php')) {
            $uri = substr($uri, str_contains($uri, 'index.php') + 10);
        }

        if (!$uri) {
            return true;
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
                self::setRequest($req);
                $match = true;
                break;
            }
        }

        if (!$match) {
            $req = explode('/', $uri);
            if (count($req) % 2 != 0) {
                return false;
            }
            self::setRequest($req);
        }

        return true;
    }

    /**
     * @param array $req
     */
    private static function setRequest(array $req): void
    {
        $_GET[self::$_dCTL] = array_shift($req);
        $_GET[self::$_dACT] = array_shift($req);
        $paramNum = count($req);
        if (!$paramNum || $paramNum % 2 !== 0) {
            return;
        }
        for ($i = 0; $i < $paramNum; $i++) {
            if (empty($req[$i])) {
                continue;
            }
            $_GET[$req[$i]] = $req[$i + 1];
            $i++;
        }
    }

    /**
     * @param $namespace
     * @param $path
     * @return void
     */
    private static function rootNamespace($namespace, $path): void
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
     * @param mixed $udi
     * @param array $params
     * @return string
     */

    public static function url(mixed $udi, array $params = []): string
    {

        if (is_array($udi)) {
            if (count($udi) == 3) {
                $url = $udi[0] . '?' . self::$_dCTL . '=' . $_udi[1] . '&' . self::$_dACT . '=' . $_udi[2];
            } else {
                throw new \Error("udi must be an array[3]");
            }
            $first = false;
        } else {
            $url = $udi;
            $first = true;
        }

        if (!empty($params)) {
            foreach ($params as $key => $val) {
                if ($first) {
                    $url .= '?' . $key . '=' . rawurlencode($val);
                    $first = false;
                } else {
                    $url .= '&' . $key . '=' . rawurlencode($val);
                }
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

}
