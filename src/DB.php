<?php

namespace Xcs;

use Xcs\Db\MongoDb;
use Xcs\Db\PdoDb;

class DB
{

    private static $default_dbo_id = APP_DSN;
    private static $using_dbo_id = null;
    private static $used_dbo = [];
    private static $dbm_time_out = 0;
    private static $mgo_time_out = 0

    /**
     * 返回 PDO 对象
     * 通常用 DB::find*  DB::row* DB::page* ...
     * 只有在切换不同数据库可能会用到
     * @param string $dsnId
     * @return PdoDb
     * @see PdoDb
     */
    public static function dbm($dsnId = 'default')
    {
        $dsn = Context::dsn($dsnId);
        if (isset(self::$used_dbo[$dsnId] && self::$dbm_time_out > time())) {
            return self::$used_dbo[$dsnId];
        }

        if ('PdoDb' != $dsn['driver']) {
            new ExException("the driver error: PdoDb");
            return null;
        }

        self::$dbm_time_out = time() + 60; //60秒超时控制
        $object = new PdoDb(['dsn' => $dsn]);
        self::$used_dbo[$dsnId] = $object;
        return $object;
    }

    /**
     * 返回 mongodb 对象
     * @param string $dsnId
     * @return MongoDb
     * @see MongoDb
     */
    public static function mgo($dsnId = 'default')
    {
        $dsn = Context::dsn($dsnId);
        if (isset(self::$used_dbo[$dsnId]) && self::$mgo_time_out > time()) {
            return self::$used_dbo[$dsnId];
        }

        if ('MongoDb' != $dsn['driver']) {
            new ExException("the driver error: MongoDb");
            return null;
        }

        self::$mgo_time_out = time() + 60
        $object = new MongoDb(['dsn' => $dsn]);
        self::$used_dbo[$dsnId] = $object;
        return $object;
    }

    /**
     * 关闭数据库  通常不用调用
     */
    public static function close()
    {
        if (!empty(self::$used_dbo)) {
            foreach (self::$used_dbo as $dbo) {
                $dbo->close();
            }
        }
    }

    /**
     * mysql专用
     * 还原默认数据源对象
     */
    public static function resume()
    {
        self::$using_dbo_id = self::$default_dbo_id;
    }

