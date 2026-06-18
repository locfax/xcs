<?php

namespace Xcs;

use Xcs\Db\MongoDb;
use Xcs\Db\MysqlDb;
use Xcs\Db\PostgresDb;
use Xcs\Db\SqliteDb;
use Xcs\Db\SqlsrvDb;

class DB
{
    private static string $using_dsn = APP_DSN;
    private static array $used_dbo = [];

    /**
     * 返回 mysql 对象
     * 只有在切换不同数据库可能会用到
     * @param string $dsnId
     * @return MysqlDb
     */
    public static function mysql(string $dsnId = 'mysql'): MysqlDb
    {
        if (isset(self::$used_dbo[$dsnId])) {
            return self::$used_dbo[$dsnId];
        }
        $object = new MysqlDb(Context::dsn($dsnId));
        self::$used_dbo[$dsnId] = $object;
        return $object;
    }

    /**
     * 返回 mongodb 对象
     * @param string $dsnId
     * @return MongoDb
     */
    public static function mongo(string $dsnId = 'mongo'): MongoDb
    {
        if (isset(self::$used_dbo[$dsnId])) {
            return self::$used_dbo[$dsnId];
        }
        $object = new MongoDb(Context::dsn($dsnId));
        self::$used_dbo[$dsnId] = $object;
        return $object;
    }

    /**
     * @param string $dsnId
     * @return SqlsrvDb
     */
    public static function sqlsrv(string $dsnId = 'sqlsrv'): SqlsrvDb
    {
        if (isset(self::$used_dbo[$dsnId])) {
            return self::$used_dbo[$dsnId];
        }
        $object = new SqlsrvDb(Context::dsn($dsnId));
        self::$used_dbo[$dsnId] = $object;
        return $object;
    }

    /**
     * @param string $dsnId
     * @return SqliteDb
     */
    public static function sqlite(string $dsnId = 'sqlite'): SqliteDb
    {
        if (isset(self::$used_dbo[$dsnId])) {
            return self::$used_dbo[$dsnId];
        }
        $object = new SqliteDb(Context::dsn($dsnId));
        self::$used_dbo[$dsnId] = $object;
        return $object;
    }

    /**
     * @param string $dsnId
     * @return PostgresDb
     */
    public static function postgres(string $dsnId = 'postgres'): PostgresDb
    {
        if (isset(self::$used_dbo[$dsnId])) {
            return self::$used_dbo[$dsnId];
        }
        $object = new PostgresDb(Context::dsn($dsnId));
        self::$used_dbo[$dsnId] = $object;
        return $object;
    }

    /**
     * mysql
     * 插入一条数据
     * $option bool 是否返回插入的ID
     *
     * @param string $table
     * @param array $data
     * @param bool $retId
     * @return bool|int|string
     */
    public static function create(string $table, array $data, bool $retId = false): bool|int|string
    {
        return self::Using()->create($table, $data, $retId);
    }

    /**
     * mysql
     * 替换一条数据
     * PS:需要设置主键值
     *
     * @param string $table
     * @param array $data
     * @return bool|int
     */
    public static function replace(string $table, array $data): bool|int
    {
        return self::Using()->replace($table, $data);
    }

    /**
     * mysql
     * 更新符合条件的数据
     * @param string $table
     * @param array|string $data
     * @param array|string $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array $args [':var' => $var]
     * @return bool|int
     * @throws ExException
     */
    public static function update(string $table, array|string $data, array|string $condition, array $args = []): bool|int
    {
        return self::Using()->update($table, $data, $condition, $args);
    }

    /**
     * mysql
     * 删除符合条件的项
     * @param string $table
     * @param array|string $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param bool $multi bool true 删除多条 返回影响数 false: 只能删除一条
     * @param array $args [':var' => $var]
     * @return bool|int
     */
    public static function remove(string $table, array|string $condition, bool $multi = false, array $args = []): bool|int
    {
        return self::Using()->remove($table, $condition, $args, $multi);
    }

    /**
     * mysql
     * 查找一条数据
     * @param string $table
     * @param string $field
     * @param array|string $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param string $orderBy
     * @param array $args [':var' => $var]
     * @param bool $retObj
     * @return mixed
     */
    public static function findOne(string $table, string $field, array|string $condition, string $orderBy = '', array $args = [], bool $retObj = false): mixed
    {
        return self::Using()->findOne($table, $field, $condition, $args, $orderBy, $retObj);
    }

    /**
     * mysql
     * 查找多条数据
     * @param string $table
     * @param string $field
     * @param array|string $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param string $orderBy
     * @param array $args [':var' => $var]
     * @param string $index
     * @param bool $retObj
     * @return array|bool
     */
    public static function findAll(string $table, string $field = '*', array|string $condition = '', string $orderBy = '', array $args = [], string $index = '', bool $retObj = false): bool|array
    {
        return self::Using()->findAll($table, $field, $condition, $args, $orderBy, $index, $retObj);
    }

