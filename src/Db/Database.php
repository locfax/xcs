<?php

namespace Xcs\Db;

use Error;
use PDO;
use PDOException;
use PDOStatement;

class Database
{
    private array $conf;
    private array $sql;
    private ?PDO $PDOLink;
    private ?PDOStatement $PDOStatement;

    public function __destruct()
    {
        $this->close();
    }

    /**
     * @param string $tableName
     * @return string
     */
    protected function qTable(string $tableName): string
    {
        return "`$tableName`";
    }

    /**
     * @param string $fieldName
     * @return string
     */
    protected function qField(string $fieldName): string
    {
        return $fieldName == '*' ? '*' : "`$fieldName`";
    }

    /**
     * @param array $fields
     * @param string $glue
     * @return array
     */
    protected function field_param(array $fields, string $glue = ','): array
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

    protected function config(array $conf): void
    {
        $this->conf = $conf;
        $this->sql = [];
    }

    private function connect(): void
    {
        if ($this->PDOLink) {
            return;
        }

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $this->conf['host'], $this->conf['port'], $this->conf['dbname']);
        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
        if (isset($this->conf['option'])) {
            $options = array_merge($options, $this->conf['option']);
        }

        $this->PDOLink = new PDO($dsn, $this->conf['login'], $this->conf['secret'], $options);
    }

    /**
     * @param string $sql
     * @param array $args
     * @return void
     */
    protected function query(string $sql, array $args = []): void
    {
        $this->sql[] = $sql; //记录sql

        $this->connect();

        if (empty($args)) {
            $this->PDOStatement = $this->PDOLink->query($sql);
        } else {
            $this->PDOStatement = $this->PDOLink->prepare($sql);
            $this->PDOStatement->execute($args);
        }
    }

    protected function lastInsertId(): bool|string
    {
        if (!$this->PDOLink) {
            return false;
        }
        return $this->PDOLink->lastInsertId();
    }

    /**
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array $args [':var' => $var]
     * @return bool|int
     */
    public function execSql(string $sql, array $args = []): bool|int
    {
        try {
            $this->query($sql, $args);
            $res = $this->PDOStatement->rowCount();
            $this->PDOStatement->closeCursor();
            return $res;
        } catch (PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode());
        }
    }

    /**
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array $args [':var' => $var]
     * @param bool $retObj
     * @return mixed
     */
    public function rowSql(string $sql, array $args = [], bool $retObj = false): mixed
    {
        try {
            $this->query($sql, $args);
            $res = $this->PDOStatement->fetch($retObj ? PDO::FETCH_OBJ : PDO::FETCH_ASSOC);
            $this->PDOStatement->closeCursor();
            return $res;
        } catch (PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode());
        }
    }

    /**
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array $args [':var' => $var]
     * @param string $index
     * @param bool $retObj
     * @return array|bool
     */
    public function rowSetSql(string $sql, array $args = [], string $index = '', bool $retObj = false): bool|array
    {
        try {
            $this->query($sql, $args);
            $res = $this->PDOStatement->fetchAll($retObj ? PDO::FETCH_OBJ : PDO::FETCH_ASSOC);
            if (!empty($index)) {
                if ($retObj) {
                    $res = $this->_object_index($res, $index);
                } else {
                    $res = $this->_array_index($res, $index);
                }
            }
            $this->PDOStatement->closeCursor();
            return $res;
        } catch (PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode());
        }
    }

    /**
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array $args [':var' => $var]
     * @return mixed
     */
    public function firstSql(string $sql, array $args = []): mixed
    {
        try {
            $this->query($sql, $args);
            $data = $this->PDOStatement->fetchColumn();
            $this->PDOStatement->closeCursor();
            return $data;
        } catch (PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode());
        }
    }

    public function startTrans(): bool
    {
        try {
            $this->connect();
            return $this->PDOLink->beginTransaction();
        } catch (PDOException $exception) {
            return $this->_halt($exception->getMessage(), $exception->getCode());
        }
    }

    /**
     * @param bool $commit_no_errors
     * @return bool
     */
    public function endTrans(bool $commit_no_errors = true): bool
    {
        try {
            if (!$this->PDOLink) {
                return false;
            }
            if ($commit_no_errors) {
                $res = $this->PDOLink->commit();
            } else {
                $res = $this->PDOLink->rollBack();
            }
            return $res;
        } catch (PDOException $PDOException) {
            return $this->_halt($PDOException->getMessage(), $PDOException->getCode());
        }
    }

    /**
     * @param string $message
     * @param mixed $code
     * @return bool
     */
    private function _halt(string $message = '', mixed $code = 0): bool
    {
        if ($this->conf['dev']) {
            $this->close();
            $message = mb_convert_encoding($message, 'UTF-8', mb_detect_encoding($message));
            $msg = 'ERROR: ' . $message . ' CODE:' . $code . ' SQL:' . implode(' ### ', $this->sql);
            throw new Error($msg);
        }

        return false;
    }

    private function close(): void
    {
        $this->PDOLink = null;
        $this->PDOStatement = null;
        $this->sql = [];
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