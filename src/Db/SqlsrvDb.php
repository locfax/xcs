<?php

namespace Xcs\Db;

use PDO;
use PDOException;
use Xcs\DbException;

class SqlsrvDb
{
    private $dsn;
    private $_link = null;
    private $repeat = false;

    /**
     * PdoDb constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->dsn = $config;

        if (empty($this->dsn)) {
            new DbException('dsn is empty', 404, 'PdoException');
        }

        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
        if (isset($this->dsn['options'])) {
            $options = array_merge($options, $this->dsn['options']);
        }

        try {
            $this->_link = new PDO($this->dsn['dsn'], $this->dsn['login'], $this->dsn['secret'], $options);
        } catch (PDOException $exception) {
            if (!$this->repeat) {
                $this->repeat = true;
                $this->__construct($config);
            } else {
                $this->_halt($exception->getMessage(), $exception->getCode(), 'connect error', true);
            }
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    public function close()
    {
        $this->_link = null;
    }

    /**
     * @param string $func
     * @param array $args
     * @return mixed
     */
    public function __call($func, array $args)
    {
        if ($this->_link) {
            return call_user_func_array([$this->_link, $func], $args);
        }
        return null;
    }

    /**
     * @return array
     */
    public function info()
    {
        return $this->dsn;
    }

    /**
     * @param string $tableName
     * @return string
     */
    public function qTable($tableName)
    {
        return $tableName;
    }

    /**
     * @param string $fieldName
     * @return string
     */
    public function qField($fieldName)
    {
        return $fieldName;
    }

    /**
     * @param array $fields
     * @param string $glue
     * @return array
     */
    public function field_param(array $fields, $glue = ',')
    {
        $args = [];
        $sql = $comma = '';
        foreach ($fields as $field => $value) {
            $sql .= $comma . $this->qField($field) . ' = :' . $field;
            $args[':' . $field] = $value;
            $comma = $glue;
        }
        return [$sql, $args];
    }

