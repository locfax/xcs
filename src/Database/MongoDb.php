<?php

namespace Xcs\Database;

use \MongoDB\BSON\ObjectID;
use \MongoDB\Driver\BulkWrite;
use \MongoDB\Driver\Exception\BulkWriteException;
use \MongoDB\Driver\Exception\ConnectionException;
use \MongoDB\Driver\Exception\RuntimeException;
use \MongoDB\Driver\Manager;
use \MongoDB\Driver\Query as MongoQuery;
use \MongoDB\Driver\Command;
use \MongoDB\Driver\ReadPreference;
use \MongoDB\Driver\WriteConcern;

class MongoDb
{
    private $_config = null;
    private $_link = null;
    private $_writeConcern = null;
    private $_dbname = null;


    public function __destruct()
    {
        $this->close();
    }

    /**
     * MongoDb constructor.
     * @param $config
     * @param bool $repeat
     */
    public function __construct($config, $repeat = false)
    {
        if (is_null($this->_config)) {
            $this->_config = $config;
        }
        try {
            $uri = 'mongodb://' . ($config['login'] ? "{$config['login']}" : '') . ($config['secret'] ? ":{$config['secret']}@" : '') . $config['host'] . ($config['port'] ? ":{$config['port']}" : '') . '/' . ($config['dbname'] ? "{$config['dbname']}" : '');
            $this->_link = new Manager($uri);
            $this->_writeConcern = new WriteConcern(WriteConcern::MAJORITY, 1000);
            $this->_dbname = $config['dbname'];
        } catch (ConnectionException $e) {
            if ($repeat == false) {
                $this->__construct($config, true);
            } else {
                $this->close();
                $this->_halt('client is not connected! ' . $e->getMessage());
            }
        }
    }

    public function info()
    {
        return $this->_config;
    }

    public function close()
    {
        $this->_link = $this->_writeConcern = null;
    }

    /**
     * @param $func
     * @param $args
     * @return mixed
     */
    public function __call($func, $args)
    {

    }

    /**
     * @param $table
     * @param array $document
     * @param bool $retid
     * @return bool|string
     */
    public function create($table, $document = [], $retid = false)
    {
        try {
            if (isset($document['_id'])) {
                if (!is_object($document['_id'])) {
                    $document['_id'] = new ObjectID($document['_id']);
                }
            } else {
                $document['_id'] = new ObjectID();
            }
            $bulk = new BulkWrite();
            $_id = $bulk->insert($document);
            $ret = $this->_link->executeBulkWrite($this->_dbname . '.' . $table, $bulk, $this->_writeConcern);
            if ($retid) {
                return (string)$_id;
            }
            return $ret->getInsertedCount();
        } catch (BulkWriteException $ex) {
            return $this->_halt($ex->getMessage(), $ex->getCode());
        }
    }


    /**
     * @param $table
     * @param array $document
     * @throws \Xcs\Exception\DbException
     */
    public function replace($table, $document = [])
    {
        throw new \Xcs\Exception\DbException('未实现', 404);
    }

    /**
     * @param $table
     * @param array $document
     * @param array $condition
     * @param mixed $options
     * @return bool
     */
    public function update($table, $document = [], $condition = [], $options = 'set')
    {
        try {
            if (isset($condition['_id'])) {
                if (!is_object($condition['_id'])) {
                    $condition['_id'] = new ObjectID($condition['_id']);
                }
            }
            $bulk = new BulkWrite();
            if (is_bool($options)) {
                $options = 'set';
            }
            if ('muti' == $options) {
                $bulk->update($condition, $document, ['multi' => false, 'upsert' => false]);
            } elseif ('set' == $options) { //更新 字段
                $bulk->update($condition, ['$set' => $document], ['multi' => false, 'upsert' => false]);
            } elseif ('inc' == $options) { //递增 字段
                $bulk->update($condition, ['$inc' => $document], ['multi' => false, 'upsert' => false]);
            } elseif ('unset' == $options) { //删除 字段
                $bulk->update($condition, ['$unset' => $document], ['multi' => false, 'upsert' => false]);
            } elseif ('push' == $options) { //推入内镶文档
                $bulk->update($condition, ['$push' => $document], ['multi' => false, 'upsert' => false]);
            } elseif ('pop' == $options) { //删除内镶文档最后一个或者第一个
                $bulk->update($condition, ['$pop' => $document], ['multi' => false, 'upsert' => false]);
            } elseif ('pull' == $options) { //删除内镶文档某个值得项
                $bulk->update($condition, ['$pull' => $document], ['multi' => false, 'upsert' => false]);
            } elseif ('addToSet' == $options) { //追加到内镶文档
                $bulk->update($condition, ['$addToSet' => $document], ['multi' => false, 'upsert' => false]);
            }
            $ret = $this->_link->executeBulkWrite($this->_dbname . '.' . $table, $bulk, $this->_writeConcern);
            return $ret->getModifiedCount();
        } catch (BulkWriteException $ex) {
            return $this->_halt($ex->getMessage(), $ex->getCode());
        }
    }

    /**
     * @param $table
     * @param array $condition
     * @param bool $muti
     * @return bool
     */
    public function remove($table, $condition = [], $muti = false)
    {
        try {
            if (isset($condition['_id'])) {
                $condition['_id'] = new ObjectID($condition['_id']);
            }
            $bulk = new BulkWrite();
            if ($muti) {
                $bulk->delete($condition, ['limit' => 0]);
            } else {
                $bulk->delete($condition, ['limit' => 1]);
            }
            $ret = $this->_link->executeBulkWrite($this->_dbname . '.' . $table, $bulk, $this->_writeConcern);
            return $ret->getDeletedCount();
        } catch (BulkWriteException $ex) {
            return $this->_halt($ex->getMessage(), $ex->getCode());
        }
    }

