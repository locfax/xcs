<?php

namespace Xcs;

class DB
{

    private static $default_dbo_id = APPDSN;
    private static $using_dbo_id = null;
    private static $used_dbo = [];

    public static function info()
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->info();
    }

    /**
     * @param string $dsnId
     * @return mixed
     */
    public static function dbo($dsnId = 'portal')
    {
        $_dsn = Context::dsn($dsnId);
        $dsnKey = $_dsn['dsnkey']; //连接池key
        if (isset(self::$used_dbo[$dsnKey])) {
            return self::$used_dbo[$dsnKey];
        } elseif ('mongo' == $_dsn['driver']) {
            $dbo = new Database\Mongo($_dsn);
        } elseif ('mongodb' == $_dsn['driver']) {
            $dbo = new Database\MongoDb($_dsn);
        } elseif ('pdo' == $_dsn['driver']) {
            $dbo = new Database\Pdo($_dsn);
        } elseif ('mysqli' == $_dsn['driver']) {
            $dbo = new Database\Mysqli($_dsn);
        } else {
            $dbo = new Database\Pdo($_dsn);
        }
        self::$used_dbo[$dsnKey] = $dbo;
        return $dbo;
    }

    /**
     * @param string $dsnId
     * @return Database\Pdo
     */
    public static function dbm($dsnId = 'portal')
    {
        $_dsn = Context::dsn($dsnId);
        $dsnKey = $_dsn['dsnkey']; //连接池key
        if (isset(self::$used_dbo[$dsnKey])) {
            return self::$used_dbo[$dsnKey];
        } elseif ('pdo' == $_dsn['driver']) {
            $dbo = new Database\Pdo($_dsn);
        } elseif ('mysqli' == $_dsn['driver']) {
            $dbo = new Database\Mysqli($_dsn);
        } else {
            $dbo = new Database\Pdo($_dsn);
        }
        self::$used_dbo[$dsnKey] = $dbo;
        return $dbo;
    }

    public static function close()
    {
        $_dbo = self::$used_dbo;
        if (!empty($_dbo)) {
            foreach ($_dbo as $dbo) {
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
     * 插入一条数据
     * $option bool 是否返回插入的ID
     *
     * @param string $table
     * @param array $data
     * @param bool $option
     * @return bool/int
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
     * @return bool
     */
    public static function replace($table, $data)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->replace($table, $data);
    }

    /**
     * 更新符合条件的数据
     * @param mixed $option 是个多用途参数
     *  - mysql的情况: bool : true 返回影响数,如果是0表示无修改  false: 执行情况 返回 bool
     *
     * @param string $table
     * @param mixed $data (array string)
     * @param mixed $condition (array string)
     * @return bool/int
     */
    public static function update($table, $data, $condition, $option = false)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->update($table, $data, $condition, $option);
    }

    /**
     * 删除符合条件的项
     * @param mixed $muti
     *  - mysql的情况: bool true 删除多条 返回影响数 false: 只能删除一条
     *
     * @param string $table
     * @param mixed $condition
     * @return bool/int
     */
    public static function remove($table, $condition, $muti = true)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->remove($table, $condition, $muti);
    }

    /**
     * 查找一条数据
     * 如果要链表 使用 DB::row
     *
     * @param string $table
     * @param mixed $field
     * @param mixed $condition
     * @param bool $retObj
     * @return mixed
     */
    public static function findOne($table, $field, $condition, $retObj = false)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->find_one($table, $field, $condition, $retObj);
    }

    /**
     * 通用取多条数据的简洁方式 如果要链表 使用 DB::rowset
     *
     * @param string $table
     * @param string $field
     * @param string $condition
     * @param string $index
     * @param bool $retObj
     * @return mixed
     */
    public static function findAll($table, $field = '*', $condition = '', $index = null, $retObj = false)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->find_all($table, $field, $condition, $index, $retObj);
    }

    /**
     * 带分页数据的DB::page
     * @param string $table
     * @param $field
     * @param mixed $condition
     * @param int $length
     * @param int $pageParam
     * @param bool $retObj
     * @return array
     */
    public static function page($table, $field, $condition = '', $pageParam = 0, $length = 18, $retObj = false)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->page($table, $field, $condition, $pageParam, $length, $retObj);
    }

    /**
     * sql专用
     * 返回一条数据的第一栏
     * $filed mix  需要返回的字段  或者sql语法
     *
     * @param string $table
     * @param string $field
     * @param mixed $condition
     * @return mixed
     */
    public static function first($table, $field, $condition)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->result_first($table, $field, $condition);
    }

    /**
     * @param $table
     * @param $field
     * @param $condition
     * @return mixed
     */
    public static function col($table, $field, $condition = '')
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->col($table, $field, $condition);
    }

    /**
     * 单表符合条件的数量
     * - mysql:
     * $field count($field)
     *
     * @param string $table
     * @param mixed $condition
     * @param string $field
     * @return mixed
     */
    public static function count($table, $condition, $field = '1')
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->count($table, $condition, $field);
    }

    /**
     * @param $sql
     * @param $args
     * @return mixed
     */
    public static function exec($sql, $args = null)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->exec($sql, $args);
    }


    //--------------多表查询---start---------------//

    /**
     * @param $sql
     * @param $args
     * @param $retObj
     * @return mixed
     */
    public static function rowReq($sql, $args = null, $retObj = false)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->row_sql($sql, $args, $retObj);
    }

    /**
     * @param $sql
     * @param $args
     * @param null $index
     * @param bool $retObj
     * @return mixed
     */
    public static function rowSetReq($sql, $args = null, $index = null, $retObj = false)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->rowset_sql($sql, $args, $index, $retObj);
    }

    /**
     * @param string $sql
     * @param array $args
     * @param int $pageParam
     * @param int $length
     * @param bool $retObj
     * @return array
     */
    public static function pageReq($sql, $args = null, $pageParam = 0, $length = 18, $retObj = false)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->page_sql($sql, $args, $pageParam, $length, $retObj);
    }

    /**
     * @param $sql
     * @param null $args
     * @return mixed
     */
    public static function countReq($sql, $args = null)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->count_sql($sql, $args);
    }

    /**
     * @param $sql
     * @param null $args
     * @return mixed
     */
    public static function firstReq($sql, $args = null)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->first_sql($sql, $args);
    }

    /**
     * @param $sql
     * @param null $args
     * @return mixed
     */
    public static function colReq($sql, $args = null)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->col_sql($sql, $args);
    }

    //--------------多表查询---end---------------//

    /**
     * 开始事务
     * @return mixed
     */
    public static function startTrans()
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->start_trans();
    }

    /**
     * 事务提交或者回滚
     * @param bool $commit_no_errors
     * @return mixed
     */
    public static function endTrans($commit_no_errors = true)
    {
        $db = self::Using(self::$using_dbo_id);
        return $db->end_trans($commit_no_errors);
    }

    //----------------------事务END-------------------//

    /**
     * 切换数据源对象
     *
     * @param null $id
     * @return mixed
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
     * @param $length
     * @return array
     */
    public static function pageBar($pageParam, $length)
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
            $pageParam['length'] = $length;
            $pageBar = Helper\Pager::pageBar($pageParam);
        } elseif ('simplepage' == $pageParam['type']) {
            $defPageParam = [
                'curpage' => 1,
                'udi' => ''
            ];
            $pageParam = array_merge($defPageParam, $pageParam);
            $pageParam['length'] = $length;
            $pageBar = Helper\Pager::simplePage($pageParam);
        } else {
            $pages = ceil($pageParam['totals'] / $length);
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
}
