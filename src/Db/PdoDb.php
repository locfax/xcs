<?php

namespace Xcs\Db;

use Xcs\BaseObject;
use Xcs\Exception\DbException;

class PdoDb extends BaseObject
{

    private $_link = null;
    private $dsn = [];
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
    public function qtable($tableName)
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
    public function qfield($fieldName)
    {
        $_fieldName = trim($fieldName);
        return ($_fieldName == '*') ? '*' : "`{$_fieldName}`";
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
            $sql .= $comma . $this->qfield($field) . ' = :' . $field;
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
            $fields .= $comma . $this->qfield($field);
            $values .= $comma . ':' . $field;
            $args[':' . $field] = $value;
            $comma = ',';
        }
        try {
            $sql = 'INSERT INTO ' . $this->qtable($tableName) . '(' . $fields . ') VALUES (' . $values . ')';
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
            $fields .= $comma . $this->qfield($field);
            $values .= $comma . ':' . $field;
            $args[':' . $field] = $value;
            $comma = ',';
        }

        $sql = 'REPLACE INTO ' . $this->qtable($tableName) . '(' . $fields . ') VALUES (' . $values . ')';
        return $this->exec($sql, $args);
    }

    /**
     * @param string $tableName
     * @param string|array $data
     * @param string|array $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array $args [':var' => $var]
     * @return bool|int
     */
    public function update($tableName, $data, $condition, array $args = null)
    {
        if (is_array($condition)) {
            list($_condition, $argsc) = $this->field_param($condition, ' AND ');
            if (is_array($data)) {
                list($_data, $argsf) = $this->field_param($data, ',');
                $args = array_merge($argsf, $argsc);
            } else {
                if (is_null($args)) {
                    $args = $argsc;
                } else {
                    $args = array_merge($args, $argsc);
                }
                $_data = $data;
            }
        } else {
            if (is_array($data)) {
                list($_data, $argsf) = $this->field_param($data, ',');
                if (is_null($args)) {
                    $args = $argsf;
                } else {
                    $args = array_merge($argsf, $args);
                }
            } else {
                $_data = $data;
            }
            $_condition = $condition;
        }
        $sql = 'UPDATE ' . $this->qtable($tableName) . " SET {$_data} WHERE {$_condition}";
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
            list($_condition, $args) = $this->field_param($condition, ',');
        } else {
            $_condition = $condition;
        }
        $limit = $multi ? '' : ' LIMIT 1';
        $sql = 'DELETE FROM ' . $this->qtable($tableName) . ' WHERE ' . $_condition . $limit;
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
    public function findOne($tableName, $field, $condition, array $args = null, $retObj = false)
    {
        if (is_array($condition)) {
            list($_condition, $args) = $this->field_param($condition, ' AND ');
        } else {
            $_condition = $condition;
        }
        $sql = 'SELECT ' . $field . ' FROM ' . $this->qtable($tableName) . ' WHERE ' . $_condition . ' LIMIT 0,1';
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
    public function findAll($tableName, $field = '*', $condition = '', array $args = null, $index = null, $retObj = false)
    {
        if (is_array($condition) && !empty($condition)) {
            list($condition, $args) = $this->field_param($condition, ' AND ');
            $_condition = ' WHERE ' . $condition;
        } else {
            $_condition = '';
            if (!empty($condition)) {
                $_condition = ' WHERE ' . $condition;
            }
        }
        $sql = 'SELECT ' . $field . ' FROM ' . $this->qtable($tableName) . $_condition;
        return $this->rowSetSql($sql, $args, $index, $retObj);
    }

    /**
     * @param string $tableName
     * @param string $field
     * @param string|array $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array $args [':var' => $var]
     * @param int|array $pageParam
     * @param int $length
     * @param bool $retObj
     * @return bool|mixed|null
     */
    public function page($tableName, $field, $condition, array $args = null, $pageParam = 0, $length = 18, $retObj = false)
    {
        if (is_array($pageParam)) {
            //固定长度分页模式
            if ($pageParam['totals'] <= 0) {
                return null;
            }
            $start = $this->_page_start($pageParam['curpage'], $length, $pageParam['totals']);
        } else {
            //任意长度模式
            $start = $pageParam;
        }

        if (is_array($condition) && !empty($condition)) {
            list($_condition, $args) = $this->field_param($condition, ' AND ');
            $args[':start'] = $start;
            $args[':length'] = $length;
            $_condition = ' WHERE ' . $_condition;
        } else {
            $_condition = '';
            if (!empty($condition)) {
                $_condition = ' WHERE ' . $condition;
            }
            $_args = [
                ':start' => $start,
                ':length' => $length
            ];
            if (is_null($args)) {
                $args = $_args;
            } else {
                $args = array_merge($args, $_args);
            }
        }
        $sql = 'SELECT ' . $field . ' FROM ' . $this->qtable($tableName) . $_condition . " LIMIT :start,:length";
        return $this->_page_sql($sql, $args, $retObj);
    }

    /**
     * @param string $tableName
     * @param string $field
     * @param string|array $condition 如果是字符串 包含变量 , 把变量放入 $args
     * @param array $args [':var' => $var]
     * @return mixed
     */
    public function first($tableName, $field, $condition, array $args = null)
    {
        try {
            if (is_array($condition)) {
                list($_condition, $args) = $this->field_param($condition, ' AND ');
                $sql = "SELECT {$field} AS result FROM " . $this->qtable($tableName) . " WHERE  {$_condition} LIMIT 0,1";
                $sth = $this->_link->prepare($sql);
                $sth->execute($args);
            } else {
                $sql = "SELECT {$field} AS result FROM " . $this->qtable($tableName) . " WHERE  {$condition} LIMIT 0,1";
                if (is_null($args)) {
                    $sth = $this->_link->query($sql);
                } else {
                    $sth = $this->_link->prepare($sql);
                    $sth->execute($args);
                }
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
    public function col($tableName, $field, $condition = null, array $args = null)
    {
        try {
            if (is_array($condition)) {
                list($_condition, $args) = $this->field_param($condition, ' AND ');
                $sql = "SELECT {$field} AS result FROM " . $this->qtable($tableName) . " WHERE  {$_condition}";
                $sth = $this->_link->prepare($sql);
                $sth->execute($args);
            } else {
                if (empty($condition)) {
                    $sql = "SELECT {$field} AS result FROM " . $this->qtable($tableName);
                    $sth = $this->_link->query($sql);
                } else {
                    if (is_null($args)) {
                        $sql = "SELECT {$field} AS result FROM " . $this->qtable($tableName) . " WHERE  {$condition}";
                        $sth = $this->_link->query($sql);
                    } else {
                        $sql = "SELECT {$field} AS result FROM " . $this->qtable($tableName) . " WHERE  {$condition}";
                        $sth = $this->_link->prepare($sql);
                        $sth->execute($args);
                    }
                }
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
    public function count($tableName, $condition, array $args = null, $field = '*')
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
            if (is_null($args)) {
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
            if (is_null($args)) {
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
            if (is_null($args)) {
                $sth = $this->_link->query($sql);
            } else {
                $sth = $this->_link->prepare($sql);
                $sth->execute($args);
            }
            if ($retObj) {
                $data = $sth->fetchAll(\PDO::FETCH_OBJ);
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
     * @param bool $retObj
     * @return array|bool
     */
    private function _page_sql($sql, $args = null, $retObj = false)
    {
        try {
            if (is_null($args)) {
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
     * @param mixed $pageParam
     * @param int $length
     * @param bool $retObj
     * @return array|bool|null
     */
    public function pageSql($sql, $args = null, $pageParam = 0, $length = 18, $retObj = false)
    {
        if (is_array($pageParam)) {
            //固定长度分页模式
            if ($pageParam['totals'] <= 0) {
                return null;
            }
            $start = $this->_page_start($pageParam['curpage'], $length, $pageParam['totals']);
        } else {
            //任意长度模式
            $start = $pageParam;
        }
        $_args = [
            ':start' => $start,
            ':length' => $length
        ];
        if (is_null($args)) {
            $args = $_args;
        } else {
            $args = array_merge($args, $_args);
        }
        return $this->_page_sql($sql . " LIMIT :start, :length", $args, $retObj);
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
            if (is_null($args)) {
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
            if (is_null($args)) {
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
     * @param int $page
     * @param int $ppp
     * @param int $totalNum
     * @return int
     */
    private function _page_start($page, $ppp, $totalNum)
    {
        $totalPage = ceil($totalNum / $ppp);
        $_page = max(1, min($totalPage, intval($page)));
        return ($_page - 1) * $ppp;
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

}