    /**
     * @param $table
     * @param array $fields
     * @param array $condition
     * @return array|bool
     * @throws \MongoDB\Driver\Exception\Exception
     */
    public function findOne($table, $fields = [], $condition = [])
    {
        try {
            if (isset($condition['_id'])) {
                $condition['_id'] = new ObjectID($condition['_id']);
            }
            $options = [];
            $query = new MongoQuery($condition, $options);
            $cursor = $this->_link->executeQuery($this->_dbname . '.' . $table, $query, new ReadPreference(ReadPreference::RP_PRIMARY_PREFERRED));
            $cursor = $cursor->toArray();
            if (empty($cursor)) {
                return false;
            }
            $ret = (array)$cursor[0];
            if (isset($ret['_id'])) {
                $ret['_id'] = $ret['nid'] = (string)$ret['_id'];
            }
            return $ret;
        } catch (RuntimeException $ex) {
            return $this->_halt($ex->getMessage(), $ex->getCode());
        }
    }


    /**
     * @param $table
     * @param array $fields
     * @param array $condition
     * @return array|bool
     * @throws \MongoDB\Driver\Exception\Exception
     */
    public function findAll($table, $fields = [], $condition = [])
    {
        try {
            if (isset($condition['sort'])) {
                $options = [
                    'sort' => $condition['sort']
                ];
                $query = new MongoQuery($condition['query'], $options);
            } else {
                $options = [];
                $query = new MongoQuery($condition, $options);
            }
            $cursor = $this->_link->executeQuery($this->_dbname . '.' . $table, $query, new ReadPreference(ReadPreference::RP_PRIMARY_PREFERRED));
            $cursor = $cursor->toArray();

            $rowsets = [];
            foreach ($cursor as $row) {
                $row = (array)$row;
                $row['_id'] = $row['nid'] = (string)$row['_id'];
                $rowsets[] = $row;
            }
            return $rowsets;
        } catch (\Exception $ex) {
            return $this->_halt($ex->getMessage(), $ex->getCode());
        }
    }


    /**
     * @param $table
     * @param $fields
     * @param $condition
     * @param int $offset
     * @param int $length
     * @return array|bool
     * @throws \MongoDB\Driver\Exception\Exception
     */
    private function _page($table, $fields, $condition, $offset = 0, $length = 18)
    {
        try {
            if (isset($condition['sort'])) {
                $options = [
                    'sort' => $condition['sort'],
                    'limit' => (int)$length,
                    'skip' => (int)$offset
                ];
                $query = new MongoQuery($condition['query'], $options);
            } else {
                $options = [
                    'limit' => (int)$length,
                    'skip' => (int)$offset
                ];
                $query = new MongoQuery($condition['query'], $options);
            }
            $cursor = $this->_link->executeQuery($this->_dbname . '.' . $table, $query, new ReadPreference(ReadPreference::RP_PRIMARY_PREFERRED));
            $cursor = $cursor->toArray();

            $rowsets = [];
            foreach ($cursor as $row) {
                $row = (array)$row;
                if (isset($row['_id'])) {
                    $row['_id'] = $row['nid'] = (string)$row['_id'];
                }
                $rowsets[] = $row;
            }
            return $rowsets;
        } catch (\Exception $ex) {
            return $this->_halt($ex->getMessage(), $ex->getCode());
        }
    }

    /**
     * @param $table
     * @param $field
     * @param $condition
     * @param int $pageparm
     * @param int $length
     * @return array|bool
     * @throws \MongoDB\Driver\Exception\Exception
     */
    function page($table, $field, $condition, $pageparm = 0, $length = 18)
    {
        if (is_array($pageparm)) {
            //固定长度分页模式
            $ret = ['rowsets' => [], 'pagebar' => ''];
            if ($pageparm['totals'] <= 0) {
                return $ret;
            }
            $start = \Xcs\DB::page_start($pageparm['curpage'], $length, $pageparm['totals']);
            $ret['rowsets'] = $this->_page($table, $field, $condition, $start, $length);;
            $ret['pagebar'] = \Xcs\DB::pagebar($pageparm, $length);
            return $ret;
        } else {
            //任意长度模式
            $start = $pageparm;
            return $this->_page($table, $field, $condition, $start, $length);
        }
    }


    /**
     * @param $table
     * @param array $condition
     * @return bool
     * @throws \MongoDB\Driver\Exception\Exception
     */
    public function count($table, $condition = [])
    {
        try {
            if (isset($condition['_id'])) {
                $condition['_id'] = new ObjectID($condition['_id']);
            }
            $cmd = ['count' => $table, 'query' => $condition];
            $cursor = $this->_link->executeCommand($this->_dbname, new Command($cmd), new ReadPreference(ReadPreference::RP_PRIMARY_PREFERRED));
            $cursor = $cursor->toArray();
            return $cursor[0]->n;
        } catch (RuntimeException $ex) {
            return $this->_halt($ex->getMessage(), $ex->getCode());
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
                throw new \Xcs\Exception\DbException($message . ' : ' . $sql, intval($code), 'MongoDbException');
            } catch (\Xcs\Exception\DbException $e) {
                exit;
            }
        }
        return false;
    }

}
