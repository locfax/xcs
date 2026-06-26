<?php

namespace Xcs\Db;

use Error;
use MongoDB\BSON\ObjectID;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Command;
use MongoDB\Driver\Exception\BulkWriteException;
use MongoDB\Driver\Exception\Exception;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query as MongoQuery;
use MongoDB\Driver\ReadPreference;
use MongoDB\Driver\WriteConcern;

class MongoDb
{
    private array $_conf;
    private ?Manager $_link;
    private ?WriteConcern $_writeConcern;
    private string $_dbname;

    public function __construct(array $config)
    {
        $this->_conf = $config;
        $options = [
            'connect' => true,
            'persist' => false
        ];
        if (isset($config['options'])) {
            $options = array_merge($options, $config['options']);
        }
        if ($config['login']) {
            $dsn = sprintf('mongodb://%s:%s@%s:%s/%s', $config['login'], $config['secret'], $config['host'], $config['port'], $config['dbname']);
        } else {
            $dsn = sprintf('mongodb://%s:%s/%s', $config['host'], $config['port'], $config['dbname']);
        }
        $this->_link = new Manager($dsn, $options);
        $this->_writeConcern = new WriteConcern(WriteConcern::MAJORITY, 5000);
        $this->_dbname = $config['dbname'];
    }

    /**
     * @param string $table
     * @param array $document
     * @param bool $retId
     * @return bool|int|string
     */
    public function create(string $table, array $document = [], bool $retId = false): bool|int|string
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
     * @param string $table
     * @param array $document
     * @param array $condition
     * @param string $options
     * @return bool|int|null
     */
    public function update(string $table, array $document = [], array $condition = [], string $options = '$set'): bool|int|null
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
                return $this->_halt('the option is not support');
            }
            $ret = $this->_link->executeBulkWrite($this->_dbname . '.' . $table, $bulk, $this->_writeConcern);
            return $ret->getModifiedCount();
        } catch (BulkWriteException $ex) {
            return $this->_halt($ex->getMessage(), $ex->getCode());
        }
    }

    /**
     * @param string $table
     * @param array $condition
     * @param bool $multi
     * @return bool|int|null
     */
    public function remove(string $table, array $condition = [], bool $multi = false): bool|int|null
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
     * @param string $table
     * @param array $options
     * @param array $condition
     * @return array|bool
     */
    public function findOne(string $table, array $options = [], array $condition = []): bool|array
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
     * @param string $table
     * @param array $options
     * @param array $condition
     * @return array|bool
     */
    public function findAll(string $table, array $options = [], array $condition = []): bool|array
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
     * @param string $table
     * @param array $options
     * @param array $condition
     * @param int $offset
     * @param int $limit
     * @return array|bool
     */
    public function page(string $table, array $options = [], array $condition = [], int $offset = 0, int $limit = 20): bool|array
    {
        try {
            $options = array_merge($options, [
                'limit' => $limit,
                'skip' => $offset
            ]);
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
     * @param string $table
     * @param array $condition
     * @return int
     */
    public function count(string $table, array $condition = []): int
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
     * @param int $total
     * @return int
     */
    public function pageStart(int $page, int $ppp, int $total): int
    {
        $totalPage = ceil($total / $ppp);
        $_page = max(1, min($totalPage, intval($page)));
        return ($_page - 1) * $ppp;
    }

    /**
     * @param string $message
     * @param mixed $code
     * @return bool
     */
    private function _halt(string $message = '', mixed $code = 0): bool
    {
        if ($this->_conf['dev']) {
            $message = mb_convert_encoding($message, 'UTF-8', mb_detect_encoding($message));
            $msg = 'ERROR: ' . $message . ' CODE:' . $code;
            throw new Error($msg);
        }
        return false;
    }

}
