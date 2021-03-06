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
    private $dsn;

    /**
     * @var Manager
     */
    private $_link = null;

    /**
     * @var WriteConcern
     */
    private $_writeConcern;

    /**
     * @var mixed|null
     */
    private $_dbname;

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

        $options = [
            'connect' => true,
            'persist' => false
        ];
        if (isset($this->dsn['options'])) {
            $options = array_merge($options, $this->dsn['options']);
        }

        $this->_link = new Manager($this->dsn['dsn'], $options);
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
     * @param bool $retId
     * @return bool|string
     */
    public function create($table, $document = [], $retId = false)
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
            if ($retId) {
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
     * @param array $condition
     * @param string $options
     * @return bool|int|null
     */
    public function update($table, $document = [], $condition = [], $options = '$set')
    {
        try {
            if (isset($condition['_id'])) {
                if (!is_object($condition['_id'])) {
                    $condition['_id'] = new ObjectID($condition['_id']);
                }
            }
            $bulk = new BulkWrite();
            if ('multi' == $options) {
                $bulk->update($condition, $document, ['multi' => false, 'upsert' => false]);
            } elseif (in_array($options, ['$set', '$inc', '$unset', '$push', '$pop', '$pull', '$addToSet'])) { //更新 字段
                $bulk->update($condition, [$options => $document], ['multi' => false, 'upsert' => false]);
            } else {
                return $this->_halt('the option is not support', 0);
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
     * @param bool $multi
     * @return bool|int|null
     */
    public function remove($table, $condition = [], $multi = false)
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
     * @param array $options
     * @param array $condition
     * @return array|bool
     */
    public function findOne($table, $options = [], $condition = [])
    {
        try {
            if (isset($condition['_id'])) {
                $condition['_id'] = new ObjectID($condition['_id']);
            }
            $query = new MongoQuery($condition, $options);
            $cursor = $this->_link->executeQuery($this->_dbname . '.' . $table, $query, new ReadPreference(ReadPreference::RP_PRIMARY_PREFERRED));
            $cursor = $cursor->toArray();
            if (empty($cursor)) {
                return false;
            }

            $data = (array)$cursor[0];
            if (isset($data['_id'])) {
                $data['_id'] = (string)$data['_id'];
            }
            $cursor = null;
            return $data;
        } catch (Exception $ex) {
            return $this->_halt($ex->getMessage(), $ex->getCode());
        }
    }


    /**
     * @param $table
     * @param array $options
     * @param array $condition
     * @return array|bool
     */
    public function findAll($table, $options = [], $condition = [])
    {
        try {
            $query = new MongoQuery($condition, $options);
            $cursor = $this->_link->executeQuery($this->_dbname . '.' . $table, $query, new ReadPreference(ReadPreference::RP_PRIMARY_PREFERRED));
            $cursor = $cursor->toArray();

            $rowSets = [];
            foreach ($cursor as $row) {
                $row = (array)$row;
                $row['_id'] = (string)$row['_id'];
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
     * @param array $options
     * @param array $condition
     * @param int $offset
     * @param int $limit
     * @return array|bool
     */
    public function page($table, $options = [], $condition = [], $offset = 0, $limit = 18)
    {
        $options = array_merge($options, [
            'limit' => $limit,
            'skip' => $offset
        ]);

        try {
            $query = new MongoQuery($condition, $options);
            $cursor = $this->_link->executeQuery($this->_dbname . '.' . $table, $query, new ReadPreference(ReadPreference::RP_PRIMARY_PREFERRED));
            $cursor = $cursor->toArray();

            $rowSets = [];
            foreach ($cursor as $row) {
                $row = (array)$row;
                if (isset($row['_id'])) {
                    $row['_id'] = (string)$row['_id'];
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
     * @param array $condition
     * @return bool
     */
    public function count($table, $condition = [])
    {
        try {
            if (isset($condition['_id'])) {
                $condition['_id'] = new ObjectID($condition['_id']);
            }
            $cmd = ['count' => $table];
            if (!empty($condition)) {
                $cmd['query'] = $condition;
            }
            $cursor = $this->_link->executeCommand($this->_dbname, new Command($cmd), new ReadPreference(ReadPreference::RP_PRIMARY_PREFERRED));
            $cursor = $cursor->toArray();
            if (empty($cursor)) {
                return 0;
            }
            return $cursor[0]->n;
        } catch (Exception $ex) {
            return $this->_halt($ex->getMessage(), $ex->getCode());
        }
    }

    /**
     * @param int $page
     * @param int $ppp
     * @param $total
     * @return int
     */
    public function pageStart($page, $ppp, $total)
    {
        $totalPage = ceil($total / $ppp);
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
