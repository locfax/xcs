<?php

namespace Xcs;

use Swoole\Database\PDOPool;
use Swoole\Database\RedisPool;
use Xcs\Db\MongoDb;
use Xcs\Db\MysqlDb;
use Xcs\Db\PostgresDb;
use Xcs\Db\SqliteDb;
use Xcs\Db\SwooleMysql;
use Xcs\Db\SqlsrvDb;

class DB
{

    private static $default_dbo_id = APP_DSN;
    private static $using_dbo_id;
    private static $used_dbo = [];
    private static $dbm_time_out = 0;
    private static $mgo_time_out = 0;

    /**
     * 返回 mysql 对象
     * 只有在切换不同数据库可能会用到
     * @param string $dsnId
     * @return MysqlDb
     * @see MysqlDb
     */
    public static function mysql($dsnId = 'mysql')
    {
        if (isset(self::$used_dbo[$dsnId]) && self::$dbm_time_out > time()) {
            return self::$used_dbo[$dsnId];
        }

        $dsn = Context::dsn($dsnId);

        if ('mysql' != $dsn['driver']) {
            throw new ExException('driver', 'the driver error: mysql');
        }

        self::$dbm_time_out = time() + 30;
        $object = new MysqlDb($dsn);
        self::$used_dbo[$dsnId] = $object;
        return $object;
    }

    /**
     * 返回 mongodb 对象
     * @param string $dsnId
     * @return MongoDb
     * @see MongoDb
     */
    public static function mongo($dsnId = 'mongo')
    {
        if (isset(self::$used_dbo[$dsnId]) && self::$mgo_time_out > time()) {
            return self::$used_dbo[$dsnId];
        }

        $dsn = Context::dsn($dsnId);

        if ('mongo' != $dsn['driver']) {
            throw new ExException('driver', 'the driver error: mongo');
        }

        self::$mgo_time_out = time() + 30;
        $object = new MongoDb($dsn);
        self::$used_dbo[$dsnId] = $object;
        return $object;
    }

    /**
     * @param string $dsnId
     * @return SqlsrvDb
     */
    public static function sqlsrv($dsnId = 'sqlsrv')
    {
        if (isset(self::$used_dbo[$dsnId]) && self::$dbm_time_out > time()) {
            return self::$used_dbo[$dsnId];
        }

        $dsn = Context::dsn($dsnId);

        if ('sqlsrv' != $dsn['driver']) {
            throw new ExException('driver', 'the driver error: sqlsrv');
        }

        self::$dbm_time_out = time() + 30;
        $object = new SqlsrvDb($dsn);
        self::$used_dbo[$dsnId] = $object;
        return $object;
    }

    /**
     * @param string $dsnId
     * @return SqliteDb
     */
    public static function sqlite($dsnId = 'sqlite')
    {
        if (isset(self::$used_dbo[$dsnId]) && self::$dbm_time_out > time()) {
            return self::$used_dbo[$dsnId];
        }

        $dsn = Context::dsn($dsnId);

        if ('sqlite' != $dsn['driver']) {
            throw new ExException('driver', 'the driver error: sqlite');
        }

        self::$dbm_time_out = time() + 30;
        $object = new SqliteDb($dsn);
        self::$used_dbo[$dsnId] = $object;
        return $object;
    }

    /**
     * @param string $dsnId
     * @return PostgresDb
     */
    public static function postgres($dsnId = 'postgres')
    {
        if (isset(self::$used_dbo[$dsnId]) && self::$dbm_time_out > time()) {
            return self::$used_dbo[$dsnId];
        }

        $dsn = Context::dsn($dsnId);

        if ('postgres' != $dsn['driver']) {
            throw new ExException('driver', 'the driver error: postgres');
        }

        self::$dbm_time_out = time() + 30;
        $object = new PostgresDb($dsn);
        self::$used_dbo[$dsnId] = $object;
        return $object;
    }

    /**
     * swoole 专用
     * @param string $dsnId
     * @return PDOPool
     */
    public static function SwoolePdoPool($dsnId = 'pool')
    {
        $dsn = Context::dsn($dsnId);
        return new PDOPool((new \Swoole\Database\PDOConfig)
            ->withHost($dsn['host'])
            ->withPort($dsn['port'])
            ->withDbName($dsn['dbname'])
            ->withCharset($dsn['charset'])
            ->withUsername($dsn['login'])
            ->withPassword($dsn['secret'])
        );
    }

    /**
     * swoole 专用
     * @param \Swoole\Database\PDOProxy $pdo
     * @return SwooleMysql
     */
    public static function SwooleMysql($pdo)
    {
        return new SwooleMysql($pdo);
    }

    /**
     * swoole 专用
     * @param string $dsnId
     * @return RedisPool
     */
    public static function SwooleRedisPool($dsnId = 'redis')
    {
        $dsn = Context::dsn($dsnId);
        return new RedisPool((new \Swoole\Database\RedisConfig)
            ->withHost($dsn['host'])
            ->withPort($dsn['port'])
            ->withAuth($dsn['password'])
            ->withDbIndex($dsn['index'])
            ->withTimeout($dsn['timeout'])
        );
    }

