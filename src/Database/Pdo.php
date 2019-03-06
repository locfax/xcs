<?php

namespace Xcs\Database;

class Pdo
{

    private $_config = null;
    private $_link = null;

    public function __destruct()
    {
        $this->close();
    }

    /**
     * Pdo constructor.
     * @param $config
     * @param bool $repeat
     */
    public function __construct($config, $repeat = false)
    {
        if (is_null($this->_config)) {
            $this->_config = $config;
        }

        $opt = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_PERSISTENT => false
        ];
        try {
            $mysql = false;
            if (strpos($config['dsn'], 'mysql') !== false) {
                $opt[\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
                $opt[\PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES utf8';
                $mysql = true;
            }
            $this->_link = new \PDO($config['dsn'], $config['login'], $config['secret'], $opt);
            if (!$mysql) {
                $this->_link->exec('SET NAMES utf8');
            }
        } catch (\PDOException $exception) {
            if ($repeat == false) {
                $this->__construct($config, true);
            } else {
                $this->close();
                $this->_halt($exception->getMessage(), $exception->getCode(), 'connect_error');
            }
        }
    }

    public function info()
    {
        return $this->_config;
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
        $ret = ($_fieldName == '*') ? '*' : "`{$_fieldName}`";
        return $ret;
    }

    /**
     * @param $value
     * @return string
     */
    public function qvalue($value)
    {
        if (gettype($value) === 'string') {
            return $this->_link->quote($value);
        }
        return $value;
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
     * @param array $fields
     * @param string $glue
     * @return string
     */
    public function field_value(array $fields, $glue = ',')
    {
        $addsql = $comma = '';
        foreach ($fields as $field => $value) {
            $addsql .= $comma . $this->qfield($field) . " = " . $this->qvalue($value);
            $comma = $glue;
        }
        return $addsql;
    }

    /**
     * @param $tableName
     * @param array $data
     * @param bool $retid
     * @return mixed
     */
    public function create($tableName, array $data, $retid = false)
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
            if ($retid) {
                $ret = $this->_link->lastInsertId();
            }
            return $ret;
        } catch (\PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param $tableName
     * @param array $data
     * @param bool $retnum
     * @return mixed
     */
    public function replace($tableName, array $data, $retnum = false)
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
            $sql = 'REPLACE INTO ' . $this->qtable($tableName) . '(' . $fields . ') VALUES (' . $values . ')';
            $sth = $this->_link->prepare($sql);
            $ret = $sth->execute($args);
            if ($retnum) {
                $ret = $sth->rowCount();
            }
            return $ret;
        } catch (\PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param $tableName
     * @param array $data
     * @param $condition
     * @param bool $retnum
     * @return mixed
     */
    public function update($tableName, $data, $condition, $retnum = false)
    {
        try {
            if (is_array($condition)) {
                if (!is_array($data)) {
                    $this->_halt('$data参数必须为数组', 0);
                }
                list($_data, $argsf) = $this->field_param($data, ',');
                list($_condition, $argsw) = $this->field_param($condition, ' AND ');
                $args = array_merge($argsf, $argsw);
                $sql = 'UPDATE ' . $this->qtable($tableName) . " SET {$_data} WHERE {$_condition}";
                $sth = $this->_link->prepare($sql);
                $ret = $sth->execute($args);
                if ($retnum) {
                    $ret = $sth->rowCount();
                }
                return $ret;
            } else {
                if (is_array($data)) {
                    $_data = $this->field_value($data, ',');
                } else {
                    $_data = $data;
                }
                $sql = 'UPDATE ' . $this->qtable($tableName) . " SET {$_data} WHERE {$condition}";
                return $this->_link->exec($sql);
            }
        } catch (\PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param $tableName
     * @param $condition
     * @param bool $muti
     * @return mixed
     */
    public function remove($tableName, $condition, $muti = true)
    {
        if (is_array($condition)) {
            $_condition = $this->field_value($condition, ' AND ');
        } else {
            $_condition = $condition;
        }
        $limit = $muti ? '' : ' LIMIT 1';
        try {
            $sql = 'DELETE FROM ' . $this->qtable($tableName) . ' WHERE ' . $_condition . $limit;
            return $this->_link->exec($sql);
        } catch (\PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param $tableName
     * @param string $field
     * @param $condition
     * @param $retobj
     * @return mixed
     */
    public function findOne($tableName, $field, $condition, $retobj = false)
    {
        try {
            if (is_array($condition)) {
                list($_condition, $args) = $this->field_param($condition, ' AND ');
                $sql = 'SELECT ' . $field . ' FROM ' . $this->qtable($tableName) . ' WHERE ' . $_condition . ' LIMIT 0,1';
                $sth = $this->_link->prepare($sql);
                $sth->execute($args);
            } else {
                $sql = 'SELECT ' . $field . ' FROM ' . $this->qtable($tableName) . ' WHERE ' . $condition . ' LIMIT 0,1';
                $sth = $this->_link->query($sql);
            }
            if ($retobj) {
                $data = $sth->fetch(\PDO::FETCH_OBJ);
            } else {
                $data = $sth->fetch();
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
     * @param string $field
     * @param string $condition
     * @param null $index
     * @param bool $retobj
     * @return mixed
     */
    public function findAll($tableName, $field = '*', $condition = '', $index = null, $retobj = false)
    {
        try {
            if (is_array($condition) && !empty($condition)) {
                list($_condition, $args) = $this->field_param($condition, ' AND ');
                $sql = 'SELECT ' . $field . ' FROM ' . $this->qtable($tableName) . ' WHERE ' . $_condition;
                $sth = $this->_link->prepare($sql);
                $sth->execute($args);
            } else {
                if (empty($condition)) {
                    $sql = 'SELECT ' . $field . ' FROM ' . $this->qtable($tableName);
                } else {
                    $sql = 'SELECT ' . $field . ' FROM ' . $this->qtable($tableName) . ' WHERE ' . $condition;
                }
                $sth = $this->_link->query($sql);
            }
            if ($retobj) {
                $data = $sth->fetchAll(\PDO::FETCH_OBJ);
            } else {
                $data = $sth->fetchAll();
                if (!is_null($index)) {
                    $data = $this->array_index($data, $index);
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
     * @param $tableName
     * @param string $field
     * @param $condition
     * @param int $start
     * @param int $length
     * @param bool $retobj
     * @return mixed
     */
    private function _page($tableName, $field, $condition = '', $start = 0, $length = 20, $retobj = false)
    {
        try {
            if (is_array($condition) && !empty($condition)) {
                list($_condition, $args) = $this->field_param($condition, ' AND ');
                $args[':start'] = $start;
                $args[':length'] = $length;
                $sql = 'SELECT ' . $field . ' FROM ' . $this->qtable($tableName) . ' WHERE ' . $_condition . ' LIMIT :start,:length';
                $sth = $this->_link->prepare($sql);
                $sth->execute($args);
            } else {
                if (empty($condition)) {
                    $sql = 'SELECT ' . $field . ' FROM ' . $this->qtable($tableName) . " LIMIT {$start},{$length}";
                } else {
                    $sql = 'SELECT ' . $field . ' FROM ' . $this->qtable($tableName) . ' WHERE ' . $condition . " LIMIT {$start},{$length}";
                }
                $sth = $this->_link->query($sql);
            }
            if ($retobj) {
                $data = $sth->fetchAll(\PDO::FETCH_OBJ);
            } else {
                $data = $sth->fetchAll();
            }
            $sth->closeCursor();
            $sth = null;
            return $data;
        } catch (\PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param $table
     * @param $field
     * @param $condition
     * @param int $pageparm
     * @param int $length
     * @param bool $retobj
     * @return mixed
     */
    public function page($table, $field, $condition, $pageparm = 0, $length = 18, $retobj = false)
    {
        if (is_array($pageparm)) {
            //固定长度分页模式
            $ret = ['rowsets' => [], 'pagebar' => ''];
            if ($pageparm['totals'] <= 0) {
                return $ret;
            }
            $start = $this->page_start($pageparm['curpage'], $length, $pageparm['totals']);
            $ret['rowsets'] = $this->_page($table, $field, $condition, $start, $length, $retobj);;
            $ret['pagebar'] = \Xcs\DB::pagebar($pageparm, $length);
            return $ret;
        } else {
            //任意长度模式
            $start = $pageparm;
            return $this->_page($table, $field, $condition, $start, $length, $retobj);
        }
    }

    /**
     * @param string $tableName
     * @param string $field
     * @param mixed $condition
     * @return mixed
     */
    public function resultFirst($tableName, $field, $condition)
    {
        try {
            if (is_array($condition)) {
                list($_condition, $args) = $this->field_param($condition, ' AND ');
                $sql = "SELECT {$field} AS result FROM " . $this->qtable($tableName) . " WHERE  {$_condition} LIMIT 0,1";
                $sth = $this->_link->prepare($sql);
                $sth->execute($args);
            } else {
                $sql = "SELECT {$field} AS result FROM " . $this->qtable($tableName) . " WHERE  {$condition} LIMIT 0,1";
                $sth = $this->_link->query($sql);
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
     * @param $tableName
     * @param $field
     * @param $condition
     * @return mixed
     */
    public function getCol($tableName, $field, $condition = '')
    {
        try {
            if (is_array($condition) && !empty($condition)) {
                list($_condition, $args) = $this->field_param($condition, ' AND ');
                $sql = "SELECT {$field} AS result FROM " . $this->qtable($tableName) . " WHERE  {$_condition}";
                $sth = $this->_link->prepare($sql);
                $sth->execute($args);
            } else {
                if (empty($condition)) {
                    $sql = "SELECT {$field} AS result FROM " . $this->qtable($tableName);
                } else {
                    $sql = "SELECT {$field} AS result FROM " . $this->qtable($tableName) . " WHERE  {$condition}";
                }
                $sth = $this->_link->query($sql);
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
     * @param string $sql
     * @param $args
     * @return mixed
     */
    public function exec($sql, $args = null)
    {
        try {
            if (is_null($args)) {
                $sth = $this->_link->query($sql);
            } else {
                list($_, $_args) = $this->field_param($args);
                $sth = $this->_link->prepare($sql);
                $sth->execute($_args);
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
     * @param $sql
     * @param $args
     * @param $retobj
     * @return mixed
     */
    public function row($sql, $args = null, $retobj = false)
    {
        try {
            if (is_null($args)) {
                $sth = $this->_link->query($sql);
            } else {
                list($_, $_args) = $this->field_param($args);
                $sth = $this->_link->prepare($sql);
                $sth->execute($_args);
            }
            if ($retobj) {
                $data = $sth->fetch(\PDO::FETCH_OBJ);
            } else {
                $data = $sth->fetch();
            }
            $sth->closeCursor();
            $sth = null;
            return $data;
        } catch (\PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param $sql
     * @param $args
     * @param $index
     * @param $retobj
     * @return mixed
     */
    public function rowset($sql, $args = null, $index = null, $retobj = false)
    {
        try {
            if (is_null($args)) {
                $sth = $this->_link->query($sql);
            } else {
                list($_, $_args) = $this->field_param($args);
                $sth = $this->_link->prepare($sql);
                $sth->execute($_args);
            }
            if ($retobj) {
                $data = $sth->fetchAll(\PDO::FETCH_OBJ);
            } else {
                $data = $sth->fetchAll();
                if (!is_null($index)) {
                    $data = $this->array_index($data, $index);
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
     * @param string $sql
     * @param array $args
     * @param bool $retobj
     * @return mixed
     */
    private function _pages($sql, $args = null, $retobj = false)
    {
        try {
            if (is_null($args)) {
                $sth = $this->_link->query($sql);
            } else {
                list($_, $_args) = $this->field_param($args);
                $sth = $this->_link->prepare($sql);
                $sth->execute($_args);
            }
            if ($retobj) {
                $data = $sth->fetchAll(\PDO::FETCH_OBJ);
            } else {
                $data = $sth->fetchAll();
            }
            $sth->closeCursor();
            $sth = null;
            return $data;
        } catch (\PDOException $e) {
            return $this->_halt($e->getMessage(), $e->getCode(), $sql);
        }
    }

    /**
     * @param string $sql
     * @param array $args
     * @param mixed $pageparm
     * @param int $length
     * @param bool $retobj
     * @return mixed
     */
    public function pages($sql, $args = null, $pageparm = 0, $length = 18, $retobj = false)
    {
        if (is_array($pageparm)) {
            //固定长度分页模式
            $ret = ['rowsets' => [], 'pagebar' => ''];
            if ($pageparm['totals'] <= 0) {
                return $ret;
            }
            $start = $this->page_start($pageparm['curpage'], $length, $pageparm['totals']);
            $ret['rowsets'] = $this->_pages($sql . " LIMIT {$start},{$length}", $args, $retobj);
            $ret['pagebar'] = \Xcs\DB::pagebar($pageparm, $length);;
            return $ret;
        } else {
            //任意长度模式
            $start = $pageparm;
            return $this->_pages($sql . " LIMIT {$start},{$length}", $args, $retobj);
        }
    }

    /**
     * @param $tableName
     * @param mixed $condition
     * @param string $field
     * @return mixed
     */
    public function count($tableName, $condition, $field = '*')
    {
        return $this->resultFirst($tableName, "COUNT({$field})", $condition);
    }

    /**
     * @param $sql
     * @param null $args
     * @return mixed
     */
    public function counts($sql, $args = null)
    {
        return $this->firsts($sql, $args);
    }

    /**
     * @param string $sql
     * @param null $args
     * @return mixed
     */
    public function firsts($sql, $args = null)
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
     * @param string $sql
     * @param null $args
     * @return mixed
     */
    public function getCols($sql, $args = null)
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
     * @return mixed
     */
    public function start_trans()
    {
        return $this->_link->beginTransaction();
    }

    /**
     * @param bool $commit_no_errors
     */
    public function end_trans($commit_no_errors = true)
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
        if ($this->_config['rundev']) {
            $this->close();
            $encode = mb_detect_encoding($message, ['ASCII', 'UTF-8', 'GB2312', 'GBK', 'BIG5']);
            $message = mb_convert_encoding($message, 'UTF-8', $encode);
            try {
                throw new \Xcs\Exception\DbException($message . ' SQL: ' . $sql, intval($code), 'PdoDbException');
            } catch (\Xcs\Exception\DbException $e) {
                exit;
            }
        }
        return false;
    }

    /**
     * @param int $page
     * @param int $ppp
     * @param int $totalnum
     * @return int
     */
    private function page_start($page, $ppp, $totalnum)
    {
        $totalpage = ceil($totalnum / $ppp);
        $_page = max(1, min($totalpage, intval($page)));
        return ($_page - 1) * $ppp;
    }

    /**
     * @param $arr
     * @param $col
     * @return array
     */
    private function array_index($arr, $col)
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