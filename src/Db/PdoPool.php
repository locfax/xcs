<?php

namespace Xcs\Db;

class PdoPool
{

    private $_link;

    /**
     * PdoDb constructor.
     * @param \Swoole\Database\PDOPool $pdo
     */
    public function __construct(\Swoole\Database\PDOPool $pdo)
    {
        $this->_link = $pdo;
    }

    /**
     * @param $func
     * @param $args
     * @return bool
     */
    public function __call($func, $args)
    {
        return $this->_link && call_user_func_array([$this->_link, $func], $args);
    }

    /**
     * @param $tableName
     * @return string
     */
    public function qTable($tableName): string
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
    public function qField($fieldName): string
    {
        return ($fieldName == '*') ? '*' : "`{$fieldName}`";
    }

    /**
     * @param array $fields
     * @param string $glue
     * @return array
     */
    public function field_param(array $fields, string $glue = ','): array
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
     * @return bool|int
     */
    public function create($tableName, array $data, bool $retId = false)
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
    public function replace(string $tableName, array $data)
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
     * @param array|null $args [':var' => $var]
     * @return bool|int
     */
    public function update(string $tableName, $data, $condition, array $args = null)
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
     * @param array|null $args [':var' => $var]
     * @param bool $multi
     * @return bool|int
     */
    public function remove(string $tableName, $condition, array $args = null, bool $multi = false)
    {
        if (is_array($condition)) {
            list($condition, $args) = $this->field_param($condition, ' AND ');
        }
        $limit = $multi ? '' : ' LIMIT 1';
        $sql = 'DELETE FROM ' . $this->qTable($tableName) . ' WHERE ' . $condition . $limit;
        return $this->exec($sql, $args);
    }

    /**
     * @param string $tableName
     * @param string $field
     * @param string|array $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array|null $args [':var' => $var]
     * @param null $orderBy
     * @param bool $retObj
     * @return mixed
     */
    public function findOne(string $tableName, string $field, $condition, array $args = null, $orderBy = null, bool $retObj = false)
    {
        if (is_array($condition)) {
            list($condition, $args) = $this->field_param($condition, ' AND ');
        }
        $orderBy = is_null($orderBy) ? '' : ' ORDER BY ' . $orderBy;
        $sql = 'SELECT ' . $field . ' FROM ' . $this->qTable($tableName) . ' WHERE ' . $condition . $orderBy . ' LIMIT 1';
        return $this->rowSql($sql, $args, $retObj);
    }

    /**
     * @param string $tableName
     * @param string $field
     * @param string|array $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array|null $args [':var' => $var]
     * @param null $orderBy
     * @param null $index
     * @param bool $retObj
     * @return mixed
     */
    public function findAll(string $tableName, string $field = '*', $condition = '', array $args = null, $orderBy = null, $index = null, bool $retObj = false)
    {
        if (is_array($condition) && !empty($condition)) {
            list($condition, $args) = $this->field_param($condition, ' AND ');
        }
        $orderBy = is_null($orderBy) ? '' : ' ORDER BY ' . $orderBy;
        $condition = empty($condition) ? '' : ' WHERE ' . $condition;
        $sql = 'SELECT ' . $field . ' FROM ' . $this->qTable($tableName) . $condition . $orderBy;
        return $this->rowSetSql($sql, $args, $index, $retObj);
    }

    /**
     * @param string $tableName
     * @param string $field
     * @param string|array $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array|null $args [':var' => $var]
     * @param null $orderBy
     * @param int $offset
     * @param int $limit
     * @param bool $retObj
     * @return mixed
     */
    public function page(string $tableName, string $field, $condition, array $args = null, $orderBy = null, int $offset = 0, int $limit = 18, bool $retObj = false)
    {
        if (is_array($condition) && !empty($condition)) {
            list($condition, $args) = $this->field_param($condition, ' AND ');
        }
        $orderBy = is_null($orderBy) ? '' : ' ORDER BY ' . $orderBy;
        $condition = empty($condition) ? '' : ' WHERE ' . $condition;
        $sql = 'SELECT ' . $field . ' FROM ' . $this->qTable($tableName) . $condition . $orderBy;
        return $this->pageSql($sql, $args, $offset, $limit, $retObj);
    }

    /**
     * @param string $tableName
     * @param string $field
     * @param string|array $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array|null $args [':var' => $var]
     * @param null $orderBy
     * @return mixed
     */
    public function first(string $tableName, string $field, $condition, array $args = null, $orderBy = null)
    {
        if (is_array($condition)) {
            list($condition, $args) = $this->field_param($condition, ' AND ');
        }
        $orderBy = is_null($orderBy) ? '' : ' ORDER BY ' . $orderBy;
        $sql = "SELECT {$field} AS result FROM " . $this->qTable($tableName) . " WHERE {$condition}{$orderBy} LIMIT 1";
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
     * @param array|null $args [':var' => $var]
     * @param null $orderBy
     * @return array|bool
     */
    public function col(string $tableName, string $field, $condition, array $args = null, $orderBy = null)
    {
        if (is_array($condition)) {
            list($condition, $args) = $this->field_param($condition, ' AND ');
        }
        $orderBy = is_null($orderBy) ? '' : ' ORDER BY ' . $orderBy;
        $sql = "SELECT {$field} AS result FROM " . $this->qTable($tableName) . " WHERE {$condition}{$orderBy}";
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
     * @param array|null $args [':var' => $var]
     * @param string $field
     * @return mixed
     */
    public function count($tableName, $condition, array $args = null, string $field = '*')
    {
        return $this->first($tableName, "COUNT({$field})", $condition, $args);
    }

    /**
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array|null $args [':var' => $var]
     * @return mixed
     */
    public function exec(string $sql, array $args = null)
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
     * @param array|null $args [':var' => $var]
     * @param bool $retObj
     * @return mixed
     */
    public function rowSql(string $sql, array $args = null, bool $retObj = false)
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
     * @param array|null $args [':var' => $var]
     * @param $index
     * @param bool $retObj
     * @return mixed
     */
    public function rowSetSql(string $sql, array $args = null, $index = null, bool $retObj = false)
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
     * @param array|null $args [':var' => $var]
     * @param int $offset
     * @param int $limit
     * @param bool $retObj
     * @return mixed
     */
    public function pageSql(string $sql, array $args = null, int $offset = 0, int $limit = 18, bool $retObj = false)
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
     * @param array|null $args [':var' => $var]
     * @return mixed
     */
    public function countSql(string $sql, array $args = null)
    {
        return $this->firstSql($sql, $args);
    }

    /**
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array|null $args [':var' => $var]
     * @return mixed
     */
    public function firstSql(string $sql, array $args = null)
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
     * @param array|null $args [':var' => $var]
     * @return array|bool
     */
    public function colSql(string $sql, array $args = null)
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
    public function startTrans(): bool
    {
        return $this->_link->beginTransaction();
    }

    /**
     * @param bool $commit_no_errors
     */
    public function endTrans(bool $commit_no_errors = true)
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
    private function _halt(string $message = '', int $code = 0, string $sql = ''): bool
    {
        $encode = mb_detect_encoding($message, ['ASCII', 'UTF-8', 'GB2312', 'GBK', 'BIG5']);
        $message = mb_convert_encoding($message, 'UTF-8', $encode);
        echo 'ERROR:' . $message . ' SQL:' . $sql . ' CODE: ' . $code . PHP_EOL;
        return false;
    }

    /**
     * @param $arr
     * @param $col
     * @return array
     */
    private function _array_index($arr, $col): array
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
    private function _object_index($arr, $col): array
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