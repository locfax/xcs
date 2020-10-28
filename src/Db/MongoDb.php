<?php

namespace Xcs\Db;

use \MongoDB\BSON\ObjectID;
use \MongoDB\Driver\BulkWrite;
use \MongoDB\Driver\Exception\BulkWriteException;
use \MongoDB\Driver\Exception\Exception;
use \MongoDB\Driver\Manager;
use \MongoDB\Driver\Query as MongoQuery;
use \MongoDB\Driver\Command;
use \MongoDB\Driver\ReadPreference;
use \MongoDB\Driver\WriteConcern;
use Xcs\Di\BaseObject;
use Xcs\Ex\DbException;

class MongoDb extends BaseObject
{
    /**
     * @var array
     */
    private $dsn = [];

    /**
     * @var Manager
     */
    private $_link = null;

    /**
     * @var WriteConcern|null
     */
    private $_writeConcern = null;

    /**
     * @var mixed|null
     */
    private $_dbname = null;

    public function __destruct()
    {
        $this->close();
    }

    /**
     * MongoDb constructor.
     * @param array|null $config
     */
    public function __construct(array $config = null)
    {
        $this->dsn = $config['dsn'];

        if (empty($this->dsn)) {
            new DbException('dsn is empty', 404, 'PdoException');
            return;
        }

        $uri = 'mongodb://' . ($this->dsn['login'] ? "{$this->dsn['login']}" : '') . ($this->dsn['secret'] ? ":{$this->dsn['secret']}@" : '') . $this->dsn['host'] . ($this->dsn['port'] ? ":{$this->dsn['port']}" : '') . '/' . ($this->dsn['dbname'] ? "{$this->dsn['dbname']}" : '');
        $this->_link = new Manager($uri);
        $this->_writeConcern = new WriteConcern(WriteConcern::MAJORITY, 5000);
        $this->_dbname = $this->dsn['dbname'];

    }

    public function info()
    {
        return $this->dsn;
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
     * @return null
     */
    public function replace($table, $document = [])
    {
        return null;
    }

    /**
     * @param $table
     * @param array $document
     * @param array $condition
     * @param string $options
     * @return bool|int|null
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
     * @param null $args
     * @param bool $multi
     * @return bool|int|null
     */
    public function remove($table, $condition = [], $args = null, $multi = false)
    {
        try {
            if (isset($condition['_id'])) {
                $condition['_id'] = new ObjectID($condition['_id']);
            }
            $bulk = new BulkWrite();
            if ($multi) {
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
     * @param null $fields
     * @param array $condition
     * @param null $args
     * @return array|bool
     */
    public function findOne($table, $fields = null, $condition = [], $args = null)
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
            $cursor = null;
            return $ret;
        } catch (Exception $ex) {
            return $this->_halt($ex->getMessage(), $ex->getCode());
        }
    }


    /**
     * @param $table
     * @param array $fields
     * @param array $condition
     * @param null $args
     * @return array|bool
     */
    public function findAll($table, $fields = null, $condition = [], $args = null)
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

            $rowSets = [];
            foreach ($cursor as $row) {
                $row = (array)$row;
                $row['_id'] = $row['nid'] = (string)$row['_id'];
                $rowSets[] = $row;
            }
            $cursor = null;
            return $rowSets;
        } catch (Exception $ex) {
            return $this->_halt($ex->getMessage(), $ex->getCode());
        }
    }


    /**
     * @param $table
     * @param $fields
     * @param $condition
     * @param int $offset
     * @param int $limit
     * @return array|bool
     */
    private function _page($table, $fields = null, $condition = [], $offset = 0, $limit = 18)
    {
        try {
            if (isset($condition['sort'])) {
                $options = [
                    'sort' => $condition['sort'],
                    'limit' => (int)$limit,
                    'skip' => (int)$offset
                ];
                $query = new MongoQuery($condition['query'], $options);
            } else {
                $options = [
                    'limit' => (int)$limit,
                    'skip' => (int)$offset
                ];
                $query = new MongoQuery($condition['query'], $options);
            }
            $cursor = $this->_link->executeQuery($this->_dbname . '.' . $table, $query, new ReadPreference(ReadPreference::RP_PRIMARY_PREFERRED));
            $cursor = $cursor->toArray();

            $rowSets = [];
            foreach ($cursor as $row) {
                $row = (array)$row;
                if (isset($row['_id'])) {
                    $row['_id'] = $row['nid'] = (string)$row['_id'];
                }
                $rowSets[] = $row;
            }
            $cursor = null;
            return $rowSets;
        } catch (Exception $ex) {
            return $this->_halt($ex->getMessage(), $ex->getCode());
        }
    }

    /**
     * @param $table
     * @param $field
     * @param $condition
     * @param null $args
     * @param int $pageParam
     * @param int $limit
     * @return array|bool
     */
    function page($table, $field, $condition, $args = null, $pageParam = 0, $limit = 18)
    {
        if (is_array($pageParam)) {
            //固定长度分页模式
            if ($pageParam['totals'] <= 0) {
                return null;
            }
            $offset = $this->_page_start($pageParam['curpage'], $limit, $pageParam['totals']);
        } else {
            //任意长度模式
            $offset = $pageParam;
        }
        return $this->_page($table, $field, $condition, $offset, $limit);
    }


    /**
     * @param $table
     * @param array $condition
     * @param null $args
     * @param null $field
     * @return bool
     */
    public function count($table, $condition = [], $args = null, $field = null)
    {
        try {
            if (isset($condition['_id'])) {
                $condition['_id'] = new ObjectID($condition['_id']);
            }
            $cmd = ['count' => $table, 'query' => $condition];
            $cursor = $this->_link->executeCommand($this->_dbname, new Command($cmd), new ReadPreference(ReadPreference::RP_PRIMARY_PREFERRED));
            $cursor = $cursor->toArray();
            return $cursor[0]->n;
        } catch (Exception $ex) {
            return $this->_halt($ex->getMessage(), $ex->getCode());
        }
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
            new DbException($message . ' : ' . $sql, intval($code), 'MongoDbException');
        }
        return false;
    }

}
