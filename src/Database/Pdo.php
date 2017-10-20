<?php

namespace Xcs\Database;

class Pdo {

    private $_config = null;
    private $_link = null;

    public function __destruct() {
        $this->close();
    }

    /**
     * @param $func
     * @param $args
     * @return mixed
     */
    public function __call($func, $args) {
        return call_user_func_array(array($this->_link, $func), $args);
    }

    /**
     * @param $config
     * @param string $type
     * @return bool
     */
    public function connect($config, $type = '') {
        if (is_null($this->_config)) {
            $this->_config = $config;
        }
        try {
            $opt = array(
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
                \PDO::ATTR_PERSISTENT => false
            );
            $this->_link = new \PDO($config['dsn'], $config['login'], $config['secret'], $opt);
            return true;
        } catch (\PDOException $e) {
            if ('RETRY' !== $type) {
                return $this->reconnect();
            }
            $this->_link = null;
            return $this->_halt($e->getMessage(), $e->getCode());
        }
    }

    /**
     * @return bool
     */
    public function reconnect() {
        return $this->connect($this->_config, 'RETRY');
    }

    public function close() {
        $this->_link = null;
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
     * @param array $fields
     * @param string $glue
     * @return array
     */
    public function field_param(array $fields, $glue = ',') {
        $args = array();
        $sql = $comma = '';
        foreach ($fields as $field => $value) {
            $sql .= $comma . $this->qfield($field) . '=:' . $field;
            $args[':' . $field] = $value;
            $comma = $glue;
        }
        return array($sql, $args);
    }

    /**
     * @param array $fields
     * @param string $glue
     * @return string
     */
    public function field_value(array $fields, $glue = ',') {
        $addsql = $comma = '';
        foreach ($fields as $field => $value) {
            if (strpos($value, '+') || strpos($value, '-')) {
                $addsql .= $comma . $this->qfield($field) . '=' . $value;
            } else {
                $addsql .= $comma . $this->qfield($field) . "='" . $value . "'";
            }
            $comma = $glue;
        }
        return $addsql;
    }

    /**
     * @param $tableName
     * @param array $data
     * @param bool $retid
     * @return bool
     */
    public function create($tableName, array $data, $retid = false) {
        if (empty($data)) {
            return false;
        }
        $args = array();
        $fields = $values = $comma = '';
        foreach ($data as $field => $value) {
            $fields .= $comma . $this->qfield($field);
            $values .= $comma . ':' . $field;
            $args[':' . $field] = $value;
            $comma = ',';
        }
        try {
            $sth = $this->_link->prepare('INSERT INTO ' . $tableName . '(' . $fields . ') VALUES (' . $values . ')');
            $ret = $sth->execute($args);
            if ($ret && $retid) {
                return $this->_link->lastInsertId();
            }
            return $ret;
        } catch (\PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode());
        }
    }

