<?php

namespace Xcs;

use Xcs\Db\PdoDb;
use Xcs\Db\Mongo;
use Xcs\Db\MongoDb;

class DB
{

    private static $default_dbo_id = APP_DSN;
    private static $using_dbo_id = null;
    private static $used_dbo = [];

    /**
     * @param string $dsnId
     * @return MongoDb|PdoDb
     */
    public static function dbo($dsnId = 'default')
    {
        $dsn = Context::dsn($dsnId);
        if (isset(self::$used_dbo[$dsnId])) {
            return self::$used_dbo[$dsnId];
        }

        if (!in_array($dsn['driver'], ['PdoDb', 'MongoDb'])) {
            new ExException("the driver error, PdoDb|Mongo|MongoDb");
            return null;
        }

        $driver = '\\Xcs\\Db\\' . $dsn['driver'];
        $dbo = new $driver(['dsn' => $dsn]);

        self::$used_dbo[$dsnId] = $dbo;
        return $dbo;
    }

    /**
     * @param string $dsnId
     * @return PdoDb
     */
    public static function dbm($dsnId = 'default')
    {
        $dsn = Context::dsn($dsnId);
        if (isset(self::$used_dbo[$dsnId])) {
            return self::$used_dbo[$dsnId];
        }

        if ('PdoDb' != $dsn['driver']) {
            new ExException("the driver error: PdoDb");
            return null;
        }

        $dbo = new PdoDb(['dsn' => $dsn]);
        self::$used_dbo[$dsnId] = $dbo;
        return $dbo;
    }

    public static function close()
    {
        if (!empty(self::$used_dbo)) {
            foreach (self::$used_dbo as $dbo) {
                $dbo->close();
            }
        }
    }

    /**
     * 还原默认数据源对象
     */
    public static function resume()
    {
        self::$using_dbo_id = self::$default_dbo_id;
    }

