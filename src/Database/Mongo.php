<?php

namespace Xcs\Database;

class Mongo
{

    private $_config = null;
    public $_link = null;
    public $_client = null;

    public function __destruct()
    {
        $this->close();
    }

    /**
     * @param $config
     */
    public function __construct($config)
    {
        if (is_null($this->_config)) {
            $this->_config = $config;
        }
        try {
            $this->_link = new \MongoClient($config['dsn'], array("connect" => false));
            $this->_link->connect();
            $this->_client = $this->_link->selectDB($config['dbname']);
            if (isset($config['login']) && $config['login']) {
                $this->_client->authenticate($config['login'], $config['secret']);
            }
        } catch (\MongoConnectionException $e) {
            $this->_halt('client is not connected!');
        }
    }

    public function reconnect()
    {
        $this->__construct($this->_config);
    }


    public function info()
    {
        return $this->_config;
    }

    public function close()
    {
        if ($this->_link) {
            $this->_link->close();
            $this->_client = null;
        }
    }

    /**
     * @param $func
     * @param $args
     * @return mixed
     */
    public function __call($func, $args)
    {
        return $this->_client && call_user_func_array(array($this->_client, $func), $args);
    }

    /**
     * @param $table
     * @param array $document
     * @param bool $retid
     * @param string $type
     * @return bool|string
     */
    public function create($table, $document = array(), $retid = false, $type = '')
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
            $ret = $collection->insert($document, array('w' => 1));
            if ($retid && $ret) {
                $insert_id = (string)$document['_id'];
                return $insert_id;
            }
            return $ret['ok'];
        } catch (\Exception $ex) {
            if ('RETRY' !== $type) {
                $this->reconnect();
                return $this->create($table, $document, $retid, 'RETRY');
            }
            return $this->_halt($ex->getMessage(), $ex->getCode());
        }
    }

    /**
     * @param $table
     * @param array $document
     * @param string $type
     * @return bool
     */
    public function replace($table, $document = array(), $type = '')
    {
        try {
            if (isset($document['_id'])) {
                $document['_id'] = new \MongoId($document['_id']);
            }
            $collection = $this->_client->selectCollection($table);
            $ret = $collection->save($document);
            return $ret['ok'];
        } catch (\Exception $ex) {
            if ('RETRY' !== $type) {
                $this->reconnect();
                return $this->replace($table, $document, 'RETRY');
            }
            return $this->_halt($ex->getMessage(), $ex->getCode());
        }
    }

    /**
     * @param $table
     * @param array $document
     * @param array $condition
     * @param string $options
     * @param string $type
     * @return bool
     */
    public function update($table, $document = array(), $condition = array(), $options = 'set', $type = '')
    {
        try {
            if (isset($condition['_id'])) {
                $condition['_id'] = new \MongoId($condition['_id']);
            }
            $collection = $this->_client->selectCollection($table);
            if (is_bool($options)) {
                $options = 'set';
            }
            $ret = null;
            if ('muti' == $options) {
                $ret = $collection->update($condition, $document);
            } elseif ('set' == $options) { //更新 字段
                $ret = $collection->update($condition, array('$set' => $document));
            } elseif ('inc' == $options) { //递增 字段
                $ret = $collection->update($condition, array('$inc' => $document));
            } elseif ('unset' == $options) { //删除 字段
                $ret = $collection->update($condition, array('$unset' => $document));
            } elseif ('push' == $options) { //推入内镶文档
                $ret = $collection->update($condition, array('$push' => $document));
            } elseif ('pop' == $options) { //删除内镶文档最后一个或者第一个
                $ret = $collection->update($condition, array('$pop' => $document));
            } elseif ('pull' == $options) { //删除内镶文档某个值得项
                $ret = $collection->update($condition, array('$pull' => $document));
            } elseif ('addToSet' == $options) { //追加到内镶文档
                $ret = $collection->update($condition, array('$addToSet' => $document));
            }
            return $ret;
        } catch (\Exception $ex) {
            if ('RETRY' !== $type) {
                $this->reconnect();
                return $this->update($table, $document, $condition, $options, 'RETRY');
            }
            return $this->_halt($ex->getMessage(), $ex->getCode());
        }
    }

    /**
     * @param $table
     * @param array $condition
     * @param bool $muti
     * @param string $type
     * @return bool
     */
    public function remove($table, $condition = array(), $muti = false, $type = '')
    {
        try {
            if (isset($condition['_id'])) {
                $condition['_id'] = new \MongoId($condition['_id']);
            }
            $collection = $this->_client->selectCollection($table);
            if ($muti) {
                $ret = $collection->remove($condition);
            } else {
                $ret = $collection->remove($condition, array('justOne' => true));
            }
            return $ret;
        } catch (\Exception $ex) {
            if ('RETRY' !== $type) {
                $this->reconnect();
                return $this->remove($table, $condition, $muti, 'RETRY');
            }
            return $this->_halt($ex->getMessage(), $ex->getCode());
        }
    }

    /**
     * @param $table
     * @param array $fields
     * @param array $condition
     * @param string $type
     * @return mixed
     */
    public function findOne($table, $fields = array(), $condition = array(), $type = '')
    {
        try {
            if (isset($condition['_id'])) {
                $condition['_id'] = new \MongoId($condition['_id']);
            }
            $collection = $this->_client->selectCollection($table);
            $cursor = $collection->findOne($condition, $fields);
            if (isset($cursor['_id'])) {
                $cursor['_id'] = $cursor['nid'] = $cursor['_id']->{'$id'};
            }
            return $cursor;
        } catch (\Exception $ex) {
            if ('RETRY' !== $type) {
                $this->reconnect();
                return $this->findOne($table, $fields, $condition, 'RETRY');
            }
            return $this->_halt($ex->getMessage(), $ex->getCode());
        }
    }

    /**
     * @param $table
     * @param array $fields
     * @param array $query
     * @param string $type
     * @return array|bool|\Generator
     */
    public function findAll($table, $fields = array(), $query = array(), $type = '')
    {
        try {
            $collection = $this->_client->selectCollection($table);
            if (isset($query['query'])) {
                $cursor = $collection->find($query['query'], $fields);
                if (isset($query['sort'])) {
                    $cursor = $cursor->sort($query['sort']);
                }
            } else {
                $cursor = $collection->find($query, $fields);
            }
            $rowsets = array();
            while ($cursor->hasNext()) {
                $row = $cursor->getNext();
                $row['_id'] = $row['nid'] = $row['_id']->{'$id'};
                $rowsets[] = $row;
            }
            return $rowsets;
        } catch (\Exception $ex) {
            if ('RETRY' !== $type) {
                $this->reconnect();
                return $this->findAll($table, $fields, $query, 'RETRY');
            }
            return $this->_halt($ex->getMessage(), $ex->getCode());
        }
    }

    /**
     * @param $table
     * @param $fields
     * @param $condition
     * @param int $offset
     * @param int $length
     * @param string $type
     * @return array|bool
     */
    private function _page($table, $fields, $condition, $offset = 0, $length = 18, $type = '')
    {
        try {
            $collection = $this->_client->selectCollection($table);
            if ('fields' == $condition['type']) {
                $cursor = $collection->find($condition['query'], $fields);
                if (isset($condition['sort'])) {
                    $cursor = $cursor->sort($condition['sort']);
                }
                $cursor = $cursor->limit($length)->skip($offset);
                $rowsets = array();
                while ($cursor->hasNext()) {
                    $row = $cursor->getNext();
                    $row['_id'] = $row['nid'] = $row['_id']->{'$id'};
                    $rowsets[] = $row;
                }
                return $rowsets;
            } else {
                //内镶文档查询
                if (!$fields) {
                    throw new \Xcs\Exception\DbException('fields is empty', 0);
                }
                $cursor = $collection->findOne($condition['query'], array($fields => array('$slice' => array($offset, $length))));
                return $cursor[$fields];
            }
        } catch (\Exception $ex) {
            if ('RETRY' !== $type) {
                $this->reconnect();
                return $this->_page($table, $fields, $condition, $offset, $length, 'RETRY');
            }
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
     */
    function page($table, $field, $condition, $pageparm = 0, $length = 18)
    {
        if (is_array($pageparm)) {
            //固定长度分页模式
            $ret = array('rowsets' => array(), 'pagebar' => '');
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
     * @param string $type
     * @return bool
     */
    public function count($table, $condition = array(), $type = '')
    {
        try {
            $collection = $this->_client->selectCollection($table);
            if (isset($condition['_id'])) {
                $condition['_id'] = new \MongoId($condition['_id']);
            }
            return $collection->count($condition);
        } catch (\Exception $ex) {
            if ('RETRY' !== $type) {
                $this->reconnect();
                return $this->count($table, $condition, 'RETRY');
            }
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
            $encode = mb_detect_encoding($message, array('ASCII', 'UTF-8', 'GB2312', 'GBK', 'BIG5'));
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
