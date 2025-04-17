<?php

namespace Xcs;

use Swoole\Database\PDOConfig;
use Swoole\Database\PDOPool;
use Swoole\Database\PDOProxy;
use Swoole\Database\RedisConfig;
use Swoole\Database\RedisPool;

use Xcs\Db\MongoDb;
use Xcs\Db\MysqlDb;
use Xcs\Db\PostgresDb;
use Xcs\Db\SqliteDb;
use Xcs\Db\SwooleMysql;
use Xcs\Db\SqlsrvDb;

class DB
{

    private static string $default_dbo_id = APP_DSN;
    private static string $using_dbo_id = '';
    private static array $used_dbo = [];
    private static int $dbm_time_out = 0;
    private static int $mgo_time_out = 0;

    /**
     * 返回 mysql 对象
     * 只有在切换不同数据库可能会用到
     * @param string $dsnId
     * @param mixed $dsn
     * @return MysqlDb
     * @throws ExException
     * @see MysqlDb
     */
    public static function mysql(string $dsnId = 'mysql', mixed $dsn = null): MysqlDb
    {
        if (isset(self::$used_dbo[$dsnId]) && self::$dbm_time_out > time()) {
            return self::$used_dbo[$dsnId];
        }

        if (is_null($dsn)) {
            $dsn = Context::dsn($dsnId);
        }

        if ('mysql' != $dsn['driver']) {
            throw new ExException($dsn['driver'], 'the driver error: mysql');
        }

        self::$dbm_time_out = time() + 30;
        $object = new MysqlDb($dsn);
        self::$used_dbo[$dsnId] = $object;
        return $object;
    }

    /**
     * 返回 mongodb 对象
     * @param string $dsnId
     * @param mixed $dsn
     * @return MongoDb
     * @throws ExException
     * @see MongoDb
     */
    public static function mongo(string $dsnId = 'mongo', mixed $dsn = null): MongoDb
    {
        if (isset(self::$used_dbo[$dsnId]) && self::$mgo_time_out > time()) {
            return self::$used_dbo[$dsnId];
        }

        if (is_null($dsn)) {
            $dsn = Context::dsn($dsnId);
        }

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
     * @param mixed $dsn
     * @return SqlsrvDb
     * @throws ExException
     */
    public static function sqlsrv(string $dsnId = 'sqlsrv', mixed $dsn = null): SqlsrvDb
    {
        if (isset(self::$used_dbo[$dsnId]) && self::$dbm_time_out > time()) {
            return self::$used_dbo[$dsnId];
        }

        if (is_null($dsn)) {
            $dsn = Context::dsn($dsnId);
        }

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
     * @param mixed $dsn
     * @return SqliteDb
     * @throws ExException
     */
    public static function sqlite(string $dsnId = 'sqlite', mixed $dsn = null): SqliteDb
    {
        if (isset(self::$used_dbo[$dsnId]) && self::$dbm_time_out > time()) {
            return self::$used_dbo[$dsnId];
        }

        if (is_null($dsn)) {
            $dsn = Context::dsn($dsnId);
        }

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
     * @param mixed $dsn
     * @return PostgresDb
     * @throws ExException
     */
    public static function postgres(string $dsnId = 'postgres', mixed $dsn = null): PostgresDb
    {
        if (isset(self::$used_dbo[$dsnId]) && self::$dbm_time_out > time()) {
            return self::$used_dbo[$dsnId];
        }

        if (is_null($dsn)) {
            $dsn = Context::dsn($dsnId);
        }

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
     * @throws ExException
     */
    public static function SwoolePdoPool(string $dsnId = 'pool'): PDOPool
    {
        $dsn = Context::dsn($dsnId);
        return new PDOPool((new PDOConfig)
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
     * @param PDOProxy $pdo
     * @return SwooleMysql
     */
    public static function SwooleMysql(PDOProxy $pdo): SwooleMysql
    {
        return new SwooleMysql($pdo);
    }

    /**
     * swoole 专用
     * @param string $dsnId
     * @return RedisPool
     * @throws ExException
     */
    public static function SwooleRedisPool(string $dsnId = 'redis'): RedisPool
    {
        $dsn = Context::dsn($dsnId);
        return new RedisPool((new RedisConfig)
            ->withHost($dsn['host'])
            ->withPort($dsn['port'])
            ->withAuth($dsn['password'])
            ->withDbIndex($dsn['index'])
            ->withTimeout($dsn['timeout'])
        );
    }

    /**
     * mysql postgres
     * @return array
     * @throws ExException
     */
    public static function info(): array
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->info();
    }

    /**
     * mysql postgres
     * 插入一条数据
     * $option bool 是否返回插入的ID
     *
     * @param string $table
     * @param array $data
     * @param bool $retId
     * @return bool|string
     * @throws ExException
     */
    public static function create(string $table, array $data, bool $retId = false): bool|string
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->create($table, $data, $retId);
    }

    /**
     * mysql postgres
     * 替换一条数据
     * PS:需要设置主键值
     *
     * @param string $table
     * @param array $data
     * @return bool|int
     * @throws ExException
     */
    public static function replace(string $table, array $data): bool|int
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->replace($table, $data);
    }

    /**
     * mysql postgres
     * 更新符合条件的数据
     * @param string $table
     * @param mixed $data
     * @param mixed $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @return bool|int
     * @throws ExException
     */
    public static function update(string $table, mixed $data, mixed $condition, mixed $args = null): bool|int
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->update($table, $data, $condition, $args);
    }

    /**
     * mysql postgres
     * 删除符合条件的项
     * @param string $table
     * @param mixed $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param mixed $multi bool true 删除多条 返回影响数 false: 只能删除一条
     * @param mixed $args [':var' => $var]
     * @return bool|int
     * @throws ExException
     */
    public static function remove(string $table, mixed $condition, mixed $multi = false, mixed $args = null): bool|int
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->remove($table, $condition, $args, $multi);
    }

    /**
     * mysql PostgreSQL
     * 查找一条数据
     * @param string $table
     * @param string $field
     * @param mixed $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param mixed|null $orderBy
     * @param mixed|null $args [':var' => $var]
     * @param bool $retObj
     * @return mixed
     * @throws ExException
     */
    public static function findOne(string $table, string $field, mixed $condition, mixed $orderBy = null, mixed $args = null, bool $retObj = false): mixed
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->findOne($table, $field, $condition, $args, $orderBy, $retObj);
    }

