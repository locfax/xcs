<?php

namespace Xcs;

class SwooleApp
{
    private string $_dCTL = 'c';
    private string $_dACT = 'a';
    private string $_actionPrefix = 'act_';
    private string $_controllerPrefix = 'Controller\\';
    private array $_routes = [];

    public function runFile(): void
    {
        $this->_rootNamespace('\\', APP_PATH);

        $files = [XCS_PATH . 'common.php']; //应用配置
        is_file(LIB_PATH . 'function.php') && array_push($files, LIB_PATH . 'function.php');
        is_file(LIB_PATH . APP_KEY . '.php') && array_push($files, LIB_PATH . APP_KEY . '.php');
        is_file(APP_ROOT . '/config/' . APP_KEY . '.inc.php') && array_push($files, APP_ROOT . '/config/' . APP_KEY . '.inc.php');
        is_file(APP_ROOT . '/config/common.php') && array_push($files, APP_ROOT . '/config/common.php');
        is_file(APP_ROOT . '/config/database.php') && array_push($files, APP_ROOT . '/config/database.php');

        set_error_handler(function ($errno, $errStr, $errFile, $errLine) {
            echo "\n\n=================================\n";
            echo '[语法错误: ', $errFile, ', ', $errStr, ', ', $errLine . ']';
            echo "\n=================================\n\n";
        });
        set_exception_handler(function ($ex) {
            echo "\n\n=================================\n";
            echo '[Exception错误: ' . $ex->getFile(), ', ', $ex->getLine(), ', ', $ex->getMessage() . ']';
            echo "\n=================================\n\n";
        });
        register_shutdown_function(function () {
            $error = error_get_last();
            echo "\n\n=================================\n";
            echo '[致命错误: ' . $error['message'] . ']';
            echo "\n=================================\n\n";
        });
        array_walk($files, function ($file) {
            require $file;
        });

    }

    public function dispatching($request, $response): void
    {

        if (isset($request->get['s'])) {
            $uri = trim(str_replace(['.html', '.htm'], '', $request->get['s']), '/');
        } else {
            $uri = trim(str_replace(['.html', '.htm'], '', $request->server['request_uri']), '/');
        }

        if (defined('ROUTE') && ROUTE) {
            $this->_router($request, $uri);
        }

        $controllerName = $request->get[$this->_dCTL] ?? getini('site/defaultController');
        $actionName = $request->get[$this->_dACT] ?? getini('site/defaultAction');

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
        if (!isset($controller_pool[$controllerClass])) {
            //主动载入controller
            if (!$this->_loadController($controllerName, $controllerClass)) {
                //控制器加载失败
                $response->header('Content-Type: text/html; charset=UTF-8', true);
                $response->end($this->_errCtrl($controllerName . ' 控制器不存在'));
                return;
            }
            $controller_pool[$controllerClass] = new $controllerClass();
        }

        $retsult = $controller_pool[$controllerClass]->init($request, $response, $controllerName, $actionName);
        if ($retsult) {
            if (!is_array($retsult)) {
                $response->end($retsult);
            } else {
                if ($retsult['type'] == 'text') {
                    header('Content-Type: text/html; charset=UTF-8', true);
                } elseif ($retsult['type'] == 'json') {
                    header('Content-Type: application/json; charset=UTF-8', true);
                }
                $response->end($retsult['content']);
            }
            return;
        }

        $retsult = call_user_func([$controller_pool[$controllerClass], $actionMethod]);
        if (!is_array($retsult)) {
            $response->end($retsult);
            return;
        }

        if ($retsult['type'] == 'text') {
            header('Content-Type: text/html; charset=UTF-8', true);
        } elseif ($retsult['type'] == 'json') {
            header('Content-Type: application/json; charset=UTF-8', true);
        }
        $response->end($retsult['content']);
    }

    /**
     * @param $args
     * @return string
     */
    private function _errCtrl($args)
    {
        if (DEBUG) {
            return ExUiException::showError('控制器', $args);
        }
        return '';
    }

    /**
     * @param string $args
     * @return string
     */
    public function ErrACL(string $args)
    {
        return ExUiException::showError('权限', $args);
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
     * @param $request
     * @return void
     */
    private function _router(&$request, $uri)
    {
        static $routes = null;

        if (strpos($uri, 'index.php') !== false) {
            $uri = substr($uri, strpos($uri, 'index.php') + 10);
        }
        if (!$uri) {
            return [];
        }
        if (!$routes) {
            $routes = include(APP_PATH . 'Route/' . APP_KEY . '.php');
        }

        $match = false;
        foreach ($routes as $key => $val) {
            $key = str_replace([':any', ':num'], ['[^/]+', '[0-9]+'], $key);
            if (preg_match('#^' . $key . '$#', $uri)) {
                if (str_contains($val, '$') && str_contains($key, '(')) {
                    $val = preg_replace('#^' . $key . '$#', $val, $uri);
                }
                $req = explode('/', $val);
                $this->_setRequest($request, $req);
                $match = true;
                break;
            }
        }

        if (!$match) {
            $req = explode('/', $uri);
            $this->_setRequest($request, $req);
        }
    }

    /**
     * @param array $req
     */
    private function _setRequest($request, array $req): void
    {
        $request->get[$this->_dCTL] = array_shift($req);
        $request->get[$this->_dACT] = array_shift($req);
        $paramNum = count($req);
        if (!$paramNum || $paramNum % 2 !== 0) {
            return;
        }
        for ($i = 0; $i < $paramNum; $i++) {
            $request->get[$req[$i]] = $req[$i + 1];
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
