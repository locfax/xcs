<?php

namespace Xcs;

use Swoole\Http\Server;

class SwooleApp
{
    public string $_dCTL = 'c';
    public string $_dACT = 'a';
    public string $_actionPrefix = 'act_';
    private array $_routes = [];
    private string $_controllerPrefix = 'Controller\\';

    public function run($host = '127.0.0.1', $port = 9501): void
    {
        $http = new Server($host, $port);
        $http->on('Request', function ($request, $response) {
            $this->_runFile();
            $this->_dispatching($request, $response);
        });
        $http->start();
    }

    private function _runFile(): void
    {
        $this->_rootNamespace('\\', APP_PATH);

        $preloadFile = RUNTIME_PATH . 'preload/runtime_' . APP_KEY . '_files.php';
        if (!is_file($preloadFile) || DEBUG) {

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
                    ExUiException::showError('语法错误', $errStr, $error);
                });
                set_exception_handler(function ($ex) {
                    if ($ex instanceof ExException) {
                        return;
                    }
                    ExUiException::render(get_class($ex), $ex->getMessage(), $ex->getFile(), $ex->getLine(), true, $ex);
                });
                register_shutdown_function(function () {
                    $error = error_get_last();
                    if ($error) {
                        ExUiException::showError('致命异常', $error['message'], [$error]);
                    }
                });
                array_walk($files, function ($file) {
                    require $file;
                });

                return;

            }

            !is_dir(RUNTIME_PATH . 'preload') && mkdir(RUNTIME_PATH . 'preload');
            $preloadFile = $this->_makeRunFile($files, $preloadFile);
        }

        $preloadFile && require $preloadFile;
    }

    /**
     * @param $runtimeFiles
     * @param $runFile
     * @return mixed
     */
    private function _makeRunFile($runtimeFiles, $runFile): mixed
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

    private function _dispatching($request, $response): void
    {
        $uri = $request->getRequestTarget();
        if (defined('ROUTE') && ROUTE) {
            $this->_router($uri);
        }
        $controllerName = getgpc('g.' . $this->_dCTL, getini('site/defaultController'));
        $actionName = getgpc('g.' . $this->_dACT, getini('site/defaultAction'));
        $controllerName = preg_replace('/[^a-z\d_]+/i', '', $controllerName);
        $actionName = preg_replace('/[^a-z\d_]+/i', '', $actionName);

        $this->_execute($controllerName, $actionName, $request, $response);
    }

    private function _execute($controllerName, $actionName, $request, $response): void
    {
        static $controller_pool = [];
        $controllerName = ucfirst($controllerName);
        $actionMethod = $this->_actionPrefix . $actionName;

        $controllerClass = $this->_controllerPrefix . $controllerName;
        if (isset($controller_pool[$controllerClass])) {
            $controller = $controller_pool[$controllerClass];
        } else {
            //主动载入controller
            if (!$this->_loadController($controllerName, $controllerClass)) {
                //控制器加载失败
                echo ' 控制器不存在';
                return;
            }
            $controller = new $controllerClass();
            $controller_pool[$controllerClass] = $controller;
        }
        $controller->init($request, $response, $controllerName, $actionName);
        call_user_func([$controller, $actionMethod]);
    }

    /**
     * @param $controllerName
     * @param $controllerClass
     * @return bool
     */
    private function _loadController($controllerName, $controllerClass): bool
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
    private function _router($uri): void
    {
        if (str_contains($uri, 'index.php')) {
            $uri = substr($uri, strpos($uri, 'index.php') + 10);
        }

        if (!$uri) {
            return;
        }

        if (is_file(APP_PATH . 'Route/' . APP_KEY . '.php')) {
            $this->_routes = include(APP_PATH . 'Route/' . APP_KEY . '.php');
        }

        $match = false;
        foreach ($this->_routes as $key => $val) {
            $key = str_replace([':any', ':num'], ['[^/]+', '[0-9]+'], $key);
            if (preg_match('#^' . $key . '$#', $uri)) {
                if (str_contains($val, '$') && str_contains($key, '(')) {
                    $val = preg_replace('#^' . $key . '$#', $val, $uri);
                }
                $req = explode('/', $val);
                $this->_setRequest($req);
                $match = true;
                break;
            }
        }

        if (!$match) {
            $req = explode('/', $uri);
            $this->_setRequest($req);
        }
    }

    /**
     * @param array $req
     */
    private function _setRequest(array $req): void
    {
        $_GET[$this->_dCTL] = array_shift($req);
        $_GET[$this->_dACT] = array_shift($req);
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
    private function _rootNamespace($namespace, $path): void
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

}