    /**
     * @param string $tableName
     * @param array $data
     * @param bool $retId
     * @return bool|string
     */
    public function create($tableName, array $data, $retId = false)
    {
        $args = [];
        $fields = $values = $comma = '';
        foreach ($data as $field => $value) {
            $fields .= $comma . $this->qField($field);
            $values .= $comma . ':' . $field;
            $args[':' . $field] = $value;
            $comma = ',';
        }
        $sql = 'INSERT INTO ' . $this->qTable($tableName) . '(' . $fields . ') VALUES (' . $values . ')';
        try {
            $sth = $this->_link->prepare($sql);
            $ret = $sth->execute($args);
            if ($retId) {
                $ret = $this->_link->lastInsertId();
            }
            return $ret;
        } catch (PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param string $tableName
     * @param array $data
     * @return bool|int
     */
    public function replace($tableName, array $data)
    {
        $args = [];
        $fields = $values = $comma = '';
        foreach ($data as $field => $value) {
            $fields .= $comma . $this->qField($field);
            $values .= $comma . ':' . $field;
            $args[':' . $field] = $value;
            $comma = ',';
        }

        $sql = 'REPLACE INTO ' . $this->qTable($tableName) . '(' . $fields . ') VALUES (' . $values . ')';
        return $this->exec($sql, $args);
    }

    /**
     * @param string $tableName
     * @param mixed $data
     * @param mixed $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @return bool|int
     */
    public function update($tableName, $data, $condition, $args = null)
    {
        if (is_array($condition)) {
            list($condition, $args1) = $this->field_param($condition, ' AND ');
            if (is_array($data)) {
                list($data, $args2) = $this->field_param($data);
                $args = array_merge($args2, $args1);
            } else {
                $args = empty($args) ? $args1 : array_merge($args, $args1);
            }
        } else {
            if (is_array($data)) {
                list($data, $args1) = $this->field_param($data);
                $args = empty($args) ? $args1 : array_merge($args1, $args);
            }
        }
        $condition = empty($condition) ? '' : ' WHERE ' . $condition;
        $sql = 'UPDATE ' . $this->qTable($tableName) . " SET {$data} {$condition}";
        return $this->exec($sql, $args);
    }

    /**
     * @param string $tableName
     * @param mixed $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @return bool|int
     */
    public function remove($tableName, $condition, $args = null)
    {
        if (is_array($condition)) {
            list($condition, $args) = $this->field_param($condition, ' AND ');
        }
        $condition = empty($condition) ? '' : ' WHERE ' . $condition;
        $sql = 'DELETE FROM ' . $this->qTable($tableName) . $condition;
        return $this->exec($sql, $args);
    }

    /**
     * @param string $tableName
     * @param string $field
     * @param mixed $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @param mixed $orderBy
     * @param bool $retObj
     * @return mixed
     */
    public function findOne($tableName, $field, $condition, $args = null, $orderBy = null, $retObj = false)
    {
        if (is_array($condition)) {
            list($condition, $args) = $this->field_param($condition, ' AND ');
        }
        $condition = empty($condition) ? '' : ' WHERE ' . $condition;
        $orderBy = is_null($orderBy) ? '' : ' ORDER BY ' . $orderBy;
        $sql = 'SELECT TOP 1 ' . $field . ' FROM ' . $this->qTable($tableName) . $condition . $orderBy;
        return $this->rowSql($sql, $args, $retObj);
    }

    /**
     * @param string $tableName
     * @param string $field
     * @param mixed $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @param mixed $orderBy
     * @param mixed $index
     * @param bool $retObj
     * @return array|bool
     */
    public function findAll($tableName, $field = '*', $condition = '', $args = null, $orderBy = null, $index = null, $retObj = false)
    {
        if (is_array($condition) && !empty($condition)) {
            list($condition, $args) = $this->field_param($condition, ' AND ');
        }
        $condition = empty($condition) ? '' : ' WHERE ' . $condition;
        $orderBy = is_null($orderBy) ? '' : ' ORDER BY ' . $orderBy;
        $sql = 'SELECT ' . $field . ' FROM ' . $this->qTable($tableName) . $condition . $orderBy;
        return $this->rowSetSql($sql, $args, $index, $retObj);
    }

    /**
     * @param string $tableName
     * @param string $field
     * @param mixed $condition
     * @param mixed $args
     * @param mixed $orderBy
     * @param int $offset
     * @param int $ppp
     * @param bool $retObj
     * @return array|bool
     */
    public function page($tableName, $field, $condition, $args = null, $orderBy = '', $offset = 0, $ppp = 18, $retObj = false)
    {
        if (is_array($condition) && !empty($condition)) {
            list($condition, $args) = $this->field_param($condition, ' AND ');
        }
        $condition = empty($condition) ? '' : ' WHERE ' . $condition;
        $sql = 'SELECT T1.* FROM (SELECT ' . $field . ', ROW_NUMBER() OVER ( ORDER BY ' . $orderBy . ') AS ROW_NUMBER FROM ' . $this->qTable($tableName) . $condition . ') AS T1 WHERE T1.ROW_NUMBER BETWEEN ' . ($offset + 1) . ' AND ' . ($offset + $ppp);
        return $this->rowSetSql($sql, $args, null, $retObj);
    }

    /**
     * @param string $tableName
     * @param string $field
     * @param mixed $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @param mixed $orderBy
     * @return mixed
     */
    public function first($tableName, $field, $condition, $args = null, $orderBy = null)
    {
        if (is_array($condition)) {
            list($condition, $args) = $this->field_param($condition, ' AND ');
        }
        $condition = empty($condition) ? '' : ' WHERE ' . $condition;
        $orderBy = is_null($orderBy) ? '' : ' ORDER BY ' . $orderBy;
        $sql = "SELECT {$field} AS result FROM " . $this->qTable($tableName) . $condition . $orderBy;
        try {
            if (empty($args)) {
                $sth = $this->_link->query($sql);
            } else {
                $sth = $this->_link->prepare($sql);
                $sth->execute($args);
            }
            $data = $sth->fetchColumn();
            $sth->closeCursor();
            $sth = null;
            return $data;
        } catch (PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param string $tableName
     * @param string $field
     * @param mixed $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @param mixed $orderBy
     * @return array|bool
     */
    public function col($tableName, $field, $condition, $args = null, $orderBy = null)
    {
        if (is_array($condition)) {
            list($condition, $args) = $this->field_param($condition, ' AND ');
        }
        $condition = empty($condition) ? '' : ' WHERE ' . $condition;
        $orderBy = is_null($orderBy) ? '' : ' ORDER BY ' . $orderBy;
        $sql = "SELECT {$field} AS result FROM " . $this->qTable($tableName) . $condition . $orderBy;
        try {
            if (empty($args)) {
                $sth = $this->_link->query($sql);
            } else {
                $sth = $this->_link->prepare($sql);
                $sth->execute($args);
            }
            $data = [];
            while ($col = $sth->fetchColumn()) {
                $data[] = $col;
            }
            $sth->closeCursor();
            $sth = null;
            return $data;
        } catch (PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param $tableName
     * @param mixed $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @param string $field
     * @return mixed
     */
    public function count($tableName, $condition, $args = null, $field = '*')
    {
        return $this->first($tableName, "COUNT({$field})", $condition, $args);
    }

    /**
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @return bool|int
     */
    public function exec($sql, $args = null)
    {
        try {
            if (empty($args)) {
                $sth = $this->_link->query($sql);
            } else {
                $sth = $this->_link->prepare($sql);
                $sth->execute($args);
            }
            $ret = $sth->rowCount();
            $sth->closeCursor();
            $sth = null;
            return $ret;
        } catch (PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @param bool $retObj
     * @return mixed
     */
    public function rowSql($sql, $args = null, $retObj = false)
    {
        try {
            if (empty($args)) {
                $sth = $this->_link->query($sql);
            } else {
                $sth = $this->_link->prepare($sql);
                $sth->execute($args);
            }
            if ($retObj) {
                $data = $sth->fetch(PDO::FETCH_OBJ);
            } else {
                $data = $sth->fetch(PDO::FETCH_ASSOC);
            }
            $sth->closeCursor();
            $sth = null;
            return $data;
        } catch (PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @param mixed $index
     * @param bool $retObj
     * @return array|bool
     */
    public function rowSetSql($sql, $args = null, $index = null, $retObj = false)
    {
        try {
            if (empty($args)) {
                $sth = $this->_link->query($sql);
            } else {
                $sth = $this->_link->prepare($sql);
                $sth->execute($args);
            }
            if ($retObj) {
                $data = $sth->fetchAll(PDO::FETCH_OBJ);
                if (!is_null($index)) {
                    $data = $this->_object_index($data, $index);
                }
            } else {
                $data = $sth->fetchAll(PDO::FETCH_ASSOC);
                if (!is_null($index)) {
                    $data = $this->_array_index($data, $index);
                }
            }
            $sth->closeCursor();
            $sth = null;
            return $data;
        } catch (PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @return mixed
     */
    public function countSql($sql, $args = null)
    {
        return $this->firstSql($sql, $args);
    }

    /**
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @return mixed
     */
    public function firstSql($sql, $args = null)
    {
        try {
            if (empty($args)) {
                $sth = $this->_link->query($sql);
            } else {
                $sth = $this->_link->prepare($sql);
                $sth->execute($args);
            }
            $data = $sth->fetchColumn();
            $sth->closeCursor();
            $sth = null;
            return $data;
        } catch (PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param mixed $args [':var' => $var]
     * @return array|bool
     */
    public function colSql($sql, $args = null)
    {
        try {
            if (empty($args)) {
                $sth = $this->_link->query($sql);
            } else {
                $sth = $this->_link->prepare($sql);
                $sth->execute($args);
            }
            $data = [];
            while ($col = $sth->fetchColumn()) {
                $data[] = $col;
            }
            $sth->closeCursor();
            $sth = null;
            return $data;
        } catch (PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @return bool
     */
    public function startTrans()
    {
        return $this->_link->beginTransaction();
    }

    /**
     * @param bool $commit_no_errors
     */
    public function endTrans($commit_no_errors = true)
    {
        try {
            if ($commit_no_errors) {
                $this->_link->commit();
            } else {
                $this->_link->rollBack();
            }
        } catch (PDOException $PDOException) {
            $this->_halt($PDOException->getMessage(), $PDOException->getCode());
        }
    }

    /**
     * @param string $message
     * @param mixed $code
     * @param string $sql
     * @return bool
     */
    private function _halt($message = '', $code = 0, $sql = '', $stop = false)
    {
        if ($this->dsn['dev']) {
            $this->close();
            $encode = mb_detect_encoding($message, ['ASCII', 'UTF-8', 'GB2312', 'GBK', 'BIG5']);
            $message = mb_convert_encoding($message, 'UTF-8', $encode);
            echo 'ERROR: ' . $message . ' SQL: ' . $sql . ' CODE: ' . $code . PHP_EOL;
            if ($stop) exit;
        }
        return false;
    }

    /**
     * @param mixed $arr
     * @param string $col
     * @return mixed
     */
    private function _array_index($arr, $col)
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
     * @param mixed $arr
     * @param string $col
     * @return mixed
     */
    private function _object_index($arr, $col)
    {
        if (!is_array($arr)) {
            return $arr;
        }
        $rows = [];
        foreach ($arr as $row) {
            $rows[$row->{$col}] = $row;
        }
        return $rows;
    }

}