    /**
     * @param $tableName
     * @param array $data
     * @return bool
     */
    public function replace($tableName, array $data) {
        if (empty($data)) {
            return false;
        }
        $args = array();
        $fields = $values = $comma = '';
        foreach ($data as $field => $value) {
            $fields .= $comma . $this->qfield($field);
            $values .= $comma . ':' . $field;
            $args[':' . $field] = $value;
            $comma = ',';
        }
        try {
            $sth = $this->_link->prepare('REPLACE INTO ' . $tableName . '(' . $fields . ') VALUES (' . $values . ')');
            return $sth->execute($args);
        } catch (\PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode());
        }
    }

    /**
     * @param $tableName
     * @param array $data
     * @param $condition
     * @param bool $retnum
     * @return bool
     */
    public function update($tableName, $data, $condition, $retnum = false) {
        if (empty($data)) {
            return false;
        }
        try {
            if (is_array($condition)) {
                if (!is_array($data)) {
                    $this->_halt('$data参数必须为数组', 0);
                }
                list($data, $argsf) = $this->field_param($data, ',');
                list($condition, $argsw) = $this->field_param($condition, ' AND ');
                $args = array_merge($argsf, $argsw);
                $sth = $this->_link->prepare("UPDATE {$tableName} SET {$data} WHERE {$condition}");
                $sth->execute($args);
                if ($retnum) {
                    return $sth->rowCount();
                }
                return true;
            } else {
                if (is_array($data)) {
                    $data = $this->field_value($data, ',');
                }
                return $this->_link->exec("UPDATE {$tableName} SET {$data} WHERE {$condition}");
            }
        } catch (\PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode());
        }
    }

    /**
     * @param $tableName
     * @param $condition
     * @param bool $muti
     * @return bool
     */
    public function remove($tableName, $condition, $muti = true) {
        if (empty($condition)) {
            return false;
        }
        if (is_array($condition)) {
            $condition = $this->field_value($condition, ' AND ');
        }
        $limit = $muti ? '' : ' LIMIT 1';
        try {
            return $this->_link->exec('DELETE FROM ' . $tableName . ' WHERE ' . $condition . $limit);
        } catch (\PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode());
        }
    }

    /**
     * @param $tableName
     * @param string $field
     * @param $condition
     * @return bool
     */
    public function findOne($tableName, $field, $condition) {
        try {
            if (is_array($condition)) {
                list($condition, $args) = $this->field_param($condition, ' AND ');
                $sth = $this->_link->prepare('SELECT ' . $field . ' FROM ' . $tableName . ' WHERE ' . $condition . ' LIMIT 0,1');
                $sth->execute($args);
            } else {
                $sth = $this->_link->query('SELECT ' . $field . ' FROM ' . $tableName . ' WHERE ' . $condition . ' LIMIT 0,1');
            }
            return $sth->fetch();
        } catch (\PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode());
        }
    }

    /**
     * @param $tableName
     * @param string $field
     * @param string $condition
     * @param null $index
     * @return array|bool
     */
    public function findAll($tableName, $field = '*', $condition = '1', $index = null) {
        try {
            if (is_array($condition)) {
                list($condition, $args) = $this->field_param($condition, ' AND ');
                $sth = $this->_link->prepare('SELECT ' . $field . ' FROM ' . $tableName . ' WHERE ' . $condition);
                $sth->execute($args);
            } else {
                $sth = $this->_link->query('SELECT ' . $field . ' FROM ' . $tableName . ' WHERE ' . $condition);
            }
            $data = $sth->fetchAll();
            if (is_null($index)) {
                return $data;
            }
            return $this->array_index($data, $index);
        } catch (\PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode());
        }
    }

    /**
     * @param $tableName
     * @param string $field
     * @param $condition
     * @param int $start
     * @param int $length
     * @return bool
     */
    private function _page($tableName, $field, $condition, $start = 0, $length = 20) {
        try {
            if (is_array($condition)) {
                list($condition, $args) = $this->field_param($condition, ' AND ');
                $args[':start'] = $start;
                $args[':length'] = $length;
                $sth = $this->_link->prepare('SELECT ' . $field . ' FROM ' . $tableName . ' WHERE ' . $condition . ' LIMIT :start,:length');
                $sth->execute($args);
            } else {
                $sth = $this->_link->query('SELECT ' . $field . ' FROM ' . $tableName . ' WHERE ' . $condition . " LIMIT {$start},{$length}");
            }
            return $sth->fetchAll();
        } catch (\PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode());
        }
    }

    /**
     * @param $table
     * @param $field
     * @param $condition
     * @param int $pageparm
     * @param int $length
     * @return array|bool
     */
    public function page($table, $field, $condition, $pageparm = 0, $length = 18) {
        if (is_array($pageparm)) {
            //固定长度分页模式
            $ret = array('rowsets' => array(), 'pagebar' => '');
            if ($pageparm['totals'] <= 0) {
                return $ret;
            }
            $start = \Xcs\DB::page_start($pageparm['curpage'], $length, $pageparm['totals']);
            $ret['rowsets'] = $this->_page($table, $field, $condition, $start, $length);;
            $ret['pagebar'] = \Xcs\DB::pagebar($pageparm, $length);
            return $ret;
        } else {
            //任意长度模式
            $start = $pageparm;
            return $this->_page($table, $field, $condition, $start, $length);
        }
    }

    /**
     * @param string $tableName
     * @param string $field
     * @param mixed $condition
     * @return bool
     */
    public function resultFirst($tableName, $field, $condition) {
        try {
            if (is_array($condition)) {
                list($condition, $args) = $this->field_param($condition, ' AND ');
                $sth = $this->_link->prepare("SELECT {$field} AS result FROM {$tableName} WHERE  {$condition} LIMIT 0,1");
                $sth->execute($args);
            } else {
                $sth = $this->_link->query("SELECT {$field} AS result FROM {$tableName} WHERE  {$condition} LIMIT 0,1");
            }
            return $sth->fetchColumn();
        } catch (\PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode());
        }
    }


    public function getCol($tableName, $field, $condition) {
        try {
            if (is_array($condition)) {
                list($condition, $args) = $this->field_param($condition, ' AND ');
                $sth = $this->_link->prepare("SELECT {$field} AS result FROM {$tableName} WHERE  {$condition}");
                $sth->execute($args);
            } else {
                $sth = $this->_link->query("SELECT {$field} AS result FROM {$tableName} WHERE  {$condition}");
            }
            $ret = array();
            while ($col = $sth->fetchColumn()) {
                $ret[] = $col;
            }
            return $ret;
        } catch (\PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode());
        }
    }

    /**
     * @param $sql
     * @param null $args
     * @return bool
     */
    public function exec($sql, $args = null) {
        try {
            if (is_null($args)) {
                $ret = $this->_link->exec($sql);
            } else {
                list($_, $_args) = $this->field_param($args);
                $sth = $this->_link->prepare($sql);
                $ret = $sth->execute($_args);
            }
            return $ret;
        } catch (\PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode());
        }
    }

    /**
     * @param $sql
     * @param $args
     * @return bool
     */
    public function row($sql, $args = null) {
        try {
            if (is_null($args)) {
                $sth = $this->_link->query($sql);
            } else {
                list($_, $_args) = $this->field_param($args);
                $sth = $this->_link->prepare($sql);
                $sth->execute($_args);
            }
            return $sth->fetch();
        } catch (\PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode());
        }
    }

    /**
     * @param $sql
     * @param $args
     * @param $index
     * @return bool|array
     */
    public function rowset($sql, $args = null, $index = null) {
        try {
            if (is_null($args)) {
                $sth = $this->_link->query($sql);
            } else {
                list($_, $_args) = $this->field_param($args);
                $sth = $this->_link->prepare($sql);
                $sth->execute($_args);
            }
            $data = $sth->fetchAll();
            if (is_null($index)) {
                return $data;
            }
            return $this->array_index($data, $index);
        } catch (\PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode());
        }
    }

    /**
     * @param string $sql
     * @param array $args
     * @return bool
     */
    private function _pages($sql, $args = null) {
        try {
            if (is_null($args)) {
                $sth = $this->_link->query($sql);
            } else {
                list($_, $_args) = $this->field_param($args);
                $sth = $this->_link->prepare($sql);
                $sth->execute($_args);
            }
            return $sth->fetchAll();
        } catch (\PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode());
        }
    }

    /**
     * @param string $sql
     * @param array $args
     * @param mixed $pageparm
     * @param int $length
     * @return array|bool
     */
    public function pages($sql, $args = null, $pageparm = 0, $length = 18) {
        if (is_array($pageparm)) {
            //固定长度分页模式
            $ret = array('rowsets' => array(), 'pagebar' => '');
            if ($pageparm['totals'] <= 0) {
                return $ret;
            }
            $start = \Xcs\DB::page_start($pageparm['curpage'], $length, $pageparm['totals']);
            $ret['rowsets'] = $this->_pages($sql . " LIMIT {$start},{$length}", $args);;
            $ret['pagebar'] = \Xcs\DB::pagebar($pageparm, $length);;
            return $ret;
        } else {
            //任意长度模式
            $start = $pageparm;
            return $this->_pages($sql . " LIMIT {$start},{$length}", $args);
        }
    }

    /**
     * @param $tableName
     * @param string $condition
     * @param string $field
     * @return bool
     */
    public function count($tableName, $condition, $field = '*') {
        return $this->resultFirst($tableName, "COUNT({$field})", $condition);
    }

    /**
     * @param $sql
     * @param null $args
     * @return bool
     */
    public function counts($sql, $args = null) {
        return $this->firsts($sql, $args);
    }

    /**
     * @param $sql
     * @param null $args
     * @return bool
     */
    public function firsts($sql, $args = null) {
        try {
            if (is_null($args)) {
                $sth = $this->_link->query($sql);
            } else {
                $sth = $this->_link->prepare($sql);
                $sth->execute($args);
            }
            return $sth->fetchColumn();
        } catch (\PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode());
        }
    }

    public function getcols($sql, $args = null) {
        try {
            if (is_null($args)) {
                $sth = $this->_link->query($sql);
            } else {
                $sth = $this->_link->prepare($sql);
                $sth->execute($args);
            }
            $ret = array();
            while ($col = $sth->fetchColumn()) {
                $ret[] = $col;
            }
            return $ret;
        } catch (\PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode());
        }
    }

    public function start_trans() {
        $this->_link->beginTransaction();
    }

    /**
     * @param bool $commit_no_errors
     */
    public function end_trans($commit_no_errors = true) {
        if ($commit_no_errors) {
            $this->_link->commit();
        } else {
            $this->_link->rollBack();
        }
    }

    /**
     * @param string $message
     * @param int $code
     * @return bool
     * @throws \Xcs\Exception\DbException
     */
    private function _halt($message = '', $code = 0) {
        if ($this->_config['rundev']) {
            $this->close();
            $encode = mb_detect_encoding($message, array('ASCII', 'UTF-8', 'GB2312', 'GBK', 'BIG5'));
            $message = mb_convert_encoding($message, 'UTF-8', $encode);
            throw new \Xcs\Exception\DbException($message, intval($code));
        }
        return false;
    }

    /**
     * @param $string
     * @return array|string
     */
    private function daddslashes($string) {
        if (empty($string)) {
            return $string;
        }
        if (is_numeric($string)) {
            return $string;
        }
        if (is_array($string)) {
            return array_map('$this->daddslashes', $string);
        }
        return addslashes($string);
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

}
