<?php

namespace Xcs\Db;

use PDO;
use PDOException;
use Xcs\ExException;

class PostgresDb
{
    private array $_config;
    private PDO $_link;

    /**
     * Db constructor.
     * @param array $config
     * @throws ExException
     */
    public function __construct(array $config)
    {
        if (empty($config)) {
            throw new ExException('postgre dsn is empty');
        }
        $this->_config = $config;
        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
        if (isset($config['options'])) {
            $options = array_merge($options, $config['options']);
        }
        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $config['host'], $config['port'], $config['dbname']);
        try {
            $this->_link = new PDO($dsn, $config['login'], $config['secret'], $options);
        } catch (PDOException $exception) {
            $this->_halt($exception->getMessage(), $exception->getCode(), 'connect error');
        }
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
        if (!str_contains($tableName, '.')) {
            return 'public.' . $tableName;
        }
        return $tableName;
    }

    /**
     * @param string $fieldName
     * @return string
     */
    public function qField(string $fieldName): string
    {
        return ($fieldName == '*') ? '*' : $fieldName;
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
     * @throws ExException
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

        $sql = sprintf('INSERT INTO %s ( %s ) VALUES ( %s )', $this->qTable($tableName), $fields, $values);

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
     * @throws ExException
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

        return $this->exec(sprintf('REPLACE INTO %s ( %s ) VALUES ( %s )', $this->qTable($tableName), $fields, $values), $args);
    }

    /**
     * @param string $tableName
     * @param array|string $data
     * @param array|string $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array|null $args [':var' => $var]
     * @return bool|int
     * @throws ExException
     */
    public function update(string $tableName, array|string $data, array|string $condition, array $args = null): bool|int
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
        return $this->exec(sprintf('UPDATE %s SET %s %s', $this->qTable($tableName), $data, $condition), $args);
    }

    /**
     * @param string $tableName
     * @param array|string $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array|null $args [':var' => $var]
     * @param bool $multi
     * @return bool|int
     * @throws ExException
     */
    public function remove(string $tableName, array|string $condition, array $args = null, bool $multi = false): bool|int
    {
        if (is_array($condition)) {
            list($condition, $args) = $this->field_param($condition, ' AND ');
        }
        $condition = empty($condition) ? '' : ' WHERE ' . $condition;
        return $this->exec(sprintf('DELETE FROM %s %s', $this->qTable($tableName), $condition), $args);
    }

    /**
     * @param string $tableName
     * @param string $field
     * @param array|string $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array|null $args [':var' => $var]
     * @param string|null $orderBy
     * @param bool $retObj
     * @return mixed
     * @throws ExException
     */
    public function findOne(string $tableName, string $field, array|string $condition, array $args = null, string $orderBy = null, bool $retObj = false): mixed
    {
        if (is_array($condition)) {
            list($condition, $args) = $this->field_param($condition, ' AND ');
        }
        $condition = empty($condition) ? '' : ' WHERE ' . $condition;
        $orderBy = is_null($orderBy) ? '' : ' ORDER BY ' . $orderBy;
        return $this->rowSql(sprintf('SELECT %s FROM %s %s %s LIMIT 1', $field, $this->qTable($tableName), $condition, $orderBy), $args, $retObj);
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
     * @throws ExException
     */
    public function findAll(string $tableName, string $field = '*', array|string $condition = '', array $args = null, string $orderBy = null, string $index = null, bool $retObj = false): bool|array
    {
        if (is_array($condition) && !empty($condition)) {
            list($condition, $args) = $this->field_param($condition, ' AND ');
        }
        $condition = empty($condition) ? '' : ' WHERE ' . $condition;
        $orderBy = is_null($orderBy) ? '' : ' ORDER BY ' . $orderBy;
        return $this->rowSetSql(sprintf('SELECT %s FROM %s %s %s', $field, $this->qTable($tableName), $condition, $orderBy), $args, $index, $retObj);
    }

    /**
     * @param string $tableName
     * @param string $field
     * @param array|string $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array|null $args [':var' => $var]
     * @param string|null $orderBy
     * @param int $offset
     * @param int $limit
     * @param bool $retObj
     * @return array|bool
     * @throws ExException
     */
    public function page(string $tableName, string $field, array|string $condition, array $args = null, string $orderBy = null, int $offset = 0, int $limit = 18, bool $retObj = false): bool|array
    {
        if (is_array($condition) && !empty($condition)) {
            list($condition, $args) = $this->field_param($condition, ' AND ');
        }
        $condition = empty($condition) ? '' : ' WHERE ' . $condition;
        $orderBy = is_null($orderBy) ? '' : ' ORDER BY ' . $orderBy;
        return $this->pageSql(sprintf('SELECT %s FROM %s %s %s', $field, $this->qTable($tableName), $condition, $orderBy), $args, $offset, $limit, $retObj);
    }

    /**
     * @param string $tableName
     * @param string $field
     * @param array|string $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array|null $args [':var' => $var]
     * @param string|null $orderBy
     * @return mixed
     * @throws ExException
     */
    public function first(string $tableName, string $field, array|string $condition, array $args = null, string $orderBy = null): mixed
    {
        if (is_array($condition)) {
            list($condition, $args) = $this->field_param($condition, ' AND ');
        }
        $condition = empty($condition) ? '' : ' WHERE ' . $condition;
        $orderBy = is_null($orderBy) ? '' : ' ORDER BY ' . $orderBy;
        $sql = sprintf('SELECT %s AS result FROM %s %s %s LIMIT 1', $field, $this->qTable($tableName), $condition, $orderBy);
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
     * @throws ExException
     */
    public function col(string $tableName, string $field, array|string $condition, array $args = null, string $orderBy = null): bool|array
    {
        if (is_array($condition)) {
            list($condition, $args) = $this->field_param($condition, ' AND ');
        }
        $condition = empty($condition) ? '' : ' WHERE ' . $condition;
        $orderBy = is_null($orderBy) ? '' : ' ORDER BY ' . $orderBy;
        $sql = sprintf('SELECT %s AS result FROM %s %s %s', $field, $this->qTable($tableName), $condition, $orderBy);
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
     * @param string $tableName
     * @param array|string $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array|null $args [':var' => $var]
     * @param string $field
     * @return mixed
     * @throws ExException
     */
    public function count(string $tableName, array|string $condition, array $args = null, string $field = '*'): mixed
    {
        return $this->first($tableName, sprintf('COUNT( %s )', $field), $condition, $args);
    }

    /**
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array|null $args [':var' => $var]
     * @return bool|int
     * @throws ExException
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
     * @param array $args [':var' => $var]
     * @param bool $retObj
     * @return mixed
     * @throws ExException
     */
    public function rowSql(string $sql, array $args = [], bool $retObj = false): mixed
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
     * @param array $args [':var' => $var]
     * @param string $index
     * @param bool $retObj
     * @return array|bool
     * @throws ExException
     */
    public function rowSetSql(string $sql, array $args = [], string $index = '', bool $retObj = false): bool|array
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
     * @param array $args [':var' => $var]
     * @param int $offset
     * @param int $limit
     * @param bool $retObj
     * @return array|bool
     * @throws ExException
     */
    public function pageSql(string $sql, array $args = [], int $offset = 0, int $limit = 18, bool $retObj = false): bool|array
    {
        $sql .= sprintf(" LIMIT %d OFFSET %d", $limit, $offset);
        try {
            if (empty($args)) {
                $sth = $this->_link->query($sql);
            } else {
                $sth = $this->_link->prepare($sql);
                $sth->execute($args);
            }
            if ($retObj) {
                $data = $sth->fetchAll(PDO::FETCH_OBJ);
            } else {
                $data = $sth->fetchAll(PDO::FETCH_ASSOC);
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
     * @param array $args [':var' => $var]
     * @return mixed
     * @throws ExException
     */
    public function countSql(string $sql, array $args = []): mixed
    {
        return $this->firstSql($sql, $args);
    }

    /**
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array $args [':var' => $var]
     * @return mixed
     * @throws ExException
     */
    public function firstSql(string $sql, array $args = []): mixed
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
     * @param array $args [':var' => $var]
     * @return array|bool
     * @throws ExException
     */
    public function colSql(string $sql, array $args = []): array|bool
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
     * @return void
     */
    public function startTrans(): void
    {
        $this->_link->beginTransaction();
    }

    /**
     * @param bool $commit_no_errors
     * @throws ExException
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
     * @throws ExException
     */
    private function _halt(string $message = '', int $code = 0, string $sql = ''): bool
    {
        if ($this->_config['dev']) {
            $this->close();
            $encode = mb_detect_encoding($message, ['ASCII', 'UTF-8', 'GB2312', 'GBK', 'BIG5']);
            $message = mb_convert_encoding($message, 'UTF-8', $encode);
            $msg = 'ERROR: ' . $message . ' CODE:' . $code . ' SQL: ' . $sql;
            throw new ExException($msg);
        }
        return false;
    }

    /**
     * @param array $arr
     * @param string $col
     * @return array
     */
    private function _array_index(array $arr, string $col): array
    {
        $rows = [];
        foreach ($arr as $row) {
            $rows[$row[$col]] = $row;
        }
        return $rows;
    }

    /**
     * @param array $arr
     * @param string $col
     * @return array
     */
    private function _object_index(array $arr, string $col): array
    {
        $rows = [];
        foreach ($arr as $row) {
            $rows[$row->{$col}] = $row;
        }
        return $rows;
    }

}