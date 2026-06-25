<?php

namespace Xcs;

use Error;

class App
{
    private static string $_dCTL = 'c';
    private static string $_dACT = 'a';
    private static string $_actionPrefix = 'act_';

    public static function run(): void
    {
        if (isset($_GET['s'])) {
            $uri = trim(str_replace(['.html', '.shtml', '.htm'], '', $_GET['s']), '/');
        } else {
            $uri = $_SERVER['PHP_SELF'];
        }

        self::runFile();
        self::dispatch($uri);
    }

    public static function runFile(): void
    {
        self::rootNamespace();

        $appKey = strtolower(APP_KEY);
        $preloadFile = RUNTIME_PATH . 'preload' . DS . 'runtime_' . $appKey . '_files.php';

        if (!is_file($preloadFile) || DEBUG) {
            $files = [XCS_PATH . 'function.php']; //框架自带常用函数
            is_file(LIB_PATH . 'function.php') && array_push($files, LIB_PATH . 'function.php');  //自定义函数
            is_file(APP_ROOT . DS . 'config' . DS . 'common.php') && array_push($files, APP_ROOT . DS . 'config' . DS . 'common.php'); //通用常用配置
            is_file(APP_ROOT . DS . 'config' . DS . 'database.php') && array_push($files, APP_ROOT . DS . 'config' . DS . 'database.php'); //数据库配置
            is_file(APP_ROOT . DS . 'config' . DS . $appKey . '.inc.php') && array_push($files, APP_ROOT . DS . 'config' . DS . $appKey . '.inc.php');
            is_file(APP_ROOT . DS . 'config' . DS . $appKey . '.route.php') && array_push($files, APP_ROOT . DS . 'config' . DS . $appKey . '.route.php');

            if (DEBUG) {
                set_error_handler(function ($errno, $errStr, $errFile, $errLine) {
                    $error = [
                        ['file' => $errFile, 'line' => $errLine]
                    ];
                    echo UiException::error('error', $errStr, $error);
                });

                set_exception_handler(function ($ex) {
                    echo UiException::render(get_class($ex), $ex->getMessage(), $ex->getFile(), $ex->getLine(), true, $ex);
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

                return; //调试状态不生成runtime文件
            }

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
            mkdir($fileDir, DIR_WRITE_MODE, true);
        }

        if (!is_file($runFile)) {
            file_exists($runFile) && unlink($runFile); //可能是异常文件 删除
            touch($runFile) && chmod($runFile, FILE_READ_MODE); //读写空文件
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
        $controllerClass = sprintf('\\Controller\\%s\\%s', APP_KEY, $controllerName);

        //判断controller是否存在
        $fixed = self::fixedController($controllerClass);
        if (!$fixed) {
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

        header('Content-Type: text/html; charset=UTF-8');

        //调用方法
        $result = call_user_func([$controller, self::$_actionPrefix . $actionName]);

        //没找到action
        if ($result === false) {
            $result = self::errCtrl($controllerName . ' act not found');
            self::printResult($result);
            return;
        }

        //无需后续 可能是在action直接输出
        if (empty($result)) {
            return;
        }

        self::printResult($result);
    }

    private static function printResult(array $result): void
    {
        if ($result['type'] == 'html') {
            header('Content-Type: text/html; charset=UTF-8', true, $result['code']);
        } elseif ($result['type'] == 'json') {
            header('Content-Type: application/json; charset=UTF-8', true, $result['code']);
        } elseif ($result['type'] == 'text') {
            header('Content-Type: text/plain; charset=UTF-8', true, $result['code']);
        } else {
            throw new Error('result type not supported');
        }

        echo $result['content'] ?? '';
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
                return ['type' => 'json', 'content' => json_encode($data, JSON_UNESCAPED_UNICODE), 'code' => 200];
            }
            return ['type' => 'html', 'content' => UiException::error('Ctl', $msg), 'code' => 200];
        }

        return ['type' => 'html', 'content' => template('page/404', [], false, true), 'code' => 200];
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
            return ['type' => 'json', 'content' => json_encode($data, JSON_UNESCAPED_UNICODE), 'code' => 200];
        }

        return ['type' => 'html', 'content' => UiException::error('Acl', $msg), 'code' => 200];
    }

    /**
     * @param string $controllerClass
     * @return bool
     */
    private static function fixedController(string $controllerClass): bool
    {
        if (class_exists($controllerClass, false)) {
            return true;
        }

        $filename = APP_PATH . str_replace('\\', DS, trim($controllerClass, '\\')) . '.php';
        if (DEBUG && pathinfo($filename, PATHINFO_FILENAME) != pathinfo(realpath($filename), PATHINFO_FILENAME)) {
            throw new Error("$filename not exists");
        }

        return is_file($filename);
    }

    /**
     * @param string $controllerName
     * @return mixed
     */
    public static function getController(string $controllerName): mixed
    {
        $controllerName = ucfirst($controllerName);
        $controllerClass = sprintf('\\Controller\\%s\\%s', APP_KEY, $controllerName);
        if (class_exists($controllerClass, false)) {
            return $controllerClass;
        }

        $filename = sprintf('%sController%s%s%s%s.php', APP_PATH, DS, APP_KEY, DS, $controllerName);
        if (DEBUG && pathinfo($filename, PATHINFO_FILENAME) != pathinfo(realpath($filename), PATHINFO_FILENAME)) {
            throw new Error("$filename not exists");
        }

        if (is_file($filename)) {
            include $filename;
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

        $_routes = self::mergeVars('route');
        if (empty($_routes)) {
            return true;
        }

        $match = false;
        foreach ($_routes as $key => $val) {
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
            $filename = APP_PATH . str_replace('\\', DS, trim($classname, '\\')) . '.php';
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
        static $_CDATA =  ['dsn' => [], 'cfg' => [], 'route' => []];
        if (empty($vars)) {
            return $_CDATA[$group] ?? [];
        }
        if (isset($_CDATA[$group])) {
            $_CDATA[$group] = array_merge($_CDATA[$group], $vars);
        }
        return true;
    }

}
