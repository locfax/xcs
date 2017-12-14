<?php

namespace Xcs;

class DB {

    private static $default_dbo_id = APPDSN;
    private static $using_dbo_id = null;
    private static $used_dbo = array();

    /**
     * @param string $dsnid
     * @return mixed
     */
    public static function dbo($dsnid = 'portal') {
        $_dsn = Context::dsn($dsnid);
        $dsnkey = $_dsn['dsnkey']; //连接池key
        if (isset(self::$used_dbo[$dsnkey])) {
            $dbo = self::$used_dbo[$dsnkey];
            if(is_null($dbo->_link)) {
                call_user_func(array($dbo, 'connect'), $_dsn);
            }
        } else {
            if ('mongo' == $_dsn['driver']) {
                $dbo = new Database\Mongo();
            } elseif ('mysql' == $_dsn['driver']) {
                $dbo = new Database\Pdo();
            } else {
                $dbo = new Database\Pdo(); //默认为Pdo
            }
            $dbo->connect($_dsn);
            self::$used_dbo[$dsnkey] = $dbo;
        }
        return $dbo;
    }

    /**
     * @param string $dsnid
     * @return Database\Pdo
     */
    public static function dbm($dsnid = 'portal') {
        $_dsn = Context::dsn($dsnid);
        $dsnkey = $_dsn['dsnkey']; //连接池key
        if (isset(self::$used_dbo[$dsnkey])) {
            $dbo = self::$used_dbo[$dsnkey];
            if(is_null($dbo->_link)) {
                call_user_func(array($dbo, 'connect'), $_dsn);
            }
        } else {
            $dbo = new Database\Pdo();
            $dbo->connect($_dsn);
            self::$used_dbo[$dsnkey] = $dbo;
        }
        return $dbo;
    }

    public static function close() {
        $dbos = self::$used_dbo;
        if (!empty($dbos)) {
            foreach ($dbos as $dbo) {
                $dbo->close();
            }
        }
    }

