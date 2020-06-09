<?php

namespace Xcs\Database;

class Mysqli
{

    private $_config = null;
    private $_link = null;

    public function __destruct()
    {
        $this->close();
    }

    /**
     * Mysqli constructor.
     * @param $config
     * @param bool $repeat
     */
    public function __construct($config, $repeat = false)
    {
        if (is_null($this->_config)) {
            $this->_config = $config;
        }
        try {
            $this->_link = new \mysqli($config['host'], $config['login'], $config['secret'], $config['dbname'], $config['port']);
            $this->_link->set_charset('UTF8');
        } catch (\Exception $exception) {
            if ($repeat == false) {
                $this->__construct($config, true);
            } else {
                $this->close();
                $this->_halt($exception->getMessage(), $exception->getCode(), 'connect_error');
            }
        }
    }

    public function info()
    {
        return $this->_config;
    }

    public function close()
    {
        if ($this->_link) {
            $this->_link->close();
        }
        $this->_link = null;
    }

    /**
     * @param $func
     * @param $args
     * @return mixed
     */
    public function __call($func, $args)
    {
        return $this->_link && call_user_func_array([$this->_link, $func], $args);
    }

    /**
     * @param $tableName
     * @return string
     */
    public function qtable($tableName)
    {
        if (strpos($tableName, '.') === false) {
            return "`{$tableName}`";
        }
        $arr = explode('.', $tableName);
        if (count($arr) > 2) {
            $this->_halt("tableName:{$tableName} 最多只能有一个点.", 0, '');
        }
        return "`{$arr[0]}`.`{$arr[1]}`";
    }

    /**
     * @param $fieldName
     * @return string
     */
    public function qfield($fieldName)
    {
        $_fieldName = trim($fieldName);
        $ret = ($_fieldName == '*') ? '*' : "`{$_fieldName}`";
        return $ret;
    }

    /**
     * @param $value
     * @return string
     */
    public function qvalue($value)
    {
        if (gettype($value) === 'string') {
            return "'" . $this->_link->real_escape_string($value) . "'";
        }
        return $value;
    }

    /**
     * @param array $fields
     * @param string $glue
     * @return string
     */
    public function field_value(array $fields, $glue = ',')
    {
        $addsql = $comma = '';
        foreach ($fields as $field => $value) {
            $addsql .= $comma . $this->qfield($field) . " = " . $this->qvalue($value);
            $comma = $glue;
        }
        return $addsql;
    }