    /**
     * mysql postgres
     * 查找多条数据
     * @param string $table
     * @param string $field
     * @param mixed $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param mixed $orderBy
     * @param mixed $args [':var' => $var]
     * @param mixed $index
     * @param bool $retObj
     * @return array|bool
     * @throws ExException
     */
    public static function findAll(string $table, string $field = '*', mixed $condition = '', mixed $orderBy = null, mixed $args = null, mixed $index = null, bool $retObj = false): bool|array
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->findAll($table, $field, $condition, $args, $orderBy, $index, $retObj);
    }

    /**
     * mysql postgres
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
     * @throws ExException
     */
    public static function page(string $table, string $field, mixed $condition = '', mixed $orderBy = null, mixed $args = null, mixed $pageParam = [], int $limit = 20, bool $retObj = false): bool|array
    {
        $db = self::Using(self::$using_dbo_id);
        if (is_array($pageParam)) {
            $_pageParam = [
                'page' => 1,
                'maxPages' => 0,
                'showPage' => 10,
                'showNum' => false,
                'length' => $limit,
            ];

            if (!isset($pageParam['udi'])) {
                $_pageParam['udi'] = url(getini('udi'));
            }

            if (!empty($pageParam)) {
                if (!isset($pageParam['total'])) {
                    $_pageParam['total'] = self::count($table, $condition, $args);
                }
                $pageParam = array_merge($_pageParam, $pageParam);
            } else {
                $_pageParam['total'] = self::count($table, $condition, $args);
                $pageParam = $_pageParam;
            }
            $realPages = ceil($pageParam['total'] / $pageParam['length']);

            //共有多少页
            $pageParam['pages'] = $pageParam['maxPages'] ? min($realPages, $pageParam['maxPages']) : $realPages;
            //当前页
            $pageParam['page'] = $pageParam['maxPages'] ? max(1, min($pageParam['page'], $realPages, $pageParam['maxPages'])) : max(1, min($pageParam['page'], $realPages));

            $offset = ($pageParam['page'] - 1) * $pageParam['length'];
        } else {
            $offset = $pageParam;
        }
        $data = $db->page($table, $field, $condition, $args, $orderBy, $offset, $limit, $retObj);
        if (is_array($pageParam)) {
            return ['total' => $pageParam['total'], 'rows' => $data, 'pagebar' => $data ? Helper\Pager::pageBar($pageParam) : ''];
        }
        return $data;
    }

    /**
     * mysql postgres
     * 返回一条数据的第一栏
     * $filed mix  需要返回的字段  或者sql语法
     *
     * @param string $table
     * @param string $field
     * @param mixed $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param mixed $orderBy
     * @param mixed $args [':var' => $var]
     * @return mixed
     * @throws ExException
     */
    public static function first(string $table, string $field, mixed $condition, mixed $orderBy = null, mixed $args = null): mixed
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->first($table, $field, $condition, $args, $orderBy);
    }

    /**
     * mysql postgres
     * @param string $table
     * @param string $field
     * @param mixed $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param mixed $orderBy
     * @param mixed $args [':var' => $var]
     * @return array|bool
     * @throws ExException
     */
    public static function col(string $table, string $field, mixed $condition = '', mixed $orderBy = null, mixed $args = null): bool|array
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->col($table, $field, $condition, $args, $orderBy);
    }

    /**
     * mysql postgres
     * 单表符合条件的数量
     * @param string $table
     * @param mixed $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @param string $field
     * @return mixed
     * @throws ExException
     */
    public static function count(string $table, mixed $condition, mixed $args = null, string $field = '*'): mixed
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->count($table, $condition, $args, $field);
    }

    /**
     * mysql postgres
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @return bool|int
     * @throws ExException
     */
    public static function exec(string $sql, mixed $args = null): bool|int
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
     * @throws ExException
     */
    public static function rowSql(string $sql, mixed $args = null, bool $retObj = false): mixed
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->rowSql($sql, $args, $retObj);
    }

    /**
     * mysql postgres
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @param mixed $index
     * @param bool $retObj
     * @return array|bool
     * @throws ExException
     */
    public static function rowSetSql(string $sql, mixed $args = null, mixed $index = null, bool $retObj = false): bool|array
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->rowSetSql($sql, $args, $index, $retObj);
    }

    /**
     * mysql postgres
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @param mixed $pageParam
     * @param int $limit
     * @param bool $retObj
     * @return array|bool
     * @throws ExException
     */
    public static function pageSql(string $sql, mixed $args = null, mixed $pageParam = 0, int $limit = 18, bool $retObj = false): bool|array
    {
        $db = self::Using(self::$using_dbo_id);
        if (is_array($pageParam)) {
            $_pageParam = [
                'page' => 1,
                'maxPages' => 0,
                'showPage' => 10,
                'showNum' => false,
                'length' => $limit,
            ];
            if (!isset($pageParam['udi'])) {
                $_pageParam['udi'] = url(getini('udi'));
            }
            $pageParam = array_merge($_pageParam, $pageParam);
            $offset = self::pageStart($pageParam['page'], $limit, $pageParam['total']);
        } else {
            $offset = $pageParam;
        }
        $data = $db->pageSql($sql, $args, $offset, $limit, $retObj);
        if (is_array($pageParam)) {
            return ['total' => $pageParam['total'], 'rows' => $data, 'pagebar' => $data ? Helper\Pager::pageBar($pageParam) : ''];
        }
        return $data;
    }

    /**
     * mysql postgres
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @return mixed
     * @throws ExException
     */
    public static function countSql(string $sql, mixed $args = null): mixed
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->countSql($sql, $args);
    }

    /**
     * mysql postgres
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @return mixed
     * @throws ExException
     */
    public static function firstSql(string $sql, mixed $args = null): mixed
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->firstSql($sql, $args);
    }

    /**
     * mysql postgres
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @return array|bool
     * @throws ExException
     */
    public static function colSql(string $sql, mixed $args = null): bool|array
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->colSql($sql, $args);
    }

    //--------------多表查询---end---------------//

    /**
     * mysql postgres
     * 开始事务
     * @return bool
     * @throws ExException
     */
    public static function startTrans(): bool
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->startTrans();
    }

    /**
     * mysql postgres
     * 事务提交或者回滚
     * @param bool $commit_no_errors
     * @throws ExException
     */
    public static function endTrans(bool $commit_no_errors = true): void
    {
        $db = self::Using(self::$using_dbo_id);
        $db->endTrans($commit_no_errors);
    }

    //----------------------事务END-------------------//

    /**
     * mysql postgres
     * 切换数据源对象
     * @param string|null $id
     * @return MysqlDb | PostgresDb
     * @throws ExException
     */
    public static function Using(string $id = null): MysqlDb|PostgresDb
    {
        if (!$id) {
            //初始运行
            self::$using_dbo_id = self::$default_dbo_id;
        } else {
            //切换dbo id
            self::$using_dbo_id = $id;
        }

        $dsn = Context::dsn(self::$using_dbo_id);

        if ($dsn['driver'] == 'mysql') {
            return self::mysql(self::$using_dbo_id, $dsn);
        } elseif ($dsn['driver'] == 'postgres') {
            return self::postgres(self::$using_dbo_id, $dsn);
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
    public static function pageStart(int $page, int $ppp, int $total): int
    {
        $totalPage = ceil($total / $ppp);
        $_page = max(1, min($totalPage, $page));
        return ($_page - 1) * $ppp;
    }

    /**
     * @param $arr
     * @return string
     */
    public static function ids($arr): string
    {
        return implode(',', (array)$arr);
    }

    /**
     * @param $arr
     * @return string
     */
    public static function implode($arr): string
    {
        return "'" . implode("','", (array)$arr) . "'";
    }
}
