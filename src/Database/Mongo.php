<?php

namespace Xcs\Database;

class Mongo
{

    /**
     * @var array
     */
    private $_config = [];
    /**
     * @var \MongoClient
     */
    private $_link = null;
    /**
     * @var \MongoDB
     */
    private $_client = null;

    public function __destruct()
    {
        $this->close();
    }

    /**
     * Mongo constructor.
     * @param $config
     * @param bool $repeat
     */
    public function __construct($config, $repeat = false)
    {
        $this->config = $config;

        if (empty($this->config)) {
            new \Xcs\Exception\DbException('config is empty', 404, 'PdoDbException');
            return;
        }

        try {
            $this->_link = new \MongoClient($config['dsn'], ["connect" => true]);
            $this->_client = $this->_link->selectDB($config['dbname']);
            if (isset($config['login']) && $config['login']) {
                $this->_client->authenticate($config['login'], $config['secret']);
            }
        } catch (\MongoConnectionException $e) {
            if ($repeat == false) {
                $this->__construct($config, true);
            } else {
                $this->close();
                $this->_halt('client is not connected!');
            }
        }
    }

    public function info()
    {
        return $this->_config;
    }

    public function close()
    {
        if ($this->_link) {
            $this->_link->close();
        }
        $this->_link = $this->_client = null;
    }

    /**
     * @param $func
     * @param $args
     * @return mixed
     */
    public function __call($func, $args)
    {
        return $this->_client && call_user_func_array([$this->_client, $func], $args);
    }

    /**
     * @param $table
     * @param array $document
     * @param bool $retid
     * @return bool|mixed|string
     */
    public function create($table, $document = [], $retid = false)
    {
        try {
            if (isset($document['_id'])) {
                if (!is_object($document['_id'])) {
                    $document['_id'] = new \MongoId($document['_id']);
                }
            } else {
                $document['_id'] = new \MongoId();
            }
            $collection = $this->_client->selectCollection($table);
            $ret = $collection->insert($document, ['w' => 1]);
            if ($retid && $ret) {
                $insert_id = (string)$document['_id'];
                return $insert_id;
            }
            return $ret['ok'];
        } catch (\MongoException $ex) {
            return $this->_halt($ex->getMessage(), $ex->getCode());
        }
    }

    /**
     * @param $table
     * @param array $document
     * @return bool
     */
    public function replace($table, $document = [])
    {
        try {
            if (isset($document['_id'])) {
                if (!is_object($document['_id'])) {
                    $document['_id'] = new \MongoId($document['_id']);
                }
            }
            $collection = $this->_client->selectCollection($table);
            $ret = $collection->save($document);
            return $ret['ok'];
        } catch (\MongoException $ex) {
            return $this->_halt($ex->getMessage(), $ex->getCode());
        }
    }

