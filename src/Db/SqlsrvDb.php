<?php

namespace Xcs\Db;

use PDO;
use PDOException;
use Xcs\DbException;

class SqlsrvDb
{
    private array $_config;
    private PDO $_link;
    private bool $repeat = false;

    /**
     * PdoDb constructor.
     * @param array $config
     * @throws DbException
     */
    public function __construct(array $config)
    {
        $this->_config = $config;

        if (empty($config)) {
            throw new DbException('dsn is empty', 404);
        }

        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
        if (isset($config['options'])) {
            $options = array_merge($options, $config['options']);
        }

        try {
            $dsn = sprintf('sqlsrv:Database=%s;Server=%s,%s', $config['dbname'], $config['host'], $config['port']);
            $this->_link = new PDO($dsn, $config['login'], $config['secret'], $options);
        } catch (PDOException $exception) {
            if (!$this->repeat) {
                $this->repeat = true;
                $this->__construct($config);
            } else {
                $this->_halt($exception->getMessage(), $exception->getCode(), 'connect error');
            }
        }
    }

    public function __destruct()
    {

    }

    public function close(): void
    {

    }

    /**
     * @param string $func
     * @param array $args
     * @return mixed
     */
    public function __call(string $func, array $args)
    {
        return call_user_func_array([$this->_link, $func], $args);
    }

    /**
     * @return array
     */
    public function info(): array
    {
        return $this->_config;
    }

    /**
     * @param string $tableName
     * @return string
     */
    public function qTable(string $tableName): string
    {
        return $tableName;
    }

    /**
     * @param string $fieldName
     * @return string
     */
    public function qField(string $fieldName): string
    {
        return $fieldName;
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
     * @param string $tableName
     * @param array $data
     * @param bool $retId
     * @return bool|string
     * @throws DbException
     */
    public function create(string $tableName, array $data, bool $retId = false): bool|string
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
     * @throws DbException
     */
    public function replace(string $tableName, array $data): bool|int
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
     * @throws DbException
     */
    public function update(string $tableName, mixed $data, mixed $condition, mixed $args = null): bool|int
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
     * @throws DbException
     */
    public function remove(string $tableName, mixed $condition, mixed $args = null): bool|int
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
     * @param array|null $args [':var' => $var]
     * @param string|null $orderBy
     * @param bool $retObj
     * @return mixed
     * @throws DbException
     */
    public function findOne(string $tableName, string $field, mixed $condition, array $args = null, string $orderBy = null, bool $retObj = false): mixed
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
     * @param array|string $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array|null $args [':var' => $var]
     * @param string|null $orderBy
     * @param string|null $index
     * @param bool $retObj
     * @return array|bool
     * @throws DbException
     */
    public function findAll(string $tableName, string $field = '*', array|string $condition = '', array $args = null, string $orderBy = null, string $index = null, bool $retObj = false): bool|array
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
     * @throws DbException
     */
    public function page(string $tableName, string $field, mixed $condition, mixed $args = null, mixed $orderBy = '', int $offset = 0, int $ppp = 20, bool $retObj = false): bool|array
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
     * @param array|string $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array|null $args [':var' => $var]
     * @param mixed $orderBy
     * @return mixed
     * @throws DbException
     */
    public function first(string $tableName, string $field, array|string $condition, array $args = null, mixed $orderBy = null): mixed
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
     * @param array|string $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array|null $args [':var' => $var]
     * @param string|null $orderBy
     * @return array|bool
     * @throws DbException
     */
    public function col(string $tableName, string $field, array|string $condition, array $args = null, string $orderBy = null): bool|array
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
     * @param array|string $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array|null $args [':var' => $var]
     * @param string $field
     * @return mixed
     * @throws DbException
     */
    public function count($tableName, array|string $condition, array $args = null, string $field = '*'): mixed
    {
        return $this->first($tableName, "COUNT({$field})", $condition, $args);
    }

    /**
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array|null $args [':var' => $var]
     * @return bool|int
     * @throws DbException
     */
    public function exec(string $sql, array $args = null): bool|int
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
     * @param array|null $args [':var' => $var]
     * @param bool $retObj
     * @return mixed
     * @throws DbException
     */
    public function rowSql(string $sql, array $args = null, bool $retObj = false): mixed
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
     * @throws DbException
     */
    public function rowSetSql(string $sql, mixed $args = null, mixed $index = null, bool $retObj = false): bool|array
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
     * @param array|null $args [':var' => $var]
     * @return mixed
     * @throws DbException
     */
    public function countSql(string $sql, array $args = null): mixed
    {
        return $this->firstSql($sql, $args);
    }

    /**
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array|null $args [':var' => $var]
     * @return mixed
     * @throws DbException
     */
    public function firstSql(string $sql, array $args = null): mixed
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
     * @param array|null $args [':var' => $var]
     * @return array|bool
     * @throws DbException
     */
    public function colSql(string $sql, array $args = null): bool|array
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
    public function startTrans(): bool
    {
        return $this->_link->beginTransaction();
    }

    /**
     * @param bool $commit_no_errors
     * @throws DbException
     */
    public function endTrans(bool $commit_no_errors = true): void
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
     * @param int $code
     * @param string $sql
     * @return bool
     * @throws DbException
     */
    private function _halt(string $message = '', int $code = 0, string $sql = ''): bool
    {
        if ($this->_config['dev']) {
            $this->close();
            $encode = mb_detect_encoding($message, ['ASCII', 'UTF-8', 'GB2312', 'GBK', 'BIG5']);
            $message = mb_convert_encoding($message, 'UTF-8', $encode);
            $msg = 'ERROR: ' . $message . ' SQL: ' . $sql . PHP_EOL;
            if (APP_CLI) {
                echo DEBUG_EOL . $msg . ' CODE: ' . $code . DEBUG_EOL;
            } else {
                throw new DbException($msg, $code);
            }
        }
        return false;
    }

    /**
     * @param mixed $arr
     * @param string $col
     * @return mixed
     */
    private function _array_index(mixed $arr, string $col): mixed
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
    private function _object_index(mixed $arr, string $col): mixed
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