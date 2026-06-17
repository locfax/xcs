<?php

namespace Xcs;

use Error;

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
        if (isset($_GET['s'])) {
            $uri = trim(str_replace(['.html', '.shtml', '.htm'], '', $_GET['s']), '/');
        } else {
            $uri = $_SERVER['PHP_SELF'];
        }

        self::runFile($refresh);
        self::dispatch($uri);
    }

    /**
     * @param bool $refresh
     */
    public static function runFile(bool $refresh = false): void
    {
        self::rootNamespace();

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
                    echo UiException::error('error', $errStr, $error);
                });
                set_exception_handler(function ($ex) {
                    echo UiException::Render(get_class($ex), $ex->getMessage(), $ex->getFile(), $ex->getLine(), true, $ex);
                });
                register_shutdown_function(function () {
                    $error = error_get_last();
                    if ($error) {
                        echo UiException::error('exception', $error['message'], [$error]);
                    }
                });
                array_walk($files, function ($file) {
                    include $file;
                });
                return;
            }

            !is_dir(RUNTIME_PATH . 'preload') && mkdir(RUNTIME_PATH . 'preload');
            $preloadFile = self::makeRunFile($files, $preloadFile);
        }

        $preloadFile && include $preloadFile;
    }

    /**
     * @param array $runtimeFiles
     * @param string $runFile
     * @return string|bool
     */
    private static function makeRunFile(array $runtimeFiles, string $runFile): string|bool
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
    private static function dispatch($uri): void
    {
        if (ROUTE) {
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
     * @param string $controllerName
     * @param string $actionName
     * @return void
     */
    private static function execute(string $controllerName, string $actionName): void
    {
        $controllerName = ucfirst($controllerName);
        $actionMethod = self::$_actionPrefix . $actionName;
        $controllerClass = self::$_controllerPrefix . $controllerName;

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
            $result = self::errCtrl($controllerName . ' act not found');
            self::printResult($result);
            return;
        }

        //无需后续
        if (empty($result)) {
            return;
        }

        self::printResult($result);
    }

    private static function printResult(array $result): void
    {
        if ($result['type'] == 'html') {
            header('Content-Type: text/html; charset=UTF-8');
        } elseif ($result['type'] == 'json') {
            header('Content-Type: application/json; charset=UTF-8');
        } elseif ($result['type'] == 'text') {
            header('Content-Type: text/plain; charset=UTF-8');
        } else {
            throw new Error('result type not supported');
        }

        echo $result['content'];
    }

    /**
     * @param $msg
     * @return array
     */
    private static function errCtrl($msg): array
    {
        if (DEBUG) {
            if (isAjax()) {
                $data = [
                    'code' => 1,
                    'message' => $msg,
                ];
                return ['type' => 'json', 'content' => json_encode($data, JSON_UNESCAPED_UNICODE)];
            }
            return ['type' => 'html', 'content' => UiException::error('Ctl', $msg)];
        }

        return ['type' => 'html', 'content' => template('page/404', [], false, true)];
    }

    /**
     * @param string $msg
     * @return array
     */
    public static function errACL(string $msg): array
    {
        if (isAjax()) {
            $data = [
                'code' => 1,
                'message' => $msg,
            ];
            return ['type' => 'json', 'content' => json_encode($data, JSON_UNESCAPED_UNICODE)];
        }
        return ['type' => 'html', 'content' => UiException::error('Acl', $msg), 'header' => 'HTTP/1.1 401 Unauthorized'];
    }

    /**
     * @param string $controllerName
     * @param string $controllerClass
     * @return bool
     */
    private static function loadController(string $controllerName, string $controllerClass): bool
    {
        if (class_exists($controllerClass, false) || interface_exists($controllerClass, false)) {
            return true;
        }
        $app = getini('site/app') ?: ucfirst(APP_KEY);
        $controllerFilename = sprintf('%sController/%s/%s.php', APP_PATH, $app, $controllerName);
        return is_file($controllerFilename) && include $controllerFilename;
    }

    /**
     * @param string $controllerName
     * @return mixed
     */
    public static function controller(string $controllerName): mixed
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
     * @param string $uri
     * @return bool
     */
    private static function router(string $uri): bool
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
     * @return void
     */
    private static function rootNamespace(): void
    {
        $loader = function ($classname) {
            $filename = rtrim(APP_PATH, '/') . '/' . str_replace('\\', '/', trim($classname, '\\')) . '.php';
            if (DEBUG && pathinfo($filename, PATHINFO_FILENAME) != pathinfo(realpath($filename), PATHINFO_FILENAME)) {
                throw new Error("$filename not exists");
            }
            include $filename;
        };
        spl_autoload_register($loader);
    }

    /**
     * @param array|string $udi
     * @param array $params
     * @return string
     */

    public static function url(array|string $udi, array $params = []): string
    {

        if (is_array($udi)) {
            if (count($udi) == 3) {
                $url = $udi[0] . '?' . self::$_dCTL . '=' . $udi[1] . '&' . self::$_dACT . '=' . $udi[2];
            } else {
                throw new Error("udi must be an array[3]");
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
     * @param string $group
     * @param array $vars
     * @return bool|array
     */
    public static function mergeVars(string $group, array $vars = []): bool|array
    {
        static $_CDATA = [APP_KEY => ['dsn' => [], 'cfg' => []]];
        if (empty($vars)) {
            return $_CDATA[APP_KEY][$group] ?? [];
        }
        if (is_null($_CDATA[APP_KEY][$group])) {
            $_CDATA[APP_KEY][$group] = $vars;
        } else {
            $_CDATA[APP_KEY][$group] = array_merge($_CDATA[APP_KEY][$group], $vars);
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

        if (isset($_file[$key])) {
            return true;
        }

        // 如果类存在 则导入类库文件
        $filename = $baseUrl . $class . $ext;

        if (!empty($filename) && is_file($filename)) {
            // 开启调试模式Win环境严格区分大小写
            if (DEBUG && pathinfo($filename, PATHINFO_FILENAME) != pathinfo(realpath($filename), PATHINFO_FILENAME)) {
                throw new Error("filename $filename not exists");
            }
            include $filename;
            $_file[$key] = true;
            return true;
        }

        return false;
    }

}