    /**
     * mysql专用
     * @return array|mixed
     */
    public static function info()
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
     * @return bool|int
     */
    public static function create($table, array $data, $retId = false)
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
     * @return bool|int|null
     */
    public static function replace($table, array $data)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->replace($table, $data);
    }

    /**
     * mysql专用
     * 更新符合条件的数据
     * @param string $table
     * @param string|array $data
     * @param string|array $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array $args [':var' => $var]
     * @return bool|int
     */
    public static function update($table, $data, $condition, array $args = null)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->update($table, $data, $condition, $args);
    }

    /**
     * mysql专用
     * 删除符合条件的项
     * @param string $table
     * @param string|array $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array $args [':var' => $var]
     * @param mixed $multi bool true 删除多条 返回影响数 false: 只能删除一条
     * @return bool|int
     */
    public static function remove($table, $condition, $multi = false, array $args = null)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->remove($table, $condition, $args, $multi);
    }

    /**
     * mysql专用
     * 查找一条数据
     * @param string $table
     * @param string $field
     * @param string|array $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param null $orderBy
     * @param array $args [':var' => $var]
     * @param bool $retObj
     * @return mixed
     */
    public static function findOne($table, $field, $condition, $orderBy = null, array $args = null, $retObj = false)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->findOne($table, $field, $condition, $args, $orderBy, $retObj);
    }

    /**
     * mysql专用
     * 查找多条数据
     * @param string $table
     * @param string $field
     * @param string|array $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param null $orderBy
     * @param array $args [':var' => $var]
     * @param string $index
     * @param bool $retObj
     * @return mixed
     */
    public static function findAll($table, $field = '*', $condition = '', $orderBy = null, array $args = null, $index = null, $retObj = false)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->findAll($table, $field, $condition, $args, $orderBy, $index, $retObj);
    }

    /**
     * mysql专用
     * 带分页数据的DB::page
     * @param string $table
     * @param $field
     * @param string|array $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param null $orderBy
     * @param array $args [':var' => $var]
     * @param array|int $pageParam
     * @param int $limit
     * @param bool $retObj
     * @return array
     */
    public static function page($table, $field, $condition = '', $orderBy = null, array $args = null, $pageParam = 0, $limit = 20, $retObj = false)
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
     * @param string|array $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param null $orderBy
     * @param array $args [':var' => $var]
     * @return mixed
     */
    public static function first($table, $field, $condition, $orderBy = null, array $args = null)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->first($table, $field, $condition, $args, $orderBy);
    }

    /**
     * mysql专用
     * @param $table
     * @param $field
     * @param string|array $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param null $orderBy
     * @param array $args [':var' => $var]
     * @return mixed
     */
    public static function col($table, $field, $condition = '', $orderBy = null, array $args = null)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->col($table, $field, $condition, $args, $orderBy);
    }

    /**
     * mysql专用
     * 单表符合条件的数量
     * @param string $table
     * @param string|array $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array $args [':var' => $var]
     * @param string $field
     * @return mixed
     */
    public static function count($table, $condition, array $args = null, $field = '*')
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->count($table, $condition, $args, $field);
    }

    /**
     * mysql专用
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array $args [':var' => $var]
     * @return mixed
     */
    public static function exec($sql, array $args = null)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->exec($sql, $args);
    }


    //--------------sql查询---start---------------//

    /**
     * mysql专用
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array $args [':var' => $var]
     * @param bool $retObj
     * @return mixed
     */
    public static function rowSql($sql, array $args = null, $retObj = false)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->rowSql($sql, $args, $retObj);
    }

    /**
     * mysql专用
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array $args [':var' => $var]
     * @param null $index
     * @param bool $retObj
     * @return mixed
     */
    public static function rowSetSql($sql, array $args = null, $index = null, $retObj = false)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->rowSetSql($sql, $args, $index, $retObj);
    }

    /**
     * mysql专用
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array $args [':var' => $var]
     * @param int $pageParam
     * @param int $limit
     * @param bool $retObj
     * @return array
     */
    public static function pageSql($sql, array $args = null, $pageParam = 0, $limit = 18, $retObj = false)
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
     * @param array $args [':var' => $var]
     * @return mixed
     */
    public static function countSql($sql, array $args = null)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->countSql($sql, $args);
    }

    /**
     * mysql专用
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array $args [':var' => $var]
     * @return mixed
     */
    public static function firstSql($sql, array $args = null)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->firstSql($sql, $args);
    }

    /**
     * mysql专用
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array $args [':var' => $var]
     * @return mixed
     */
    public static function colSql($sql, array $args = null)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->colSql($sql, $args);
    }

    //--------------多表查询---end---------------//

    /**
     * mysql专用
     * 开始事务
     * @return mixed
     */
    public static function startTrans()
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->startTrans();
    }

    /**
     * mysql专用
     * 事务提交或者回滚
     * @param bool $commit_no_errors
     * @return mixed
     */
    public static function endTrans($commit_no_errors = true)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->endTrans($commit_no_errors);
    }

    //----------------------事务END-------------------//

    /**
     * mysql专用
     * 切换数据源对象
     * @param null $id
     * @return PdoDb
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
        return self::dbm(self::$using_dbo_id);
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
     * @return array
     */
    public static function pageBar($pageParam, $length)
    {
        if (!isset($pageParam['bar']) || 'default' == $pageParam['bar']) {
            $defPageParam = [
                'page' => 1,
                'udi' => '',
                'maxpages' => 100,
                'showpage' => 10,
                'shownum' => false,
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