    /**
     * mysql postges
     * @return array
     */
    public static function info()
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->info();
    }

    /**
     * mysql postges
     * 插入一条数据
     * $option bool 是否返回插入的ID
     *
     * @param string $table
     * @param array $data
     * @param bool $retId
     * @return bool|string
     */
    public static function create($table, array $data, $retId = false)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->create($table, $data, $retId);
    }

    /**
     * mysql postges
     * 替换一条数据
     * PS:需要设置主键值
     *
     * @param string $table
     * @param array $data
     * @return bool|int
     */
    public static function replace($table, array $data)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->replace($table, $data);
    }

    /**
     * mysql postges
     * 更新符合条件的数据
     * @param string $table
     * @param mixed $data
     * @param mixed $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @return bool|int
     */
    public static function update($table, $data, $condition, $args = null)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->update($table, $data, $condition, $args);
    }

    /**
     * mysql postges
     * 删除符合条件的项
     * @param string $table
     * @param mixed $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param mixed $multi bool true 删除多条 返回影响数 false: 只能删除一条
     * @param mixed $args [':var' => $var]
     * @return bool|int
     */
    public static function remove($table, $condition, $multi = false, $args = null)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->remove($table, $condition, $args, $multi);
    }

    /**
     * mysql postges
     * 查找一条数据
     * @param string $table
     * @param string $field
     * @param mixed $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param mixed $orderBy
     * @param mixed $args [':var' => $var]
     * @param bool $retObj
     * @return mixed
     */
    public static function findOne($table, $field, $condition, $orderBy = null, $args = null, $retObj = false)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->findOne($table, $field, $condition, $args, $orderBy, $retObj);
    }

    /**
     * mysql postges
     * 查找多条数据
     * @param string $table
     * @param string $field
     * @param mixed $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param mixed $orderBy
     * @param mixed $args [':var' => $var]
     * @param mixed $index
     * @param bool $retObj
     * @return array|bool
     */
    public static function findAll($table, $field = '*', $condition = '', $orderBy = null, $args = null, $index = null, $retObj = false)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->findAll($table, $field, $condition, $args, $orderBy, $index, $retObj);
    }

    /**
     * mysql postges
     * 带分页数据的DB::page
     * @param string $table
     * @param string $field
     * @param mixed $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param mixed $orderBy
     * @param mixed $args [':var' => $var]
     * @param mixed $pageParam
     * @param int $limit
     * @param bool $retObj
     * @return array|bool
     */
    public static function page($table, $field, $condition = '', $orderBy = null, $args = null, $pageParam = [], $limit = 20, $retObj = false)
    {
        $db = self::Using(self::$using_dbo_id);
        if (is_array($pageParam)) {
            $_pageParam = [
                'page' => getgpc('g.page', 1),
                'udi' => url(getini('udi')),
            ];
            if (!empty($pageParam)) {
                if (!isset($pageParam['total'])) {
                    $_pageParam['total'] = self::count($table, $condition, $args);
                }
                $pageParam = array_merge($_pageParam, $pageParam);
            } else {
                $_pageParam['total'] = self::count($table, $condition, $args);
                $pageParam = $_pageParam;
            }
            $offset = self::pageStart($pageParam['page'], $limit, $pageParam['total']);
        } else {
            $offset = $pageParam;
        }
        $data = $db->page($table, $field, $condition, $args, $orderBy, $offset, $limit, $retObj);
        if (is_array($pageParam)) {
            return ['data' => $data, 'bar' => $data ? self::pageBar($pageParam, $limit) : ''];
        }
        return $data;
    }

    /**
     * mysql postges
     * 返回一条数据的第一栏
     * $filed mix  需要返回的字段  或者sql语法
     *
     * @param string $table
     * @param string $field
     * @param mixed $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param mixed $orderBy
     * @param mixed $args [':var' => $var]
     * @return mixed
     */
    public static function first($table, $field, $condition, $orderBy = null, $args = null)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->first($table, $field, $condition, $args, $orderBy);
    }

    /**
     * mysql postges
     * @param string $table
     * @param string $field
     * @param mixed $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param null $orderBy
     * @param mixed $args [':var' => $var]
     * @return array|bool
     */
    public static function col($table, $field, $condition = '', $orderBy = null, $args = null)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->col($table, $field, $condition, $args, $orderBy);
    }

    /**
     * mysql postges
     * 单表符合条件的数量
     * @param string $table
     * @param mixed $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @param string $field
     * @return mixed
     */
    public static function count($table, $condition, $args = null, $field = '*')
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->count($table, $condition, $args, $field);
    }

    /**
     * mysql postges
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @return bool|int
     */
    public static function exec($sql, $args = null)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->exec($sql, $args);
    }


    //--------------sql查询---start---------------//

    /**
     * mysql postges
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @param bool $retObj
     * @return mixed
     */
    public static function rowSql($sql, $args = null, $retObj = false)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->rowSql($sql, $args, $retObj);
    }

    /**
     * mysql postges
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @param mixed $index
     * @param bool $retObj
     * @return array|bool
     */
    public static function rowSetSql($sql, $args = null, $index = null, $retObj = false)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->rowSetSql($sql, $args, $index, $retObj);
    }

    /**
     * mysql postges
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @param mixed $pageParam
     * @param int $limit
     * @param bool $retObj
     * @return mixed
     */
    public static function pageSql($sql, $args = null, $pageParam = 0, $limit = 18, $retObj = false)
    {
        $db = self::Using(self::$using_dbo_id);
        if (is_array($pageParam)) {
            $offset = self::pageStart($pageParam['page'], $limit, $pageParam['total']);
        } else {
            $offset = $pageParam;
        }
        $data = $db->pageSql($sql, $args, $offset, $limit, $retObj);
        if (is_array($pageParam)) {
            return ['data' => $data, 'bar' => $data ? self::pageBar($pageParam, $limit) : ''];
        }
        return $data;
    }

    /**
     * mysql postges
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @return mixed
     */
    public static function countSql($sql, $args = null)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->countSql($sql, $args);
    }

    /**
     * mysql postges
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @return mixed
     */
    public static function firstSql($sql, $args = null)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->firstSql($sql, $args);
    }

    /**
     * mysql postges
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @return array|bool
     */
    public static function colSql($sql, $args = null)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->colSql($sql, $args);
    }

    //--------------多表查询---end---------------//

    /**
     * mysql postges
     * 开始事务
     * @return bool
     */
    public static function startTrans()
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->startTrans();
    }

    /**
     * mysql postges
     * 事务提交或者回滚
     * @param bool $commit_no_errors
     */
    public static function endTrans($commit_no_errors = true)
    {
        $db = self::Using(self::$using_dbo_id);
        $db->endTrans($commit_no_errors);
    }

    //----------------------事务END-------------------//

    /**
     * mysql postges
     * 切换数据源对象
     * @param string|null $id
     * @return MysqlDb | PostgresDb
     */
    public static function Using($id = null)
    {
        if (!$id) {
            //初始运行
            self::$using_dbo_id = self::$default_dbo_id;
        } else {
            //切换dbo id
            self::$using_dbo_id = $id;
        }

        if (!in_array(self::$using_dbo_id, ['mysql', 'postgres'])) {
            throw new ExException('driver', 'id must be mysql | postgres');
        }

        if (self::$using_dbo_id == 'mysql') {
            return self::mysql(self::$using_dbo_id);
        } elseif (self::$using_dbo_id == 'postgres') {
            return self::postgres(self::$default_dbo_id);
        } else {
            throw new ExException('driver', 'dsn id is error. must be mysql | postgres');
        }
    }

    /**
     * @param int $page
     * @param int $ppp
     * @param int $total
     * @return int
     */
    public static function pageStart($page, $ppp, $total)
    {
        $totalPage = ceil($total / $ppp);
        $_page = max(1, min($totalPage, intval($page)));
        return ($_page - 1) * $ppp;
    }

    /**
     * @param array|int $pageParam
     * @param int $length
     * @return array|string
     */
    public static function pageBar($pageParam, $length)
    {
        if (!isset($pageParam['bar']) || 'default' == $pageParam['bar']) {
            $defPageParam = [
                'page' => 1,
                'udi' => '',
                'maxpages' => 100,
                'showpage' => 10,
                'shownum' => true,
                'showkbd' => false,
                'simple' => false
            ];
            $pageParam = array_merge($defPageParam, $pageParam);
            $pageParam['length'] = $length;
            $pageBar = Helper\Pager::pageBar($pageParam);
        } elseif ('simple' == $pageParam['bar']) {
            $defPageParam = [
                'page' => 1,
                'udi' => '',
                'maxpages' => 100,
            ];
            $pageParam = array_merge($defPageParam, $pageParam);
            $pageParam['length'] = $length;
            $pageBar = Helper\Pager::simplePage($pageParam);
        } else {
            $pages = ceil($pageParam['total'] / $length);
            $nextPage = ($pages > $pageParam['page']) ? $pageParam['page'] + 1 : $pages;
            $pageBar = [
                'total' => $pageParam['total'],
                'count' => $pages,
                'pre' => $pageParam['page'] - 1 > 0 ? $pageParam['page'] - 1 : 1,
                'page' => $pageParam['page'],
                'next' => $nextPage
            ];
        }
        return $pageBar;
    }

    /**
     * @param $arr
     * @return string
     */
    public static function ids($arr)
    {
        return implode(',', (array)$arr);
    }

    /**
     * @param $arr
     * @return string
     */
    public static function implode($arr)
    {
        return "'" . implode("','", (array)$arr) . "'";
    }
}
