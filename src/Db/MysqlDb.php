<?php

namespace Xcs\Db;

use Xcs\ExException;

class MysqlDb extends Database
{

    public function __construct(array $config)
    {
        $this->connect($config);
    }

    /**
     * @param string $tableName
     * @param array $data
     * @param bool $retId
     * @return bool|int|string
     * @throws ExException
     */
    public function create(string $tableName, array $data, bool $retId = false): bool|int|string
    {
        $args = [];
        $fields = $values = $comma = '';
        foreach ($data as $field => $value) {
            $fields .= $comma . $this->qField($field);
            $values .= $comma . ':' . $field;
            $args[':' . $field] = $value;
            $comma = ',';
        }
        $res = $this->exec(sprintf('INSERT INTO %s (%s) VALUES (%s)', $this->qTable($tableName), $fields, $values), $args);
        if ($retId) {
            return $this->lastInsertId();
        }
        return $res;
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
        return $this->exec(sprintf('REPLACE INTO %s (%s) VALUES (%s)', $this->qTable($tableName), $fields, $values), $args);
    }

    /**
     * @param string $tableName
     * @param array|string $data
     * @param array|string $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array $args [':var' => $var]
     * @return bool|int
     * @throws ExException
     */
    public function update(string $tableName, array|string $data, array|string $condition, array $args = []): bool|int
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
        return $this->exec(sprintf('UPDATE %s SET %s%s', $this->qTable($tableName), $data, $condition), $args);
    }

    /**
     * @param string $tableName
     * @param array|string $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array $args [':var' => $var]
     * @param bool $multi
     * @return bool|int
     * @throws ExException
     */
    public function remove(string $tableName, array|string $condition, array $args = [], bool $multi = false): bool|int
    {
        if (is_array($condition)) {
            list($condition, $args) = $this->field_param($condition, ' AND ');
        }
        $condition = empty($condition) ? '' : ' WHERE ' . $condition;
        $limit = $multi ? '' : ' LIMIT 1';
        return $this->exec(sprintf('DELETE FROM %s%s%s', $this->qTable($tableName), $condition, $limit), $args);
    }

    /**
     * @param string $tableName
     * @param string $field
     * @param array|string $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array $args [':var' => $var]
     * @param string $orderBy
     * @param bool $retObj
     * @return mixed
     * @throws ExException
     */
    public function findOne(string $tableName, string $field, array|string $condition, array $args = [], string $orderBy = '', bool $retObj = false): mixed
    {
        if (is_array($condition)) {
            list($condition, $args) = $this->field_param($condition, ' AND ');
        }
        $condition = empty($condition) ? '' : ' WHERE ' . $condition;
        $orderBy = empty($orderBy) ? '' : ' ORDER BY ' . $orderBy;
        return $this->rowSql(sprintf('SELECT %s FROM %s%s%s LIMIT 1', $field, $this->qTable($tableName), $condition, $orderBy), $args, $retObj);
    }

    /**
     * @param string $tableName
     * @param string $field
     * @param mixed $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array $args [':var' => $var]
     * @param string $orderBy
     * @param string $index
     * @param bool $retObj
     * @return array|bool
     * @throws ExException
     */
    public function findAll(string $tableName, string $field = '*', mixed $condition = '', array $args = [], string $orderBy = '', string $index = '', bool $retObj = false): bool|array
    {
        if (is_array($condition) && !empty($condition)) {
            list($condition, $args) = $this->field_param($condition, ' AND ');
        }
        $condition = empty($condition) ? '' : ' WHERE ' . $condition;
        $orderBy = empty($orderBy) ? '' : ' ORDER BY ' . $orderBy;
        return $this->rowSetSql(sprintf('SELECT %s FROM %s%s%s', $field, $this->qTable($tableName), $condition, $orderBy), $args, $index, $retObj);
    }

    /**
     * @param string $tableName
     * @param string $field
     * @param array|string $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array $args [':var' => $var]
     * @param string $orderBy
     * @param int $offset
     * @param int $limit
     * @param string $index
     * @param bool $retObj
     * @return array|bool
     * @throws ExException
     */
    public function page(string $tableName, string $field, array|string $condition, array $args = [], string $orderBy = '', int $offset = 0, int $limit = 18, string $index = '', bool $retObj = false): bool|array
    {
        if (is_array($condition) && !empty($condition)) {
            list($condition, $args) = $this->field_param($condition, ' AND ');
        }
        $condition = empty($condition) ? '' : ' WHERE ' . $condition;
        $orderBy = empty($orderBy) ? '' : ' ORDER BY ' . $orderBy;
        $addSql = sprintf(' LIMIT %d OFFSET %d', $limit, $offset);
        return $this->rowSetSql(sprintf('SELECT %s FROM %s%s%s%s', $field, $this->qTable($tableName), $condition, $orderBy, $addSql), $args, $index, $retObj);
    }

    /**
     * @param string $tableName
     * @param string $field
     * @param array|string $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array $args [':var' => $var]
     * @param string $orderBy
     * @return mixed
     * @throws ExException
     */
    public function first(string $tableName, string $field, array|string $condition, array $args = [], string $orderBy = ''): mixed
    {
        if (is_array($condition)) {
            list($condition, $args) = $this->field_param($condition, ' AND ');
        }
        $condition = empty($condition) ? '' : ' WHERE ' . $condition;
        $orderBy = empty($orderBy) ? '' : ' ORDER BY ' . $orderBy;
        return $this->firstSql(sprintf('SELECT %s AS result FROM %s%s%s LIMIT 1', $field, $this->qTable($tableName), $condition, $orderBy), $args);
    }

    /**
     * @param string $tableName
     * @param array|string $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array $args [':var' => $var]
     * @param string $field
     * @return mixed
     * @throws ExException
     */
    public function count(string $tableName, array|string $condition, array $args = [], string $field = '*'): mixed
    {
        return $this->first($tableName, sprintf('COUNT(%s)', $field), $condition, $args);
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
     * @param string $tableName
     * @param string $field
     * @param array|string $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array $args [':var' => $var]
     * @param string $orderBy
     * @return array|bool
     * @throws ExException
     */
    public function col(string $tableName, string $field, array|string $condition, array $args = [], string $orderBy = ''): bool|array
    {
        if (is_array($condition)) {
            list($condition, $args) = $this->field_param($condition, ' AND ');
        }
        $condition = empty($condition) ? '' : ' WHERE ' . $condition;
        $orderBy = empty($orderBy) ? '' : ' ORDER BY ' . $orderBy;
        $data = $this->rowSetSql(sprintf('SELECT %s AS result FROM %s%s%s', $field, $this->qTable($tableName), $condition, $orderBy), $args);
        $res = [];
        foreach ($data as $row) {
            $res[] = $row['result'];
        }
        return $res;
    }

}