    /**
     * mysql
     * 带分页数据的DB::page
     * @param string $table
     * @param string $field
     * @param array|string $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param string $orderBy
     * @param array $args [':var' => $var]
     * @param array|int $pageParam
     * @param int $limit
     * @param string $index
     * @param bool $retObj
     * @return array|bool
     */
    public static function page(string $table, string $field, array|string $condition = '', string $orderBy = '', array $args = [], array|int $pageParam = [], int $limit = 20, string $index = '', bool $retObj = false): bool|array
    {
        if (is_array($pageParam)) {
            $_pageParam = [
                'page' => 1,
                'maxPages' => 0,
                'showPage' => 10,
                'showNum' => false,
                'length' => $limit,
            ];

            if (!isset($pageParam['total'])) {
                $_pageParam['total'] = self::count($table, $condition, $args);
            }

            $pageParam = array_merge($_pageParam, $pageParam); //覆盖Pagebar参数
            $realPages = ceil($pageParam['total'] / $pageParam['length']);

            //共有多少页
            $pageParam['pages'] = $pageParam['maxPages'] ? min($realPages, $pageParam['maxPages']) : $realPages;
            //当前页
            $pageParam['page'] = $pageParam['maxPages'] ? max(1, min($pageParam['page'], $realPages, $pageParam['maxPages'])) : max(1, min($pageParam['page'], $realPages));

            $offset = ($pageParam['page'] - 1) * $pageParam['length'];
            $data = self::Using()->page($table, $field, $condition, $args, $orderBy, $offset, $limit, $index, $retObj);
            return ['total' => $pageParam['total'], 'rows' => $data, 'pagebar' => !empty($data) ? Helper\Pager::pageBar($pageParam) : ''];
        }

        $offset = $pageParam;
        return self::Using()->page($table, $field, $condition, $args, $orderBy, $offset, $limit, $index, $retObj);
    }

    /**
     * mysql postgres
     * 返回一条数据的第一栏
     * $filed mix  需要返回的字段  或者sql语法
     *
     * @param string $table
     * @param string $field
     * @param array|string $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param string $orderBy
     * @param array $args [':var' => $var]
     * @return mixed
     */
    public static function first(string $table, string $field, array|string $condition, string $orderBy = '', array $args = []): mixed
    {
        return self::Using()->first($table, $field, $condition, $args, $orderBy);
    }

    /**
     * mysql postgres
     * 单表符合条件的数量
     * @param string $table
     * @param array|string $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array $args [':var' => $var]
     * @param string $field
     * @return mixed
     */
    public static function count(string $table, array|string $condition, array $args = [], string $field = '*'): mixed
    {
        return self::Using()->count($table, $condition, $args, $field);
    }

    /**
     * mysql postgres
     * @param string $table
     * @param string $field
     * @param array|string $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param string $orderBy
     * @param array $args [':var' => $var]
     * @return array|bool
     */
    public static function col(string $table, string $field, array|string $condition = '', string $orderBy = '', array $args = []): bool|array
    {
        return self::Using()->col($table, $field, $condition, $args, $orderBy);
    }

    /**
     * mysql postgres
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array $args [':var' => $var]
     * @return bool|int
     */
    public static function exec(string $sql, array $args = []): bool|int
    {
        return self::Using()->exec($sql, $args);
    }

    /**
     * mysql postges
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array $args [':var' => $var]
     * @param bool $retObj
     * @return mixed
     */
    public static function rowSql(string $sql, array $args = [], bool $retObj = false): mixed
    {
        return self::Using()->rowSql($sql, $args, $retObj);
    }

    /**
     * mysql postgres
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array $args [':var' => $var]
     * @param string $index
     * @param bool $retObj
     * @return array|bool
     */
    public static function rowSetSql(string $sql, array $args = [], string $index = '', bool $retObj = false): bool|array
    {
        return self::Using()->rowSetSql($sql, $args, $index, $retObj);
    }

    /**
     * mysql postgres
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array $args [':var' => $var]
     * @param int|array $pageParam
     * @param int $limit
     * @param bool $retObj
     * @return array|bool
     */
    public static function pageSql(string $sql, array $args = [], int|array $pageParam = 0, int $limit = 18, string $index = '', bool $retObj = false): bool|array
    {
        if (is_array($pageParam)) {
            $_pageParam = [
                'page' => 1,
                'maxPages' => 0,
                'showPage' => 10,
                'showNum' => false,
                'length' => $limit,
            ];
            $pageParam = array_merge($_pageParam, $pageParam);
            $offset = self::pageStart($pageParam['page'], $limit, $pageParam['total']);
        } else {
            $offset = $pageParam;
        }
        $sql .= sprintf(' LIMIT %d OFFSET %d', $limit, $offset);
        $data = self::Using()->rowSetSql($sql, $args, $index, $retObj);
        if (is_array($pageParam)) {
            return ['total' => $pageParam['total'], 'rows' => $data, 'pagebar' => $data ? Helper\Pager::pageBar($pageParam) : ''];
        }
        return $data;
    }

    /**
     * mysql postgres
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array $args [':var' => $var]
     * @return mixed
     */
    public static function countSql(string $sql, array $args = []): mixed
    {
        return self::Using()->countSql($sql, $args);
    }

    /**
     * mysql postgres
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array $args [':var' => $var]
     * @return mixed
     */
    public static function firstSql(string $sql, array $args = []): mixed
    {
        return self::Using()->firstSql($sql, $args);
    }

    /**
     * mysql postgres
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array $args [':var' => $var]
     * @return array|bool
     */
    public static function colSql(string $sql, array $args = []): bool|array
    {
        return self::Using()->colSql($sql, $args);
    }

    /**
     * mysql postgres
     * 开始事务
     */
    public static function startTrans(): bool
    {
        return self::Using()->startTrans();
    }

    /**
     * mysql postgres
     * 事务提交或者回滚
     * @param bool $commit_no_errors
     */
    public static function endTrans(bool $commit_no_errors): void
    {
        self::Using()->endTrans($commit_no_errors);
    }

    private static function Using(): MysqlDb
    {
        return self::mysql(self::$using_dsn);
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
     * @param array $arr
     * @return string
     */
    public static function ids(array $arr): string
    {
        return implode(',', $arr);
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