    /**
     * 还原默认数据源对象
     */
    public static function resume() {
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
    public static function create($table, $data, $option = false) {
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
    public static function replace($table, $data) {
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
    public static function update($table, $data, $condition, $option = false) {
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
    public static function remove($table, $condition, $muti = true) {
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
     * @param bool $retobj
     * @return mixed
     */
    public static function findOne($table, $field, $condition, $retobj = false) {
        $db = self::Using(self::$using_dbo_id);
        return $db->findOne($table, $field, $condition, $retobj);
    }

    /**
     * 通用取多条数据的简洁方式 如果要链表 使用 DB::rowset
     *
     * @param string $table
     * @param string $field
     * @param string $condition
     * @param string $index
     * @param bool $retobj
     * @return mixed
     */
    public static function findAll($table, $field = '*', $condition = '1', $index = null, $retobj = false) {
        $db = self::Using(self::$using_dbo_id);
        return $db->findAll($table, $field, $condition, $index, $retobj);
    }

    /**
     * 带分页数据的DB::page
     * @param string $table
     * @param $field
     * @param mixed $condition
     * @param int $length
     * @param int $pageparm
     * @param bool $retobj
     * @return array
     */
    public static function page($table, $field, $condition, $pageparm = 0, $length = 18, $retobj = false) {
        $db = self::Using(self::$using_dbo_id);
        return $db->page($table, $field, $condition, $pageparm, $length, $retobj);
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
    public static function count($table, $condition, $field = '*') {
        $db = self::Using(self::$using_dbo_id);
        return $db->count($table, $condition, $field);
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
    public static function first($table, $field, $condition) {
        $db = self::Using(self::$using_dbo_id);
        return $db->resultFirst($table, $field, $condition);
    }

    /**
     * @param $table
     * @param $field
     * @param $condition
     * @return mixed
     */
    public static function getCol($table, $field, $condition) {
        $db = self::Using(self::$using_dbo_id);
        return $db->getCol($table, $field, $condition);
    }

    //--------------多表联合查询---start---------------//

    /**
     * @param $sql
     * @param $args
     * @return mixed
     */
    public static function exec($sql, $args = null) {
        $db = self::Using(self::$using_dbo_id);
        return $db->exec($sql, $args);
    }

    /**
     * @param $query
     * @param $args
     * @param $retobj
     * @return mixed
     */
    public static function row($query, $args = null, $retobj = false) {
        $db = self::Using(self::$using_dbo_id);
        return $db->row($query, $args, $retobj);
    }

    /**
     * @param $query
     * @param $args
     * @param null $index
     * @param bool $retobj
     * @return mixed
     */
    public static function rowset($query, $args = null, $index = null, $retobj = false) {
        $db = self::Using(self::$using_dbo_id);
        return $db->rowset($query, $args, $index, $retobj);
    }

    /**
     * @param string $sql
     * @param array $args
     * @param int $pageparm
     * @param int $length
     * @param bool $retobj
     * @return array
     */
    public static function pages($sql, $args = null, $pageparm = 0, $length = 18, $retobj = false) {
        $db = self::Using(self::$using_dbo_id);
        return $db->pages($sql, $args, $pageparm, $length, $retobj);
    }

    /**
     * @param $sql
     * @param null $args
     * @return mixed
     */
    public static function counts($sql, $args = null) {
        $db = self::Using(self::$using_dbo_id);
        return $db->counts($sql, $args);
    }

    /**
     * @param $sql
     * @param null $args
     * @return mixed
     */
    public static function firsts($sql, $args = null) {
        $db = self::Using(self::$using_dbo_id);
        return $db->firsts($sql, $args);
    }

    /**
     * @param $sql
     * @param null $args
     * @return mixed
     */
    public static function getCols($sql, $args = null) {
        $db = self::Using(self::$using_dbo_id);
        return $db->getcols($sql, $args);
    }

    //--------------多表联合查询---end---------------//

    /**
     * 开始事务
     * @return mixed
     */
    public static function start_trans() {
        $db = self::Using(self::$using_dbo_id);
        return $db->start_trans();
    }

    /**
     * 事务提交或者回滚
     * @param bool $commit_no_errors
     * @return mixed
     */
    public static function end_trans($commit_no_errors = true) {
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
    public static function Using($id = null) {
        if (!$id) {
            //初始运行
            self::$using_dbo_id = self::$default_dbo_id;
        } else {
            //切换dbo id
            if ($id != self::$using_dbo_id) {
                self::$using_dbo_id = $id;
            }
        }
        return self::dbm(self::$using_dbo_id);
    }

    /**
     * @param int $page
     * @param int $ppp
     * @param int $totalnum
     * @return int
     */
    public static function page_start($page, $ppp, $totalnum) {
        $totalpage = ceil($totalnum / $ppp);
        $_page = max(1, min($totalpage, intval($page)));
        return ($_page - 1) * $ppp;
    }

    /**
     * @param $pageparm
     * @param $length
     * @return array
     */
    public static function pagebar($pageparm, $length) {
        if (!isset($pageparm['type']) || 'pagebar' == $pageparm['type']) {
            $defpageparm = array(
                'curpage' => 1,
                'maxpages' => 0,
                'showpage' => 10,
                'udi' => '',
                'shownum' => false,
                'showkbd' => false,
                'simple' => false
            );
            $pageparm = array_merge($defpageparm, $pageparm);
            $pageparm['length'] = $length;
            $pagebar = Helper\Pager::pagebar($pageparm);
        } elseif ('simplepage' == $pageparm['type']) {
            $defpageparm = array(
                'curpage' => 1,
                'udi' => ''
            );
            $pageparm = array_merge($defpageparm, $pageparm);
            $pageparm['length'] = $length;
            $pagebar = Helper\Pager::simplepage($pageparm);
        } else {
            $pages = ceil($pageparm['totals'] / $length);
            $nextpage = ($pages > $pageparm['curpage']) ? $pageparm['curpage'] + 1 : $pages;
            $pagebar = array(
                'totals' => $pageparm['totals'],
                'pagecount' => $pages,
                'prepage' => $pageparm['curpage'] - 1 > 0 ? $pageparm['curpage'] - 1 : 1,
                'curpage' => $pageparm['curpage'],
                'nextpage' => $nextpage
            );
        }
        return $pagebar;
    }
}