    /**
     * @param $tableName
     * @param array $data
     * @param bool $retid
     * @return mixed
     */
    public function create($tableName, array $data, $retid = false)
    {
        $fields = $values = $comma = '';
        foreach ($data as $field => $value) {
            $fields .= $comma . $this->qfield($field);
            $values .= $comma . $this->qvalue($value);
            $comma = ',';
        }
        try {
            $sql = 'INSERT INTO ' . $this->qtable($tableName) . '(' . $fields . ') VALUES (' . $values . ')';
            $ret = $this->_link->query($sql);
            if (!$ret) {
                throw new \Exception($this->_link->error, $this->_link->errno);
            }
            if ($retid) {
                $ret = $this->_link->insert_id;
            }
            return $ret;
        } catch (\Exception $e) {
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param $tableName
     * @param array $data
     * @param bool $retnum
     * @return mixed
     */
    public function replace($tableName, array $data, $retnum = false)
    {
        $fields = $values = $comma = '';
        foreach ($data as $field => $value) {
            $fields .= $comma . $this->qfield($field);
            $values .= $comma . $this->qvalue($value);
            $comma = ',';
        }
        $sql = 'REPLACE INTO ' . $this->qtable($tableName) . '(' . $fields . ') VALUES (' . $values . ')';
        try {
            $ret = $this->_link->query($sql);
            if (!$ret) {
                throw new \Exception($this->_link->error, $this->_link->errno);
            }
            if ($retnum) {
                $ret = $this->_link->affected_rows;
            }
            return $ret;
        } catch (\Exception $e) {
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param $tableName
     * @param array $data
     * @param $condition
     * @param bool $retNum
     * @return mixed
     */
    public function update($tableName, $data, $condition, $retNum = false)
    {
        if (is_array($condition)) {
            if (!is_array($data)) {
                $this->_halt('$data参数必须为数组', 0);
            }
            $_data = $this->field_value($data, ',');
            $_condition = $this->field_value($condition, ' AND ');
            $sql = 'UPDATE ' . $this->qtable($tableName) . " SET {$_data} WHERE {$_condition}";
        } else {
            if (is_array($data)) {
                $_data = $this->field_value($data);
            } else {
                $_data = $data;
            }
            $sql = 'UPDATE ' . $this->qtable($tableName) . " SET {$_data} WHERE {$condition}";
        }
        try {
            $ret = $this->_link->query($sql);
            if (!$ret) {
                throw new \Exception($this->_link->error, $this->_link->errno);
            }
            if ($retNum) {
                return $this->_link->affected_rows;
            }
            return $ret;
        } catch (\Exception $e) {
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param $tableName
     * @param $condition
     * @param bool $muti
     * @return mixed
     */
    public function remove($tableName, $condition, $muti = true)
    {
        if (empty($condition)) {
            return false;
        }
        if (is_array($condition)) {
            $_condition = $this->field_value($condition, ' AND ');
        } else {
            $_condition = $condition;
        }
        $limit = $muti ? '' : ' LIMIT 1';
        $sql = 'DELETE FROM ' . $this->qtable($tableName) . ' WHERE ' . $_condition . $limit;
        try {
            $ret = $this->_link->query($sql);
            if (!$ret) {
                throw new \Exception($this->_link->error, $this->_link->errno);
            }
            $ret = $this->_link->affected_rows;
            return $ret;
        } catch (\Exception $e) {
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param $tableName
     * @param $field
     * @param $condition
     * @param bool $retObj
     * @return array|bool|null|object|\stdClass
     */
    public function find_one($tableName, $field, $condition, $retObj = false)
    {
        if (is_array($condition)) {
            $_condition = $this->field_value($condition, ' AND ');
        } else {
            $_condition = $condition;
        }
        $sql = 'SELECT ' . $field . ' FROM ' . $this->qtable($tableName) . ' WHERE ' . $_condition . ' LIMIT 0,1';
        try {
            $sth = $this->_link->query($sql);
            if (!$sth) {
                throw new \Exception($this->_link->error, $this->_link->errno);
            }
            if ($retObj) {
                $data = $sth->fetch_object();
            } else {
                $data = $sth->fetch_assoc();
            }
            $sth->close();
            $sth = null;
            return $data;
        } catch (\Exception $e) {
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param $tableName
     * @param string $field
     * @param string $condition
     * @param null $index
     * @param bool $retObj
     * @return mixed
     */
    public function find_all($tableName, $field = '*', $condition = '', $index = null, $retObj = false)
    {
        if (is_array($condition) && !empty($condition)) {
            $_condition = $this->field_value($condition, ' AND ');
        } else {
            $_condition = $condition;
        }
        if ($_condition) {
            $sql = 'SELECT ' . $field . ' FROM ' . $this->qtable($tableName) . ' WHERE ' . $_condition;
        } else {
            $sql = 'SELECT ' . $field . ' FROM ' . $this->qtable($tableName);
        }
        try {
            $sth = $this->_link->query($sql, MYSQLI_STORE_RESULT);
            if (!$sth) {
                throw new \Exception($this->_link->error, $this->_link->errno);
            }
            $data = $sth->fetch_all(MYSQLI_ASSOC);
            if ($retObj) {
                $data = (array)$this->array_to_object($data);
            } elseif (!is_null($index)) {
                $data = $this->array_index($data, $index);
            }
            $sth->close();
            $sth = null;
            return $data;
        } catch (\Exception $e) {
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param $tableName
     * @param string $field
     * @param $condition
     * @param int $start
     * @param int $length
     * @param bool $retObj
     * @return mixed
     */
    private function _page($tableName, $field, $condition = '', $start = 0, $length = 20, $retObj = false)
    {
        try {
            if (is_array($condition) && !empty($condition)) {
                $_condition = $this->field_value($condition, ' AND ');
            } else {
                $_condition = $condition;
            }
            if ($_condition) {
                $sql = 'SELECT ' . $field . ' FROM ' . $this->qtable($tableName) . ' WHERE ' . $_condition . " LIMIT {$start},{$length}";
            } else {
                $sql = 'SELECT ' . $field . ' FROM ' . $this->qtable($tableName) . " LIMIT {$start},{$length}";
            }
            $sth = $this->_link->query($sql);
            if (!$sth) {
                throw new \Exception($this->_link->error, $this->_link->errno);
            }
            $data = $sth->fetch_all(MYSQLI_ASSOC);
            if ($retObj) {
                $data = (array)$this->array_to_object($data);
            }
            $sth->close();
            $sth = null;
            return $data;
        } catch (\Exception $e) {
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param $table
     * @param $field
     * @param $condition
     * @param int $pageParam
     * @param int $length
     * @param bool $retObj
     * @return mixed
     */
    public function page($table, $field, $condition, $pageParam = 0, $length = 18, $retObj = false)
    {
        if (is_array($pageParam)) {
            //固定长度分页模式
            $ret = ['rowsets' => [], 'pagebar' => ''];
            if ($pageParam['totals'] <= 0) {
                return $ret;
            }
            $start = $this->page_start($pageParam['curpage'], $length, $pageParam['totals']);
            $ret['rowsets'] = $this->_page($table, $field, $condition, $start, $length, $retObj);
            $ret['pagebar'] = \Xcs\DB::pageBar($pageParam, $length);
            return $ret;
        } else {
            //任意长度模式
            $start = $pageParam;
            return $this->_page($table, $field, $condition, $start, $length, $retObj);
        }
    }

    /**
     * @param string $tableName
     * @param string $field
     * @param mixed $condition
     * @return mixed
     */
    public function result_first($tableName, $field, $condition)
    {
        if (is_array($condition) && !empty($condition)) {
            $_condition = $this->field_value($condition, ' AND ');
        } else {
            $_condition = $condition;
        }
        $sql = "SELECT {$field} AS result FROM " . $this->qtable($tableName) . " WHERE  {$_condition} LIMIT 0,1";
        try {
            $sth = $this->_link->query($sql);
            if (!$sth) {
                throw new \Exception($this->_link->error, $this->_link->errno);
            }
            $data = $sth->fetch_assoc();
            $sth->close();
            $sth = null;
            return isset($data['result']) ? $data['result'] : null;
        } catch (\Exception $e) {
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }


    /**
     * @param $tableName
     * @param $field
     * @param $condition
     * @return mixed
     */
    public function col($tableName, $field, $condition = '')
    {
        if (is_array($condition) && !empty($condition)) {
            $_condition = $this->field_value($condition, ' AND ');
        } else {
            $_condition = $condition;
        }
        if ($_condition) {
            $sql = "SELECT {$field} FROM " . $this->qtable($tableName) . " WHERE  {$condition}";
        } else {
            $sql = "SELECT {$field} FROM " . $this->qtable($tableName);
        }
        try {
            $sth = $this->_link->query($sql, MYSQLI_STORE_RESULT);
            if (!$sth) {
                throw new \Exception($this->_link->error, $this->_link->errno);
            }
            $data = [];
            while ($col = $sth->fetch_row()) {
                $data[] = $col[0];
            }
            $sth->close();
            $sth = null;
            return $data;
        } catch (\Exception $e) {
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param $tableName
     * @param mixed $condition
     * @param string $field
     * @return mixed
     */
    public function count($tableName, $condition, $field = '*')
    {
        return $this->result_first($tableName, "COUNT({$field})", $condition);
    }

    /**
     * @param $sql
     * @param null $args
     * @return bool|int
     */
    public function exec($sql, $args = null)
    {
        try {
            $sth = $this->_link->query($sql);
            if (!$sth) {
                throw new \Exception($this->_link->error, $this->_link->errno);
            }
            $ret = $this->_link->affected_rows;
            return $ret;
        } catch (\Exception $e) {
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param $sql
     * @param $args
     * @param $retObj
     * @return mixed
     */
    public function row_sql($sql, $args = null, $retObj = false)
    {
        try {
            $sth = $this->_link->query($sql);
            if (!$sth) {
                throw new \Exception($this->_link->error, $this->_link->errno);
            }
            if ($retObj) {
                $data = $sth->fetch_object();
            } else {
                $data = $sth->fetch_assoc();
            }
            $sth->close();
            $sth = null;
            return $data;
        } catch (\Exception $e) {
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param $sql
     * @param $args
     * @param $index
     * @param $retObj
     * @return mixed
     */
    public function rowset_sql($sql, $args = null, $index = null, $retObj = false)
    {
        try {
            $sth = $this->_link->query($sql, MYSQLI_STORE_RESULT);
            if (!$sth) {
                throw new \Exception($this->_link->error, $this->_link->errno);
            }
            $data = $sth->fetch_all(MYSQLI_ASSOC);
            if ($retObj) {
                $data = (array)$this->array_to_object($data);
            } elseif (!is_null($index)) {
                $data = $this->array_index($data, $index);
            }
            $sth->close();
            $sth = null;
            return $data;
        } catch (\Exception $e) {
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param string $sql
     * @param array $args
     * @param bool $retObj
     * @return mixed
     */
    private function _page_sql($sql, $args = null, $retObj = false)
    {
        try {
            $sth = $this->_link->query($sql, MYSQLI_STORE_RESULT);
            if (!$sth) {
                throw new \Exception($this->_link->error, $this->_link->errno);
            }
            $data = $sth->fetch_all(MYSQLI_ASSOC);
            if ($retObj) {
                $data = (array)$this->array_to_object($data);
            }
            $sth->close();
            $sth = null;
            return $data;
        } catch (\Exception $e) {
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param string $sql
     * @param array $args
     * @param mixed $pageParam
     * @param int $length
     * @param bool $retObj
     * @return mixed
     */
    public function page_sql($sql, $args = null, $pageParam = 0, $length = 18, $retObj = false)
    {
        if (is_array($pageParam)) {
            //固定长度分页模式
            $ret = ['rowsets' => [], 'pagebar' => ''];
            if ($pageParam['totals'] <= 0) {
                return $ret;
            }
            $start = $this->page_start($pageParam['curpage'], $length, $pageParam['totals']);
            $ret['rowsets'] = $this->_page_sql($sql . " LIMIT {$start},{$length}", $args, $retObj);
            $ret['pagebar'] = \Xcs\DB::pageBar($pageParam, $length);
            return $ret;
        } else {
            //任意长度模式
            $start = $pageParam;
            return $this->_page_sql($sql . " LIMIT {$start},{$length}", $args, $retObj);
        }
    }

    /**
     * @param $sql
     * @param null $args
     * @return mixed
     */
    public function count_sql($sql, $args = null)
    {
        return $this->first_sql($sql, $args);
    }

    /**
     * @param string $sql
     * @param null $args
     * @return mixed
     */
    public function first_sql($sql, $args = null)
    {
        try {
            $sth = $this->_link->query($sql);
            if (!$sth) {
                throw new \Exception($this->_link->error, $this->_link->errno);
            }
            $data = $sth->fetch_row();
            $sth->close();
            $sth = null;
            return $data[0];
        } catch (\Exception $e) {
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param string $sql
     * @param null $args
     * @return mixed
     */
    public function col_sql($sql, $args = null)
    {
        try {
            $sth = $this->_link->query($sql, MYSQLI_STORE_RESULT);
            if (!$sth) {
                throw new \Exception($this->_link->error, $this->_link->errno);
            }
            $data = [];
            while ($col = $sth->fetch_row()) {
                $data[] = $col[0];
            }
            $sth->close();
            $sth = null;
            return $data;
        } catch (\Exception $e) {
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @return mixed
     */
    public function start_trans()
    {
        $this->_link->query('SET AUTOCOMMIT = 0');
        $this->_link->query('START TRANSACTION');
    }

    /**
     * @param bool $commit_no_errors
     */
    public function end_trans($commit_no_errors = true)
    {
        if ($commit_no_errors) {
            $this->_link->query('COMMIT');
            $this->_link->query('SET AUTOCOMMIT = 1');
        } else {
            $this->_link->query('ROLLBACK');
            $this->_link->query('SET AUTOCOMMIT = 1');
        }
    }

    /**
     * @param string $message
     * @param int $code
     * @param string $sql
     * @return bool
     */
    private function _halt($message = '', $code = 0, $sql = '')
    {
        if ($this->_config['rundev']) {
            $this->close();
            $encode = mb_detect_encoding($message, ['ASCII', 'UTF-8', 'GB2312', 'GBK', 'BIG5']);
            $message = mb_convert_encoding($message, 'UTF-8', $encode);
            new \Xcs\Exception\DbException($message . ' SQL: ' . $sql, intval($code), 'MysqliDbException');
        }
        return false;
    }

    /**
     * @param int $page
     * @param int $ppp
     * @param int $totalNum
     * @return int
     */
    private function page_start($page, $ppp, $totalNum)
    {
        $totalPage = ceil($totalNum / $ppp);
        $_page = max(1, min($totalPage, intval($page)));
        return ($_page - 1) * $ppp;
    }

    /**
     * @param $arr
     * @param $col
     * @return array
     */
    private function array_index($arr, $col)
    {
        if (!is_array($arr)) {
            return $arr;
        }
        $rows = [];
        foreach ($arr as $row) {
            $rows[$row[$col]] = $row;
        }
        return $rows;
    }

    /**
     * 数组 转 对象
     *
     * @param array $arr 数组
     * @return object|mixed
     */
    public function array_to_object($arr)
    {
        if (gettype($arr) != 'array') {
            return $arr;
        }
        foreach ($arr as $k => $v) {
            if (gettype($v) == 'array' || getType($v) == 'object') {
                $arr[$k] = $this->array_to_object($v);
            }
        }
        return (object)$arr;
    }
}