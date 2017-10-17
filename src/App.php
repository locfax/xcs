<?php

namespace Xcs;

class App {

    const _dCTL = 'c';
    const _dACT = 'a';
    const _controllerPrefix = 'Controller\\';
    const _actionPrefix = 'act_';

    static $routes = null;

    /**
     * @param array $preload
     * @param bool $refresh
     */
    public static function run($preload, $refresh = false) {
        if (!defined('APPKEY')) {
            exit('APPKEY not defined!');
        }
        self::runFile($preload, $refresh);
        if (isset($_GET['s'])) {
            $uri = trim($_GET['s']);
        } else {
            $uri = $_SERVER['PHP_SELF'];
        }
        self::dispatching($uri);
    }

    public static function runFile($preload, $refresh = false) {
        set_error_handler(function ($errno, $error, $file = null, $line = null) {
            throw new Exception\ErrorException($error, $errno);
        });
        $dfiles = array(
            PSROOT . '/config/base.inc.php', //全局配置
            PSROOT . '/config/' . APPKEY . '.dsn.php', //数据库配置
            PSROOT . '/config/' . APPKEY . '.inc.php', //应用配置
            BASEPATH . 'common.php'
        );
        if (defined('DEBUG') && DEBUG) {
            //测试模式
            $files = array_merge($dfiles, $preload);
            foreach ($files as $file) {
                include $file;
            }
        } else {
            //正式模式
            self::_runFile($preload, $dfiles, $refresh);
        }
        self::rootNamespace('\\', APPPATH);
    }

    /**
     * @param array $preload
     * @param array $dfiles
     * @param bool $refresh
     * @return bool
     */
    public static function _runFile($preload, $dfiles, $refresh = false) {
        $preloadfile = DATAPATH . 'preload/runtime_' . APPKEY . '_files.php';
        if (!is_file($preloadfile) || $refresh) {
            $files = array_merge($dfiles, $preload);
            $preloadfile = self::makeRunFile($files, $preloadfile);
        }
        $preloadfile && require $preloadfile;
    }

    /**
     * @param $runtimefiles
     * @param $runfile
     * @return bool
     */
    public static function makeRunFile($runtimefiles, $runfile) {
        $content = '';
        foreach ($runtimefiles as $filename) {
            $data = php_strip_whitespace($filename);
            $content .= str_replace(array('<?php', '?>', '<php_', '_php>'), array('', '', '<?php', '?>'), $data);
        }
        $filedir = dirname($runfile);
        if (!is_dir($filedir)) {
            mkdir($filedir, FILE_WRITE_MODE);
        }
        if (!is_file($runfile)) {
            file_exists($runfile) && unlink($runfile); //可能是异常文件 删除
            touch($runfile) && chmod($runfile, 0777); //生成全读写空文件
        } elseif (!is_writable($runfile)) {
            chmod($runfile, FILE_WRITE_MODE); //全读写
        }
        $ret = file_put_contents($runfile, '<?php ' . $content, LOCK_EX);
        if ($ret) {
            return $runfile;
        }
        return false;
    }

