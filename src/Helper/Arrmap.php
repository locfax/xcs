<?php

namespace Xcs\Helper;

class Arrmap {

    //php5.5+自带这个函数 只能处理二维
    //取出数组的指定列值
    public static function column(array $array, $column_key) {
        $retarr = array();
        foreach ($array as $arr) {
            if (is_array($column_key)) {
                $ret = array();
                foreach ($column_key as $key) {
                    $ret[] = isset($arr[$key]) ? $arr[$key] : null;
                }
                $retarr[] = $ret;
            } else {
                $retarr[] = $arr[$column_key];
            }
        }
        return $retarr;
    }

    /**
     * 根据指定的键值对数组排序  二维数组
     *
     * @param array $arr 要排序的数组
     * @param string $sortField 键值名称
     * @param int $sortDirection 排序方向
     *
     * @return array
     */
    public static function sort_field($arr, $sortField, $sortDirection = SORT_ASC) {
        self::sort_multi($arr, array($sortField => $sortDirection));
        return $arr;
    }

    /*
     * 数组排序
     */

    private static function sort_multi(& $arr, array $args) {
        $sortArray = array();
        $sortRule = '';
        foreach ($args as $sortField => $sortDir) {
            foreach ($arr as $offset => $row) {
                $sortArray[$sortField][$offset] = $row[$sortField];
            }
            $sortRule .= '$sortArray[\'' . $sortField . '\'], ' . $sortDir . ', ';
        }
        if (empty($sortArray) || empty($sortRule)) {
            return $arr;
        }
        eval('array_multisort(' . $sortRule . '$arr);'); //引用传值
        return $arr;
    }

    /*
     * 遍历多维数组
     */

    public static function walk($arr, callable $function, $apply_keys = false) {
        if (empty($arr)) {
            return null;
        }
        foreach ($arr as $key => $value) {
            if (is_array($value)) {
                $arr[$key] = self::walk($value, $function, $apply_keys);
            } else {
                $val = $function($value);
                if ($apply_keys) {
                    $newkey = $function($key);
                    if ($key !== $newkey) {
                        unset($arr[$key]); //删除原来的key=>$val
                        $key = $newkey;
                    }
                }
                $arr[$key] = $val; //原来的KEY 或者新的KEY 赋值
            }
        }
        return $arr;
    }

    /**
     *  多维
     * 移除val为指定值的项目
     * @param $arr
     * @param string $delval
     * @return array
     */
    public static function remove_value($arr, $delval = '') {
        if (empty($arr)) {
            return null;
        }
        foreach ($arr as $key => $value) {
            if (is_array($value)) {
                $arr[$key] = self::remove_value($value, $delval);
            } else {
                if ($delval === $value) {
                    unset($arr[$key]);
                } else {
                    $arr[$key] = $value;
                }
            }
        }
        return $arr;
    }

    /**
     *  多维
     * 移除val为空的项
     * @param $arr
     * @return array
     */
    public static function remove_empty($arr) {
        if (empty($arr)) {
            return null;
        }
        foreach ($arr as $key => $value) {
            if (is_array($value)) {
                $arr[$key] = self::remove_empty($value);
            } else {
                if (empty($value)) {
                    unset($arr[$key]);
                } else {
                    $arr[$key] = $value;
                }
            }
        }
        return $arr;
    }

    /**
     * 将一个二维数组转为 hashmap 或者 取值
     *
     * 如果省略 $valueField 参数，则转换结果每一项为包含该项所有数据的数组。
     *
     * @param array $arr 二维数组
     * @param string $keyField 唯一 如果不是唯一会出现覆盖$value , 为空变成取某一字段的值 $valueField不能忽略
     * @param string $valueField
     *
     * @return array
     */
    public static function to_map($arr, $keyField = null, $valueField = null) {
        $map = array();
        if ($valueField) {
            foreach ($arr as $row) {
                if ($keyField) {
                    $map[$row[$keyField]] = $row[$valueField];
                } else {
                    $map[] = $row[$valueField];
                }
            }
        } else {
            foreach ($arr as $row) {
                if ($keyField) {
                    $map[$row[$keyField]] = $row;
                } else {
                    $map[] = $row;
                }
            }
        }
        return $map;
    }

    /**
     * 将一个二维数组按照指定字段的值分组
     *
     * @param array $arr
     * @param string $groupField
     *
     * @return array
     */
    public static function group_by($arr, $groupField) {
        $ret = array();
        foreach ($arr as $row) {
            $ret[$row[$groupField]][] = $row;
        }
        return $ret;
    }

    /**
     * 将一个平面的二维数组按照指定的字段转换为树状结构
     *
     * 当 $returnReferences 参数为 true 时，返回结果的 tree 字段为树，refs 字段则为节点引用。
     * 利用返回的节点引用，可以很方便的获取包含以任意节点为根的子树。
     *
     * @param array $arr 原始数据
     * @param string $fid 节点ID字段名
     * @param string $fparent 节点父ID字段名
     * @param string $index 索引字段
     * @param string $fchildrens 保存子节点的字段名
     * @param boolean $returnReferences 是否在返回结果中包含节点引用
     *
     * @return array
     */
    public static function to_tree($arr, $fid = 'catid', $fparent = 'upid', $index = 'catid', $fchildrens = 'children', $returnReferences = false) {
        $refs = $arr;
        $pkvRefs = array();
        foreach ($arr as $offset => $row) {
            $pkvRefs[$row[$fid]] = &$arr[$offset];
        }

        $tree = array();
        foreach ($arr as $offset => $row) {
            $parentId = $row[$fparent];
            if ($parentId) {
                if (!isset($pkvRefs[$parentId])) {
                    continue;
                }
                $parent = &$pkvRefs[$parentId];
                if ($index) {
                    $parent[$fchildrens][$row[$index]] = &$arr[$offset];
                } else {
                    $parent[$fchildrens][] = &$arr[$offset];
                }
            } else {
                if ($index) {
                    $tree[$row[$index]] = &$arr[$offset];
                } else {
                    $tree[] = &$arr[$offset];
                }
            }
        }
        if ($returnReferences) {
            return array('tree' => $tree, 'refs' => $refs);
        }
        return $tree;
    }

    /**
     * 将树转换为平面的数组
     *
     * @param array $tree
     * @param string $fchildrens
     *
     * @return array
     */
    public static function tree_to($tree, $fchildrens = 'children') {
        $arr = array();
        if (isset($tree[$fchildrens]) && is_array($tree[$fchildrens])) {
            foreach ($tree[$fchildrens] as $child) {
                $arr = array_merge($arr, self::tree_to($child, $fchildrens));
            }
            unset($tree[$fchildrens]);
            $arr[] = $tree;
        } else {
            $arr[] = $tree;
        }
        return $arr;
    }

}
