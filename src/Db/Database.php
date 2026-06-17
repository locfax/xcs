<?php

namespace Xcs\Db;

use PDO;
use PDOException;
use PDOStatement;
use Xcs\ExException;

class Database
{
    private array $conf;
    private ?array $sql;
    private ?PDO $PDOLink;
    private ?PDOStatement $PDOStatement;

    public function __construct(array $config)
    {
        $this->sql = [];
        $this->conf = $config;
        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
        if (isset($config['options'])) {
            $options = array_merge($options, $config['options']);
        }
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], $config['dbname']);
        try {
            $this->PDOLink = new PDO($dsn, $config['login'], $config['secret'], $options);
        } catch (PDOException $exception) {
            $this->_halt($exception->getMessage(), $exception->getCode());
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * @param string $func
     * @param array $args
     * @return mixed
     */
    public function __call(string $func, array $args)
    {
        return call_user_func_array([$this->PDOLink, $func], $args);
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

    /**
     * @param string $sql
     * @param array $args
     */
    protected function query(string $sql, array $args = []): void
    {
        if (empty($args)) {
            $this->PDOStatement = $this->PDOLink->query($sql);
        } else {
            $this->PDOStatement = $this->PDOLink->prepare($sql);
            $this->PDOStatement->execute($args);
        }
        $this->sql[] = $sql;
    }

    protected function lastInsertId(): bool|string
    {
        return $this->PDOLink->lastInsertId();
    }

    /**
     * @param string $sql 如果包含变量, 不要拼接, 把变量放入 $args
     * @param array $args [':var' => $var]
     * @return bool|int
     * @throws ExException
     */
    protected function exec(string $sql, array $args = []): bool|int
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
     * @throws ExException
     */
    protected function rowSql(string $sql, array $args = [], bool $retObj = false): mixed
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
     * @throws ExException
     */
    protected function rowSetSql(string $sql, array $args = [], string $index = '', bool $retObj = false): bool|array
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
     * @throws ExException
     */
    protected function firstSql(string $sql, array $args = []): mixed
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

    protected function startTrans(): bool
    {
        return $this->PDOLink->beginTransaction();
    }

    /**
     * @param bool $commit_no_errors
     * @throws ExException
     */
    protected function endTrans(bool $commit_no_errors = true): void
    {
        try {
            if ($commit_no_errors) {
                $this->PDOLink->commit();
            } else {
                $this->PDOLink->rollBack();
            }
        } catch (PDOException $PDOException) {
            $this->_halt($PDOException->getMessage(), $PDOException->getCode());
        }
    }

    /**
     * @param string $message
     * @param mixed $code
     * @return bool
     * @throws ExException
     */
    protected function _halt(string $message = '', mixed $code = 0): bool
    {
        if ($this->conf['dev']) {
            $this->close();
            $message = mb_convert_encoding($message, 'UTF-8', mb_detect_encoding($message));
            $msg = 'ERROR: ' . $message . ' CODE:' . $code . ' SQL:' . implode('###', $this->sql);
            throw new ExException($msg);
        }
        return false;
    }

    private function close(): void
    {
        $this->PDOLink = null;
        $this->PDOStatement = null;
        $this->sql = null;
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