    /**
     * @param $table
     * @param array $document
     * @param array $condition
     * @param string $options
     * @return bool
     */
    public function update($table, $document = [], $condition = [], $options = 'set')
    {
        try {
            if (isset($condition['_id'])) {
                if (!is_object($condition['_id'])) {
                    $condition['_id'] = new \MongoId($condition['_id']);
                }
            }
            $collection = $this->_client->selectCollection($table);
            if (is_bool($options)) {
                $options = 'set';
            }
            $ret = null;
            if ('muti' == $options) {
                $ret = $collection->update($condition, $document, ['multi' => false, 'upsert' => false]);
            } elseif ('set' == $options) { //更新 字段
                $ret = $collection->update($condition, ['$set' => $document], ['multi' => false, 'upsert' => false]);
            } elseif ('inc' == $options) { //递增 字段
                $ret = $collection->update($condition, ['$inc' => $document], ['multi' => false, 'upsert' => false]);
            } elseif ('unset' == $options) { //删除 字段
                $ret = $collection->update($condition, ['$unset' => $document], ['multi' => false, 'upsert' => false]);
            } elseif ('push' == $options) { //推入内镶文档
                $ret = $collection->update($condition, ['$push' => $document], ['multi' => false, 'upsert' => false]);
            } elseif ('pop' == $options) { //删除内镶文档最后一个或者第一个
                $ret = $collection->update($condition, ['$pop' => $document], ['multi' => false, 'upsert' => false]);
            } elseif ('pull' == $options) { //删除内镶文档某个值得项
                $ret = $collection->update($condition, ['$pull' => $document], ['multi' => false, 'upsert' => false]);
            } elseif ('addToSet' == $options) { //追加到内镶文档
                $ret = $collection->update($condition, ['$addToSet' => $document], ['multi' => false, 'upsert' => false]);
            }
            return $ret;
        } catch (\MongoCursorException $ex) {
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
                if (!is_object($condition['_id'])) {
                    $condition['_id'] = new \MongoId($condition['_id']);
                }
            }
            $collection = $this->_client->selectCollection($table);
            if ($muti) {
                $ret = $collection->remove($condition);
            } else {
                $ret = $collection->remove($condition, ['justOne' => true]);
            }
            return $ret;
        } catch (\MongoCursorException $ex) {
            return $this->_halt($ex->getMessage(), $ex->getCode());
        }
    }

    /**
     * @param $table
     * @param array $fields
     * @param array $condition
     * @return array|bool|null
     */
    public function findOne($table, $fields = [], $condition = [])
    {
        try {
            if (isset($condition['_id'])) {
                if (!is_object($condition['_id'])) {
                    $condition['_id'] = new \MongoId($condition['_id']);
                }
            }
            $collection = $this->_client->selectCollection($table);
            $cursor = $collection->findOne($condition, $fields);
            if (isset($cursor['_id'])) {
                $cursor['_id'] = $cursor['nid'] = $cursor['_id']->{'$id'};
            }
            return $cursor;
        } catch (\Exception $ex) {
            return $this->_halt($ex->getMessage(), $ex->getCode());
        }
    }

    /**
     * @param $table
     * @param array $fields
     * @param array $condition
     * @return array|bool|\Generator
     */
    public function findAll($table, $fields = [], $condition = [])
    {
        try {
            $collection = $this->_client->selectCollection($table);
            if (isset($condition['query'])) {
                $cursor = $collection->find($condition['query'], $fields);
                if (isset($condition['sort'])) {
                    $cursor = $cursor->sort($condition['sort']);
                }
            } else {
                $cursor = $collection->find($condition, $fields);
            }
            $rowsets = [];
            while ($cursor->hasNext()) {
                $row = $cursor->getNext();
                $row['_id'] = $row['nid'] = $row['_id']->{'$id'};
                $rowsets[] = $row;
            }
            $cursor = null;
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
     */
    private function _page($table, $fields, $condition, $offset = 0, $length = 18)
    {
        try {
            $collection = $this->_client->selectCollection($table);
            if ('fields' == $condition['type']) {
                $cursor = $collection->find($condition['query'], $fields);
                if (isset($condition['sort'])) {
                    $cursor = $cursor->sort($condition['sort']);
                }
                $cursor = $cursor->limit($length)->skip($offset);
                $rowsets = [];
                while ($cursor->hasNext()) {
                    $row = $cursor->getNext();
                    $row['_id'] = $row['nid'] = $row['_id']->{'$id'};
                    $rowsets[] = $row;
                }
                $cursor = null;
                return $rowsets;
            } else {
                //内镶文档查询
                if (!$fields) {
                    throw new \Xcs\Exception\DbException('fields is empty', 0);
                }
                $cursor = $collection->findOne($condition['query'], [$fields => ['$slice' => [$offset, $length]]]);
                return $cursor[$fields];
            }
        } catch (\Exception $ex) {
            return $this->_halt($ex->getMessage(), $ex->getCode());
        }
    }

    /**
     * @param $table
     * @param $field
     * @param $condition
     * @param int $pageParam
     * @param int $length
     * @return array|bool
     */
    function page($table, $field, $condition, $pageParam = 0, $length = 18)
    {
        if (is_array($pageParam)) {
            //固定长度分页模式
            $ret = ['rowsets' => [], 'pagebar' => ''];
            if ($pageParam['totals'] <= 0) {
                return $ret;
            }
            $start = \Xcs\DB::pageStart($pageParam['curpage'], $length, $pageParam['totals']);
            $ret['rowsets'] = $this->_page($table, $field, $condition, $start, $length);
            $ret['pagebar'] = \Xcs\DB::pageBar($pageParam, $length);
            return $ret;
        } else {
            //任意长度模式
            $start = $pageParam;
            return $this->_page($table, $field, $condition, $start, $length);
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
            $collection = $this->_client->selectCollection($table);
            if (isset($condition['_id'])) {
                if (!is_object($condition['_id'])) {
                    $condition['_id'] = new \MongoId($condition['_id']);
                }
            }
            return $collection->count($condition);
        } catch (\Exception $ex) {
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
            new \Xcs\Exception\DbException($message . ' : ' . $sql, intval($code), 'MongoDbException');
        }
        return false;
    }

}
