<?php

namespace Xcs\Db;

use Xcs\Di\BaseObject;
use Xcs\Ex\DbException;

class PdoDb extends BaseObject
{

    private $dsn;
    private $_link = null;
    private $repeat = false;

    public function __destruct()
    {
        $this->close();
    }

    /**
     * PdoDb constructor.
     * @param array|null $config
     */
    public function __construct(array $config = null)
    {
        $this->dsn = $config['dsn'];

        if (empty($this->dsn)) {
            new DbException('dsn is empty', 404, 'PdoException');
            return;
        }

        try {
            $this->_link = new \PDO($this->dsn['dsn'], $this->dsn['login'], $this->dsn['secret']);
            $this->_link->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->_link->exec('SET NAMES utf8');
        } catch (\PDOException $exception) {
            if (!$this->repeat) {
                $this->repeat = true;
                $this->__construct($config);
            } else {
                $this->close();
                $this->_halt($exception->getMessage(), $exception->getCode(), 'connect error');
            }
        }
    }

    public function info()
    {
        return $this->dsn;
    }

    public function close()
    {
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
    public function qTable($tableName)
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
    public function qField($fieldName)
    {
        return ($fieldName == '*') ? '*' : "`{$fieldName}`";
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
     * @param $tableName
     * @param array $data
     * @param bool $retId
     * @return mixed
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
        } catch (\PDOException $e) {
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
     * @param string|array $data
     * @param string|array $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array $args [':var' => $var]
     * @return bool|int
     */
    public function update($tableName, $data, $condition, $args = null)
    {
        if (is_array($condition)) {
            list($condition, $args1) = $this->field_param($condition, ' AND ');
            if (is_array($data)) {
                list($data, $args2) = $this->field_param($data, ',');
                $args = array_merge($args2, $args1);
            } else {
                $args = empty($args) ? $args1 : array_merge($args, $args1);
            }
        } else {
            if (is_array($data)) {
                list($data, $args1) = $this->field_param($data, ',');
                $args = empty($args) ? $args1 : array_merge($args1, $args);
            }
        }
        $sql = 'UPDATE ' . $this->qTable($tableName) . " SET {$data} WHERE {$condition}";
        return $this->exec($sql, $args);
    }

    /**
     * @param string $tableName
     * @param string|array $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array $args [':var' => $var]
     * @param bool $multi
     * @return bool|int
     */
    public function remove($tableName, $condition, $args = null, $multi = false)
    {
        if (is_array($condition)) {
            list($condition, $args) = $this->field_param($condition, ',');
        }
        $limit = $multi ? '' : ' LIMIT 1';
        $sql = 'DELETE FROM ' . $this->qTable($tableName) . ' WHERE ' . $condition . $limit;
        return $this->exec($sql, $args);
    }

    /**
     * @param string $tableName
     * @param string $field
     * @param string|array $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array $args [':var' => $var]
     * @param bool $retObj
     * @return bool|mixed
     */
    public function findOne($tableName, $field, $condition, $args = null, $retObj = false)
    {
        if (is_array($condition)) {
            list($condition, $args) = $this->field_param($condition, ' AND ');
        }
        $sql = 'SELECT ' . $field . ' FROM ' . $this->qTable($tableName) . ' WHERE ' . $condition . ' LIMIT 1';
        return $this->rowSql($sql, $args, $retObj);
    }

    /**
     * @param string $tableName
     * @param string $field
     * @param string|array $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array $args [':var' => $var]
     * @param null $index
     * @param bool $retObj
     * @return array|bool|mixed
     */
    public function findAll($tableName, $field = '*', $condition = '', $args = null, $index = null, $retObj = false)
    {
        if (is_array($condition) && !empty($condition)) {
            list($condition, $args) = $this->field_param($condition, ' AND ');
        }
        $condition = empty($condition) ? '' : ' WHERE ' . $condition;
        $sql = 'SELECT ' . $field . ' FROM ' . $this->qTable($tableName) . $condition;
        return $this->rowSetSql($sql, $args, $index, $retObj);
    }

    /**
     * @param string $tableName
     * @param string $field
     * @param string|array $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array $args [':var' => $var]
     * @param int $offset
     * @param int $limit
     * @param bool $retObj
     * @return bool|mixed|null
     */
    public function page($tableName, $field, $condition, $args = null, $offset = 0, $limit = 18, $retObj = false)
    {
        if (is_array($condition) && !empty($condition)) {
            list($condition, $args) = $this->field_param($condition, ' AND ');
        }
        $condition = empty($condition) ? '' : ' WHERE ' . $condition;
        $sql = 'SELECT ' . $field . ' FROM ' . $this->qTable($tableName) . $condition;
        return $this->pageSql($sql, $args, $offset, $limit, $retObj);
    }

    /**
     * @param string $tableName
     * @param string $field
     * @param string|array $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array $args [':var' => $var]
     * @return mixed
     */
    public function first($tableName, $field, $condition, $args = null)
    {
        if (is_array($condition)) {
            list($condition, $args) = $this->field_param($condition, ' AND ');
        }
        $sql = "SELECT {$field} AS result FROM " . $this->qTable($tableName) . " WHERE {$condition} LIMIT 1";

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
        } catch (\PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param string $tableName
     * @param string $field
     * @param string|array $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array $args [':var' => $var]
     * @return mixed
     */
    public function col($tableName, $field, $condition, $args = null)
    {

        if (is_array($condition)) {
            list($condition, $args) = $this->field_param($condition, ' AND ');
        }
        $sql = "SELECT {$field} AS result FROM " . $this->qTable($tableName) . " WHERE {$condition}";

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
        } catch (\PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param $tableName
     * @param string|array $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array $args [':var' => $var]
     * @param string $field
     * @return mixed
     */
    public function count($tableName, $condition, $args = null, $field = '*')
    {
        return $this->first($tableName, "COUNT({$field})", $condition, $args);
    }

    /**
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array $args [':var' => $var]
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
        } catch (\PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array $args [':var' => $var]
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
                $data = $sth->fetch(\PDO::FETCH_OBJ);
            } else {
                $data = $sth->fetch(\PDO::FETCH_ASSOC);
            }
            $sth->closeCursor();
            $sth = null;
            return $data;
        } catch (\PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array $args [':var' => $var]
     * @param $index
     * @param $retObj
     * @return mixed
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
                $data = $sth->fetchAll(\PDO::FETCH_OBJ);
                if (!is_null($index)) {
                    $data = $this->_object_index($data, $index);
                }
            } else {
                $data = $sth->fetchAll(\PDO::FETCH_ASSOC);
                if (!is_null($index)) {
                    $data = $this->_array_index($data, $index);
                }
            }
            $sth->closeCursor();
            $sth = null;
            return $data;
        } catch (\PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array $args [':var' => $var]
     * @param int $offset
     * @param int $limit
     * @param bool $retObj
     * @return array|bool|null
     */
    public function pageSql($sql, $args = null, $offset = 0, $limit = 18, $retObj = false)
    {
        $sql .= " LIMIT {$limit} OFFSET {$offset}";
        try {
            if (empty($args)) {
                $sth = $this->_link->query($sql);
            } else {
                $sth = $this->_link->prepare($sql);
                $sth->execute($args);
            }
            if ($retObj) {
                $data = $sth->fetchAll(\PDO::FETCH_OBJ);
            } else {
                $data = $sth->fetchAll(\PDO::FETCH_ASSOC);
            }
            $sth->closeCursor();
            $sth = null;
            return $data;
        } catch (\PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array $args [':var' => $var]
     * @return bool|mixed
     */
    public function countSql($sql, $args = null)
    {
        return $this->firstSql($sql, $args);
    }

    /**
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array $args [':var' => $var]
     * @return bool|mixed
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
        } catch (\PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array $args [':var' => $var]
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
        } catch (\PDOException $e) {
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
        } catch (\PDOException $PDOException) {
            $this->_halt($PDOException->getMessage(), $PDOException->getCode());
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
        if ($this->dsn['rundev']) {
            $this->close();
            $encode = mb_detect_encoding($message, ['ASCII', 'UTF-8', 'GB2312', 'GBK', 'BIG5']);
            $message = mb_convert_encoding($message, 'UTF-8', $encode);
            new DbException($message . ' SQL: ' . $sql, intval($code), 'PdoException');
        }
        return false;
    }

    /**
     * @param $arr
     * @param $col
     * @return array
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
     * @param $arr
     * @param $col
     * @return array
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