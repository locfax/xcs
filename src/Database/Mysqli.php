<?php

namespace Xcs\Database;

class Mysqli {

    private $_config = null;
    public $_link = null;

    public function __destruct() {
        $this->close();
    }

    /**
     * @param $config
     */
    public function __construct($config) {
        if (is_null($this->_config)) {
            $this->_config = $config;
        }
        try {
            $this->_link = new \mysqli($config['host'], $config['login'], $config['secret'], $config['dbname'], $config['port']);
            $this->_link->set_charset('UTF8');
        } catch (\Exception $exception) {
            $this->_halt($exception->getMessage(), $exception->getCode(), 'connect_error');
        }
    }

    public function reconnect() {
        $this->__construct($this->_config);
    }

    public function info() {
        return $this->_config;
    }

    public function close() {
        if ($this->_link) {
            $this->_link->close();
            $this->_link = null;
        }
    }

    /**
     * @param $func
     * @param $args
     * @return mixed
     */
    public function __call($func, $args) {
        return $this->_link && call_user_func_array(array($this->_link, $func), $args);
    }

    /**
     * @param $tableName
     * @return string
     */
    public function qtable($tableName) {
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
    public function qfield($fieldName) {
        $_fieldName = trim($fieldName);
        $ret = ($_fieldName == '*') ? '*' : "`{$_fieldName}`";
        return $ret;
    }

    /**
     * @param $value
     * @return string
     */
    public function qvalue($value) {
        if (is_numeric($value)) {
            return $value;
        }
        return "'" . $this->_link->real_escape_string($value) . "'";
    }

    /**
     * @param array $fields
     * @param string $glue
     * @return string
     */
    public function field_value(array $fields, $glue = ',') {
        $addsql = $comma = '';
        foreach ($fields as $field => $value) {
            $addsql .= $comma . $this->qfield($field) . "=" . $this->qvalue($value);
            $comma = $glue;
        }
        return $addsql;
    }

    /**
     * @param $tableName
     * @param array $data
     * @param bool $retid
     * @param string $type
     * @return mixed
     */
    public function create($tableName, array $data, $retid = false, $type = '') {
        if (empty($data)) {
            return false;
        }
        $args = array();
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
            if ('RETRY' != $type) {
                $this->reconnect();
                return $this->create($tableName, $data, $retid, 'RETRY');
            }
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param $tableName
     * @param array $data
     * @param bool $retnum
     * @param string $type
     * @return mixed
     */
    public function replace($tableName, array $data, $retnum = false, $type = '') {
        if (empty($data)) {
            return false;
        }
        $args = array();
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
            if ('RETRY' != $type) {
                $this->reconnect();
                return $this->replace($tableName, $data, $retnum, 'RETRY');
            }
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param $tableName
     * @param array $data
     * @param $condition
     * @param bool $retnum
     * @param string $type
     * @return mixed
     */
    public function update($tableName, $data, $condition, $retnum = false, $type = '') {
        if (empty($data)) {
            return false;
        }
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
            $data = $this->_link->query($sql);
            if (!$data) {
                throw new \Exception($this->_link->error, $this->_link->errno);
            }
            if ($retnum) {
                return $this->_link->affected_rows;
            }
            return $data;
        } catch (\Exception $e) {
            if ('RETRY' != $type) {
                $this->reconnect();
                return $this->update($tableName, $data, $condition, $retnum, 'RETRY');
            }
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param $tableName
     * @param $condition
     * @param bool $muti
     * @param string $type
     * @return mixed
     */
    public function remove($tableName, $condition, $muti = true, $type = '') {
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
            $data = $this->_link->query($sql);
            if (!$data) {
                throw new \Exception($this->_link->error, $this->_link->errno);
            }
            $data = $this->_link->affected_rows;
            return $data;
        } catch (\Exception $e) {
            if ('RETRY' != $type) {
                $this->reconnect();
                return $this->remove($tableName, $condition, $muti, 'RETRY');
            }
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param $tableName
     * @param string $field
     * @param $condition
     * @param $retobj
     * @param $type
     * @return mixed
     */
    public function findOne($tableName, $field, $condition, $retobj = false, $type = '') {
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
            if ($retobj) {
                $data = $sth->fetch_object();
            } else {
                $data = $sth->fetch_assoc();
            }
            $sth->close();
            return $data;
        } catch (\Exception $e) {
            if ('RETRY' != $type) {
                $this->reconnect();
                return $this->findOne($tableName, $field, $condition, $retobj, 'RETRY');
            }
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param $tableName
     * @param string $field
     * @param string $condition
     * @param null $index
     * @param bool $retobj
     * @param string $type
     * @return mixed
     */
    public function findAll($tableName, $field = '*', $condition = '', $index = null, $retobj = false, $type = '') {
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
            if ($retobj) {
                $data = (array)$this->array_to_object($data);
            } elseif (!is_null($index)) {
                $data = $this->array_index($data, $index);
            }
            $sth->close();
            return $data;
        } catch (\Exception $e) {
            if ('RETRY' != $type) {
                $this->reconnect();
                return $this->findAll($tableName, $field, $condition, $index, $retobj, 'RETRY');
            }
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param $tableName
     * @param string $field
     * @param $condition
     * @param int $start
     * @param int $length
     * @param bool $retobj
     * @param string $type
     * @return mixed
     */
    private function _page($tableName, $field, $condition = '', $start = 0, $length = 20, $retobj = false, $type = '') {
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
            if ($retobj) {
                $data = (array)$this->array_to_object($data);
            }
            $sth->close();
            return $data;
        } catch (\Exception $e) {
            if ('RETRY' != $type) {
                $this->reconnect();
                return $this->_page($tableName, $field, $condition, $start, $length, $retobj, 'RETRY');
            }
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param $table
     * @param $field
     * @param $condition
     * @param int $pageparm
     * @param int $length
     * @param bool $retobj
     * @return mixed
     */
    public function page($table, $field, $condition, $pageparm = 0, $length = 18, $retobj = false) {
        if (is_array($pageparm)) {
            //固定长度分页模式
            $ret = array('rowsets' => array(), 'pagebar' => '');
            if ($pageparm['totals'] <= 0) {
                return $ret;
            }
            $start = $this->page_start($pageparm['curpage'], $length, $pageparm['totals']);
            $ret['rowsets'] = $this->_page($table, $field, $condition, $start, $length, $retobj);;
            $ret['pagebar'] = \Xcs\DB::pagebar($pageparm, $length);
            return $ret;
        } else {
            //任意长度模式
            $start = $pageparm;
            return $this->_page($table, $field, $condition, $start, $length, $retobj);
        }
    }

    /**
     * @param string $tableName
     * @param string $field
     * @param mixed $condition
     * @param string $type
     * @return mixed
     */
    public function resultFirst($tableName, $field, $condition, $type = '') {
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
            return isset($data['result']) ? $data['result'] : null;
        } catch (\Exception $e) {
            if ('RETRY' != $type) {
                $this->reconnect();
                return $this->resultFirst($tableName, $field, $condition, 'RETRY');
            }
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }


    /**
     * @param $tableName
     * @param $field
     * @param $condition
     * @param $type
     * @return mixed
     */
    public function getCol($tableName, $field, $condition = '', $type = '') {
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
            $data = array();
            while ($col = $sth->fetch_row()) {
                $data[] = $col[0];
            }
            $sth->close();
            return $data;
        } catch (\Exception $e) {
            if ('RETRY' != $type) {
                $this->reconnect();
                return $this->getCol($tableName, $field, $condition, 'RETRY');
            }
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param string $sql
     * @param $args
     * @param string $type
     * @return mixed
     */
    public function exec($sql, $args = null, $type = '') {
        try {
            $sth = $this->_link->query($sql);
            if (!$sth) {
                throw new \Exception($this->_link->error, $this->_link->errno);
            }
            $ret = $this->_link->affected_rows;
            return $ret;
        } catch (\Exception $e) {
            if ('RETRY' != $type) {
                $this->reconnect();
                return $this->exec($sql, $args, 'RETRY');
            }
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param $sql
     * @param $args
     * @param $retobj
     * @param $type
     * @return mixed
     */
    public function row($sql, $args = null, $retobj = false, $type = '') {
        try {
            $sth = $this->_link->query($sql);
            if (!$sth) {
                throw new \Exception($this->_link->error, $this->_link->errno);
            }
            if ($retobj) {
                $data = $sth->fetch_object();
            } else {
                $data = $sth->fetch_assoc();
            }
            $sth->close();
            return $data;
        } catch (\Exception $e) {
            if ('RETRY' != $type) {
                $this->reconnect();
                return $this->row($sql, $args, $retobj, 'RETRY');
            }
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param $sql
     * @param $args
     * @param $index
     * @param $retobj
     * @param $type
     * @return mixed
     */
    public function rowset($sql, $args = null, $index = null, $retobj = false, $type = '') {
        try {
            $sth = $this->_link->query($sql, MYSQLI_STORE_RESULT);
            if (!$sth) {
                throw new \Exception($this->_link->error, $this->_link->errno);
            }
            $data = $sth->fetch_all(MYSQLI_ASSOC);
            if ($retobj) {
                $data = (array)$this->array_to_object($data);
            } elseif (!is_null($index)) {
                $data = $this->array_index($data, $index);
            }
            $sth->close();
            return $data;
        } catch (\Exception $e) {
            if ('RETRY' != $type) {
                $this->reconnect();
                return $this->rowset($sql, $args, $index, $retobj, 'RETRY');
            }
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param string $sql
     * @param array $args
     * @param bool $retobj
     * @param string $type
     * @return mixed
     */
    private function _pages($sql, $args = null, $retobj = false, $type = '') {
        try {
            $sth = $this->_link->query($sql, MYSQLI_STORE_RESULT);
            if (!$sth) {
                throw new \Exception($this->_link->error, $this->_link->errno);
            }
            $data = $sth->fetch_all(MYSQLI_ASSOC);
            if ($retobj) {
                $data = (array)$this->array_to_object($data);
            }
            $sth->close();
            return $data;
        } catch (\Exception $e) {
            if ('RETRY' != $type) {
                $this->reconnect();
                return $this->_pages($sql, $args, $retobj, 'RETRY');
            }
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param string $sql
     * @param array $args
     * @param mixed $pageparm
     * @param int $length
     * @param bool $retobj
     * @return mixed
     */
    public function pages($sql, $args = null, $pageparm = 0, $length = 18, $retobj = false) {
        if (is_array($pageparm)) {
            //固定长度分页模式
            $ret = array('rowsets' => array(), 'pagebar' => '');
            if ($pageparm['totals'] <= 0) {
                return $ret;
            }
            $start = $this->page_start($pageparm['curpage'], $length, $pageparm['totals']);
            $ret['rowsets'] = $this->_pages($sql . " LIMIT {$start},{$length}", $args, $retobj);
            $ret['pagebar'] = \Xcs\DB::pagebar($pageparm, $length);;
            return $ret;
        } else {
            //任意长度模式
            $start = $pageparm;
            return $this->_pages($sql . " LIMIT {$start},{$length}", $args, $retobj);
        }
    }

    /**
     * @param $tableName
     * @param string $condition
     * @param string $field
     * @return mixed
     */
    public function count($tableName, $condition, $field = '*') {
        return $this->resultFirst($tableName, "COUNT({$field})", $condition);
    }

    /**
     * @param $sql
     * @param null $args
     * @return mixed
     */
    public function counts($sql, $args = null) {
        return $this->firsts($sql, $args);
    }

    /**
     * @param string $sql
     * @param null $args
     * @param string $type
     * @return mixed
     */
    public function firsts($sql, $args = null, $type = '') {
        try {
            $sth = $this->_link->query($sql);
            if (!$sth) {
                throw new \Exception($this->_link->error, $this->_link->errno);
            }
            $data = $sth->fetch_row();
            $sth->close();
            return $data[0];
        } catch (\Exception $e) {
            if ('RETRY' != $type) {
                $this->reconnect();
                return $this->firsts($sql, $args, 'RETRY');
            }
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param string $sql
     * @param null $args
     * @param string $type
     * @return mixed
     */
    public function getCols($sql, $args = null, $type = '') {
        try {
            $sth = $this->_link->query($sql, MYSQLI_STORE_RESULT);
            if (!$sth) {
                throw new \Exception($this->_link->error, $this->_link->errno);
            }
            $data = array();
            while ($col = $sth->fetch_row()) {
                $data[] = $col[0];
            }
            $sth->close();
            return $data;
        } catch (\Exception $e) {
            if ('RETRY' != $type) {
                $this->reconnect();
                return $this->getcols($sql, $args, 'RETRY');
            }
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @return mixed
     */
    public function start_trans() {
        $this->_link->query('SET AUTOCOMMIT = 0');
        $this->_link->query('START TRANSACTION');
    }

    /**
     * @param bool $commit_no_errors
     */
    public function end_trans($commit_no_errors = true) {
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
    private function _halt($message = '', $code = 0, $sql = '') {
        if ($this->_config['rundev']) {
            $this->close();
            $encode = mb_detect_encoding($message, array('ASCII', 'UTF-8', 'GB2312', 'GBK', 'BIG5'));
            $message = mb_convert_encoding($message, 'UTF-8', $encode);
            try {
                throw new \Xcs\Exception\DbException($message . ' SQL: ' . $sql, intval($code), 'MysqliDbException');
            } catch (\Xcs\Exception\DbException $e) {
                exit();
            }
        }
        return false;
    }

    /**
     * @param int $page
     * @param int $ppp
     * @param int $totalnum
     * @return int
     */
    private function page_start($page, $ppp, $totalnum) {
        $totalpage = ceil($totalnum / $ppp);
        $_page = max(1, min($totalpage, intval($page)));
        return ($_page - 1) * $ppp;
    }

    /**
     * @param $arr
     * @param $col
     * @return array
     */
    private function array_index($arr, $col) {
        if (!is_array($arr)) {
            return $arr;
        }
        $rows = array();
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
    public function array_to_object($arr) {
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