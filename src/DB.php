<?php

namespace Xcs;

use Xcs\Db\MongoDb;
use Xcs\Db\PdoDb;
use Xcs\Db\PdoPool;
use Xcs\Db\SqlsrvDb;

class DB
{

    private static $default_dbo_id = APP_DSN;
    private static $using_dbo_id;
    private static $used_dbo = [];
    private static $dbm_time_out = 0;
    private static $mgo_time_out = 0;

    /**
     * 返回 PDO 对象
     * 通常用 DB::find*  DB::row* DB::page* ...
     * 只有在切换不同数据库可能会用到
     * @param string $dsnId
     * @return PdoDb
     * @see PdoDb
     */
    public static function dbm(string $dsnId = 'default'): PdoDb
    {
        if (isset(self::$used_dbo[$dsnId]) && self::$dbm_time_out > time()) {
            return self::$used_dbo[$dsnId];
        }

        $dsn = Context::dsn($dsnId);

        if ('PdoDb' != $dsn['driver']) {
            new ExException("the driver error: PdoDb");
        }

        self::$dbm_time_out = time() + 30;
        $object = new PdoDb($dsn);
        self::$used_dbo[$dsnId] = $object;
        return $object;
    }

    /**
     * 返回 mongodb 对象
     * @param string $dsnId
     * @return MongoDb
     * @see MongoDb
     */
    public static function mgo(string $dsnId = 'mongo'): MongoDb
    {
        if (isset(self::$used_dbo[$dsnId]) && self::$mgo_time_out > time()) {
            return self::$used_dbo[$dsnId];
        }

        $dsn = Context::dsn($dsnId);

        if ('MongoDb' != $dsn['driver']) {
            new ExException("the driver error: MongoDb");
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
    public static function sqlsrv(string $dsnId = 'sqlsrv'): SqlsrvDb
    {
        if (isset(self::$used_dbo[$dsnId]) && self::$dbm_time_out > time()) {
            return self::$used_dbo[$dsnId];
        }

        $dsn = Context::dsn($dsnId);

        if ('SqlsrvDb' != $dsn['driver']) {
            new ExException("the driver error: SqlsrvDb");
        }

        self::$dbm_time_out = time() + 30;
        $object = new SqlsrvDb($dsn);
        self::$used_dbo[$dsnId] = $object;
        return $object;
    }

    /**
     * swoole 专用
     * @param string $dsnId
     * @return \Swoole\Database\PDOPool
     */
    public static function PdoPool(string $dsnId = 'pool'): \Swoole\Database\PDOPool
    {
        $dsn = Context::dsn($dsnId);
        return new \Swoole\Database\PDOPool((new \Swoole\Database\PDOConfig)
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
     * @param \Swoole\Database\PDOPool $pdo
     * @return PdoPool
     */
    public static function getPdoPool(\Swoole\Database\PDOPool $pdo): PdoPool
    {
        return new PdoPool($pdo);
    }

    /**
     * swoole 专用
     * @param string $dsnId
     * @return \Swoole\Database\RedisPool
     */
    public static function getRedisPool(string $dsnId = 'redis'): \Swoole\Database\RedisPool
    {
        $dsn = Context::dsn($dsnId);
        return new \Swoole\Database\RedisPool((new \Swoole\Database\RedisConfig)
            ->withHost($dsn['host'])
            ->withPort($dsn['port'])
            ->withAuth($dsn['password'])
            ->withDbIndex($dsn['index'])
            ->withTimeout($dsn['timeout'])
        );
    }

    /**
     * 还原默认数据源对象
     */
    public static function resume()
    {
        self::$using_dbo_id = self::$default_dbo_id;
    }

    /**
     * mysql专用
     * @return array
     */
    public static function info(): array
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->info();
    }

    /**
     * mysql专用
     * 插入一条数据
     * $option bool 是否返回插入的ID
     *
     * @param string $table
     * @param array $data
     * @param bool $retId
     * @return bool|string
     */
    public static function create(string $table, array $data, bool $retId = false)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->create($table, $data, $retId);
    }

    /**
     * mysql专用
     * 替换一条数据
     * PS:需要设置主键值
     *
     * @param string $table
     * @param array $data
     * @return bool|int
     */
    public static function replace(string $table, array $data)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->replace($table, $data);
    }

    /**
     * mysql专用
     * 更新符合条件的数据
     * @param string $table
     * @param mixed $data
     * @param mixed $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @return bool|int
     */
    public static function update(string $table, $data, $condition, $args = null)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->update($table, $data, $condition, $args);
    }