    /**
     * @param string $uri
     * @return bool
     */
    public static function dispatching($uri) {
        if (defined('ROUTE') && ROUTE) {
            self::router($uri);
        }
        $_controllerName = getgpc('g.' . self::_dCTL, getini('site/defaultController'), 'strtolower');
        $_actionName = getgpc('g.' . self::_dACT, getini('site/defaultAction'), 'strtolower');
        $controllerName = preg_replace('/[^a-z0-9_]+/i', '', $_controllerName);
        $actionName = preg_replace('/[^a-z0-9_]+/i', '', $_actionName);
        if (defined('AUTH') && AUTH) {
            $ret = Rbac::check($controllerName, $actionName, AUTH);
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
     * @throws Exception\Exception
     */
    public static function executeAction($controllerName, $actionName) {
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
            call_user_func(array($controller, $actionMethod));
            $controller = null;
            return true;
        } while (false);
        //控制器加载失败
        if (self::isAjax(true)) {
            $retarr = array(
                'errcode' => 1,
                'errmsg' => "The controller '" . $controllerName . '\' is not exists!',
                'data' => ''
            );
            return \Xcs\Util::rep_send($retarr, 'json');
        }
        self::errACT("The controller '" . $controllerName . '\' is not exists!');
    }

    /**
     * @param $group
     * @param null $vars
     * @return mixed
     */
    public static function mergeVars($group, $vars = null) {
        static $_CDATA = array(APPKEY => array('dsn' => null, 'cfg' => null, 'data' => null));
        $appkey = APPKEY;
        if (is_null($vars)) {
            return $_CDATA[$appkey][$group];
        }
        if (is_null($_CDATA[$appkey][$group])) {
            $_CDATA[$appkey][$group] = $vars;
        } else {
            $_CDATA[$appkey][$group] = array_merge($_CDATA[$appkey][$group], $vars);
        }
        return true;
    }

    /**
     * @param $args
     * @return bool
     */
    private static function errACT($args) {
        if (self::isAjax(true)) {
            $retarr = array(
                'errcode' => 1,
                'errmsg' => '出错了！' . $args,
                'data' => ''
            );
            return \Xcs\Util::rep_send($retarr, 'json');
        }
        $args = '出错了！' . $args;
        include template('404');
    }

    /**
     * @param $args
     * @return bool
     */
    private static function errACL($args) {
        if (self::isAjax(true)) {
            $retarr = array(
                'errcode' => 1,
                'errmsg' => '出错了！' . $args,
                'data' => ''
            );
            return json_encode($retarr);
        }
        $args = '出错了！' . $args;
        include template('403');
    }

    /**
     * @param $controllerName
     * @param $controllerClass
     * @return bool
     */
    private static function _loadController($controllerName, $controllerClass) {
        if (class_exists($controllerClass, false) || interface_exists($controllerClass, false)) {
            return true;
        };
        $controllerFilename = APPPATH . 'Controller/' . APPKEY . '/' . $controllerName . '.php';
        return is_file($controllerFilename) && require $controllerFilename;
    }

    /**
     * @param $uri
     * @return bool|void
     */
    public static function router($uri) {
        if (!$uri) {
            return;
        }
        if (strpos($uri, 'index.php') != false) {
            $uri = substr($uri, strpos($uri, 'index.php') + 10);
        }
        if (!self::$routes) {
            self::$routes = Context::config(APPKEY, 'route');
        }
        foreach (self::$routes as $key => $val) {
            $key = str_replace(array(':any', ':num'), array('[^/]+', '[0-9]+'), $key);
            if (preg_match('#' . $key . '#', $uri, $matches)) {
                if (strpos($val, '$') !== FALSE && strpos($key, '(') !== FALSE) {
                    $val = preg_replace('#' . $key . '#', $val, $uri);
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
    private static function setRequest($req) {
        $_GET[self::_dCTL] = array_shift($req);
        $_GET[self::_dACT] = array_shift($req);
        $parmnum = count($req);
        if (!$parmnum) {
            return;
        }
        for ($i = 0; $i < $parmnum; $i++) {
            $_GET[$req[$i]] = $req[$i + 1];
            $i++;
        }
    }

    /**
     * @param $namespace
     * @param $path
     */
    public static function rootNamespace($namespace, $path) {
        $namespace = trim($namespace, '\\');
        $path = rtrim($path, '/');
        $loader = function ($classname) use ($namespace, $path) {
            if ($namespace && stripos($classname, $namespace) !== 0) {
                return;
            }
            $file = trim(substr($classname, strlen($namespace)), '\\');
            $file = $path . '/' . str_replace('\\', '/', $file) . '.php';
            if (!is_file($file)) {
                throw new Exception\Exception($file . '不存在');
            }
            require $file;
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
    public static function vendor($class, $ext = '.php', $baseUrl = LIBPATH) {
        static $_file = array();
        $key = $class . $baseUrl . $ext;
        $class = str_replace(array('.', '#'), array('/', '.'), $class);

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
            include($filename);
            $_file[$key] = true;
            return true;
        }
        return false;
    }

    /**
     * @param bool $retbool
     * @return bool
     */
    public static function isPost($retbool = true) {
        if ('POST' == getgpc('s.REQUEST_METHOD')) {
            return $retbool;
        }
        return !$retbool;
    }

    /**
     * @param bool $retbool
     * @return bool
     */
    public static function isAjax($retbool = true) {
        if ('XMLHttpRequest' == getgpc('s.HTTP_X_REQUESTED_WITH')) {
            return $retbool;
        }
        return !$retbool;
    }
}