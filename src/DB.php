<?php

namespace Xcs;

use Xcs\Db\MongoDb;
use Xcs\Db\MysqlDb;
use Xcs\Db\PostgresDb;
use Xcs\Db\SqliteDb;
use Xcs\Db\SqlsrvDb;

class DB
{

    private static string $using_dbo_id = APP_DSN;
    private static array $used_dbo = [];

    /**
     * 返回 mysql 对象
     * 只有在切换不同数据库可能会用到
     * @param string $dsnId
     * @param mixed $dsn
     * @return MysqlDb
     * @throws ExException
     * @see MysqlDb
     */
    public static function mysql(string $dsnId = 'mysql', $dsn = ''): MysqlDb
    {
        if (isset(self::$used_dbo[$dsnId])) {
            return self::$used_dbo[$dsnId];
        }

        if (empty($dsn)) {
            $dsn = Context::dsn($dsnId);
        }

        if ('mysql' != $dsn['driver']) {
            throw new ExException($dsn['driver'] . ' the driver error: mysql');
        }

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
    public static function mongo(string $dsnId = 'mongo', $dsn = ''): MongoDb
    {
        if (isset(self::$used_dbo[$dsnId])) {
            return self::$used_dbo[$dsnId];
        }

        if (empty($dsn)) {
            $dsn = Context::dsn($dsnId);
        }

        if ('mongo' != $dsn['driver']) {
            throw new ExException('the driver error: mongo');
        }

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
    public static function sqlsrv(string $dsnId = 'sqlsrv', $dsn = ''): SqlsrvDb
    {
        if (isset(self::$used_dbo[$dsnId])) {
            return self::$used_dbo[$dsnId];
        }

        if (empty($dsn)) {
            $dsn = Context::dsn($dsnId);
        }

        if ('sqlsrv' != $dsn['driver']) {
            throw new ExException('the driver error: sqlsrv');
        }

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
    public static function sqlite(string $dsnId = 'sqlite', $dsn = ''): SqliteDb
    {
        if (isset(self::$used_dbo[$dsnId])) {
            return self::$used_dbo[$dsnId];
        }

        if (empty($dsn)) {
            $dsn = Context::dsn($dsnId);
        }

        if ('sqlite' != $dsn['driver']) {
            throw new ExException('the driver error: sqlite');
        }

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
    public static function postgres(string $dsnId = 'postgres', $dsn = ''): PostgresDb
    {
        if (isset(self::$used_dbo[$dsnId])) {
            return self::$used_dbo[$dsnId];
        }

        if (empty($dsn)) {
            $dsn = Context::dsn($dsnId);
        }

        if ('postgres' != $dsn['driver']) {
            throw new ExException('the driver error: postgres');
        }

        $object = new PostgresDb($dsn);
        self::$used_dbo[$dsnId] = $object;
        return $object;
    }

    /**
     * mysql postgres
     * @return array
     * @throws ExException
     */
    public static function info(): array
    {
        return self::Using()->info();
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
    public static function create(string $table, array $data, bool $retId = false)
    {
        return self::Using()->create($table, $data, $retId);
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
    public static function replace(string $table, array $data)
    {
        return self::Using()->replace($table, $data);
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
    public static function update(string $table, $data, $condition, $args = [])
    {
        return self::Using()->update($table, $data, $condition, $args);
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
    public static function remove(string $table, $condition, $multi = false, $args = [])
    {
        return self::Using()->remove($table, $condition, $args, $multi);
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
    public static function findOne(string $table, string $field, $condition, $orderBy = '', $args = [], bool $retObj = false)
    {
        return self::Using()->findOne($table, $field, $condition, $args, $orderBy, $retObj);
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
    public static function findAll(string $table, string $field = '*', $condition = '', $orderBy = '', $args = [], $index = '', bool $retObj = false)
    {
        return self::Using()->findAll($table, $field, $condition, $args, $orderBy, $index, $retObj);
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
    public static function page(string $table, string $field, $condition = '', $orderBy = '', $args = [], $pageParam = [], int $limit = 20, bool $retObj = false)
    {
        $db = self::Using();
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
    public static function first(string $table, string $field, $condition, $orderBy = '', $args = [])
    {
        return self::Using()->first($table, $field, $condition, $args, $orderBy);
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
    public static function col(string $table, string $field, $condition = '', $orderBy = '', $args = [])
    {
        return self::Using()->col($table, $field, $condition, $args, $orderBy);
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
    public static function count(string $table, $condition, $args = [], string $field = '*')
    {
        return self::Using()->count($table, $condition, $args, $field);
    }

    /**
     * mysql postgres
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @return bool|int
     * @throws ExException
     */
    public static function exec(string $sql, $args = [])
    {
        return self::Using()->exec($sql, $args);
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
    public static function rowSql(string $sql, $args = [], bool $retObj = false)
    {
        return self::Using()->rowSql($sql, $args, $retObj);
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
    public static function rowSetSql(string $sql, $args = [], $index = '', bool $retObj = false)
    {
        return self::Using()->rowSetSql($sql, $args, $index, $retObj);
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
    public static function pageSql(string $sql, $args = [], $pageParam = 0, int $limit = 18, bool $retObj = false)
    {
        $db = self::Using();
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
    public static function countSql(string $sql, $args = [])
    {
        return self::Using()->countSql($sql, $args);
    }

    /**
     * mysql postgres
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @return mixed
     * @throws ExException
     */
    public static function firstSql(string $sql, $args = [])
    {
        return self::Using()->firstSql($sql, $args);
    }

    /**
     * mysql postgres
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @return array|bool
     * @throws ExException
     */
    public static function colSql(string $sql, $args = [])
    {
        return self::Using()->colSql($sql, $args);
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
        return self::Using()->startTrans();
    }

    /**
     * mysql postgres
     * 事务提交或者回滚
     * @param bool $commit_no_errors
     * @throws ExException
     */
    public static function endTrans(bool $commit_no_errors = true): void
    {
        self::Using()->endTrans($commit_no_errors);
    }

    //----------------------事务END-------------------//

    /**
     * 数据源对象
     * @param string|null $id
     * @throws ExException
     */
    private static function Using()
    {
        //使用默认的方式
        $dsn = Context::dsn(self::$using_dbo_id);
        if ($dsn['driver'] == 'mysql') {
            return self::mysql(self::$using_dbo_id, $dsn);
        } elseif ($dsn['driver'] == 'postgres') {
            return self::postgres(self::$using_dbo_id, $dsn);
        } else {
            throw new ExException('dsn id is error.');
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