    /**
     * mysql专用
     * 删除符合条件的项
     * @param string $table
     * @param mixed $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param mixed $multi bool true 删除多条 返回影响数 false: 只能删除一条
     * @param mixed $args [':var' => $var]
     * @return bool|int
     */
    public static function remove(string $table, $condition, $multi = false, $args = null)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->remove($table, $condition, $args, $multi);
    }

    /**
     * mysql专用
     * 查找一条数据
     * @param string $table
     * @param string $field
     * @param mixed $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param mixed $orderBy
     * @param mixed $args [':var' => $var]
     * @param bool $retObj
     * @return mixed
     */
    public static function findOne(string $table, string $field, $condition, $orderBy = null, $args = null, bool $retObj = false)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->findOne($table, $field, $condition, $args, $orderBy, $retObj);
    }

    /**
     * mysql专用
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
    public static function findAll(string $table, string $field = '*', $condition = '', $orderBy = null, $args = null, $index = null, bool $retObj = false)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->findAll($table, $field, $condition, $args, $orderBy, $index, $retObj);
    }

    /**
     * mysql专用
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
    public static function page(string $table, string $field, $condition = '', $orderBy = null, $args = null, $pageParam = 0, int $limit = 20, bool $retObj = false)
    {
        $db = self::Using(self::$using_dbo_id);
        if (is_array($pageParam)) {
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
     * mysql专用
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
    public static function first(string $table, string $field, $condition, $orderBy = null, $args = null)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->first($table, $field, $condition, $args, $orderBy);
    }

    /**
     * mysql专用
     * @param string $table
     * @param string $field
     * @param mixed $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param null $orderBy
     * @param mixed $args [':var' => $var]
     * @return array|bool
     */
    public static function col(string $table, string $field, $condition = '', $orderBy = null, $args = null)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->col($table, $field, $condition, $args, $orderBy);
    }

    /**
     * mysql专用
     * 单表符合条件的数量
     * @param string $table
     * @param mixed $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @param string $field
     * @return mixed
     */
    public static function count(string $table, $condition, $args = null, string $field = '*')
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->count($table, $condition, $args, $field);
    }

    /**
     * mysql专用
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @return bool|int
     */
    public static function exec(string $sql, $args = null)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->exec($sql, $args);
    }


    //--------------sql查询---start---------------//

    /**
     * mysql专用
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @param bool $retObj
     * @return mixed
     */
    public static function rowSql(string $sql, $args = null, bool $retObj = false)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->rowSql($sql, $args, $retObj);
    }

    /**
     * mysql专用
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @param mixed $index
     * @param bool $retObj
     * @return array|bool
     */
    public static function rowSetSql(string $sql, $args = null, $index = null, bool $retObj = false)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->rowSetSql($sql, $args, $index, $retObj);
    }

    /**
     * mysql专用
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @param mixed $pageParam
     * @param int $limit
     * @param bool $retObj
     * @return mixed
     */
    public static function pageSql(string $sql, $args = null, $pageParam = 0, int $limit = 18, bool $retObj = false): array
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
     * mysql专用
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @return mixed
     */
    public static function countSql(string $sql, $args = null)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->countSql($sql, $args);
    }

    /**
     * mysql专用
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @return mixed
     */
    public static function firstSql(string $sql, $args = null)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->firstSql($sql, $args);
    }

    /**
     * mysql专用
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @return array|bool
     */
    public static function colSql(string $sql, $args = null)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->colSql($sql, $args);
    }

    //--------------多表查询---end---------------//

    /**
     * mysql专用
     * 开始事务
     * @return bool
     */
    public static function startTrans(): bool
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->startTrans();
    }

    /**
     * mysql专用
     * 事务提交或者回滚
     * @param bool $commit_no_errors
     */
    public static function endTrans(bool $commit_no_errors = true)
    {
        $db = self::Using(self::$using_dbo_id);
        $db->endTrans($commit_no_errors);
    }

    //----------------------事务END-------------------//

    /**
     * mysql专用
     * 切换数据源对象
     * @param string|null $id
     * @return PdoDb
     */
    public static function Using(string $id = null): PdoDb
    {
        if (!$id) {
            //初始运行
            self::$using_dbo_id = self::$default_dbo_id;
        } else {
            //切换dbo id
            self::$using_dbo_id = $id;
        }
        return self::dbm(self::$using_dbo_id);
    }

    /**
     * @param int $page
     * @param int $ppp
     * @param int $total
     * @return int
     */
    public static function pageStart(int $page, int $ppp, int $total)
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
    public static function pageBar($pageParam, int $length)
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