    /**
     * @return array|mixed
     */
    public static function info()
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->info();
    }

    /**
     * 插入一条数据
     * $option bool 是否返回插入的ID
     *
     * @param string $table
     * @param array $data
     * @param bool $option
     * @return bool|int
     */
    public static function create($table, $data, $option = false)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->create($table, $data, $option);
    }

    /**
     * 替换一条数据
     * PS:需要设置主键值
     *
     * @param string $table
     * @param array $data
     * @return bool|int|null
     */
    public static function replace($table, $data)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->replace($table, $data);
    }

    /**
     * 更新符合条件的数据
     * @param string $table
     * @param mixed $data (array string)
     * @param string|array $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array $args [':var' => $var]
     * @return bool/int
     */
    public static function update($table, $data, $condition, $args = null)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->update($table, $data, $condition, $args);
    }

    /**
     * 删除符合条件的项
     * @param string $table
     * @param string|array $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array $args [':var' => $var]
     * @param mixed $multi
     *  - mysql的情况: bool true 删除多条 返回影响数 false: 只能删除一条
     * @return bool/int
     */
    public static function remove($table, $condition, $args = null, $multi = false)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->remove($table, $condition, $args, $multi);
    }

    /**
     * 查找一条数据
     * 如果要链表 使用 DB::row
     *
     * @param string $table
     * @param mixed $field
     * @param string|array $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array $args [':var' => $var]
     * @param bool $retObj
     * @return mixed
     */
    public static function findOne($table, $field, $condition, $args = null, $retObj = false)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->findOne($table, $field, $condition, $args, $retObj);
    }

    /**
     * 通用取多条数据的简洁方式 如果要链表 使用 DB::rowset
     *
     * @param string $table
     * @param string $field
     * @param string|array $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array $args [':var' => $var]
     * @param string $index
     * @param bool $retObj
     * @return mixed
     */
    public static function findAll($table, $field = '*', $condition = '', $args = null, $index = null, $retObj = false)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->findAll($table, $field, $condition, $args, $index, $retObj);
    }

    /**
     * 带分页数据的DB::page
     * @param string $table
     * @param $field
     * @param string|array $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array $args [':var' => $var]
     * @param int $pageParam
     * @param int $limit
     * @param bool $retObj
     * @return array
     */
    public static function page($table, $field, $condition = '', $args = null, $pageParam = 0, $limit = 18, $retObj = false)
    {
        $db = self::Using(self::$using_dbo_id);
        $data = $db->page($table, $field, $condition, $args, $pageParam, $limit, $retObj);
        if (is_array($pageParam)) {
            return ['rowsets' => $data, 'pagebar' => $data ? self::pageBar($pageParam, $limit) : ''];
        }
        return $data;
    }

    /**
     * sql专用
     * 返回一条数据的第一栏
     * $filed mix  需要返回的字段  或者sql语法
     *
     * @param string $table
     * @param string $field
     * @param string|array $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array $args [':var' => $var]
     * @return mixed
     */
    public static function first($table, $field, $condition, $args = null)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->first($table, $field, $condition, $args);
    }

    /**
     * @param $table
     * @param $field
     * @param string|array $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array $args [':var' => $var]
     * @return mixed
     */
    public static function col($table, $field, $condition = '', $args = null)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->col($table, $field, $condition, $args);
    }

    /**
     * 单表符合条件的数量
     * - mysql:
     * $field count($field)
     *
     * @param string $table
     * @param string|array $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array $args [':var' => $var]
     * @param string $field
     * @return mixed
     */
    public static function count($table, $condition, $args = null, $field = '*')
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->count($table, $condition, $args, $field);
    }

    /**
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array $args [':var' => $var]
     * @return mixed
     */
    public static function exec($sql, $args = null)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->exec($sql, $args);
    }


    //--------------多表查询---start---------------//

    /**
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array $args [':var' => $var]
     * @param bool $retObj
     * @return mixed
     */
    public static function rowSql($sql, $args = null, $retObj = false)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->rowSql($sql, $args, $retObj);
    }

    /**
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array $args [':var' => $var]
     * @param null $index
     * @param bool $retObj
     * @return mixed
     */
    public static function rowSetSql($sql, $args = null, $index = null, $retObj = false)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->rowSetSql($sql, $args, $index, $retObj);
    }

    /**
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array $args [':var' => $var]
     * @param int $pageParam
     * @param int $limit
     * @param bool $retObj
     * @return array
     */
    public static function pageSql($sql, $args = null, $pageParam = 0, $limit = 18, $retObj = false)
    {
        $db = self::Using(self::$using_dbo_id);
        $data = $db->pageSql($sql, $args, $pageParam, $limit, $retObj);
        if (is_array($pageParam)) {
            return ['rowsets' => $data, 'pagebar' => $data ? self::pageBar($pageParam, $limit) : ''];
        }
        return $data;
    }

    /**
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array $args [':var' => $var]
     * @return mixed
     */
    public static function countSql($sql, $args = null)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->countSql($sql, $args);
    }

    /**
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array $args [':var' => $var]
     * @return mixed
     */
    public static function firstSql($sql, $args = null)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->firstSql($sql, $args);
    }

    /**
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array $args [':var' => $var]
     * @return mixed
     */
    public static function colSql($sql, $args = null)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->colSql($sql, $args);
    }

    //--------------多表查询---end---------------//

    /**
     * 开始事务
     * @return mixed
     */
    public static function startTrans()
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->startTrans();
    }

    /**
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
     * 切换数据源对象
     * @param null $id
     * @return PdoDb|Mongo|MongoDb
     */
    public static function Using($id = null)
    {
        if (!$id) {
            //初始运行
            self::$using_dbo_id = self::$default_dbo_id;
        } else {
            //切换dbo id
            if ($id != self::$using_dbo_id) {
                self::$using_dbo_id = $id;
            }
        }
        return self::dbo(self::$using_dbo_id);
    }

    /**
     * @param int $page
     * @param int $ppp
     * @param int $totalNum
     * @return int
     */
    public static function pageStart($page, $ppp, $totalNum)
    {
        $totalPage = ceil($totalNum / $ppp);
        $_page = max(1, min($totalPage, intval($page)));
        return ($_page - 1) * $ppp;
    }

    /**
     * @param $pageParam
     * @param $limit
     * @return array
     */
    public static function pageBar($pageParam, $limit)
    {
        if (!isset($pageParam['type']) || 'pagebar' == $pageParam['type']) {
            $defPageParam = [
                'curpage' => 1,
                'maxpages' => 0,
                'showpage' => 10,
                'udi' => '',
                'shownum' => false,
                'showkbd' => false,
                'simple' => false
            ];
            $pageParam = array_merge($defPageParam, $pageParam);
            $pageParam['length'] = $limit;
            $pageBar = Helper\Pager::pageBar($pageParam);
        } elseif ('simplepage' == $pageParam['type']) {
            $defPageParam = [
                'curpage' => 1,
                'udi' => ''
            ];
            $pageParam = array_merge($defPageParam, $pageParam);
            $pageParam['length'] = $limit;
            $pageBar = Helper\Pager::simplePage($pageParam);
        } else {
            $pages = ceil($pageParam['totals'] / $limit);
            $nextPage = ($pages > $pageParam['curpage']) ? $pageParam['curpage'] + 1 : $pages;
            $pageBar = [
                'totals' => $pageParam['totals'],
                'pagecount' => $pages,
                'prepage' => $pageParam['curpage'] - 1 > 0 ? $pageParam['curpage'] - 1 : 1,
                'curpage' => $pageParam['curpage'],
                'nextpage' => $nextPage
            ];
        }
        return $pageBar;
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
