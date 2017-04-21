<?php

namespace Xcs\Helper;

use Xcs\Traits\Singleton;

class Category extends Singleton{

    //管理DIV列表分类
    function div_list($data, $level = 0, $controller = 'class', $extra = '', $idname = 'catid', $pidname = 'upid', $name = 'name', $fchildrens = 'children') {
        if (!is_array($data)) {
            return '';
        }
        if (0 == $level) {
            $data = isset($data['tree']) ? $data['tree'] : $data;
        }

        $string = '';
        foreach ($data as $row) {
            if (0 == $row[$pidname]) {
                $level = 0;
            }
            $string .= '<li style="height:30px;">';
            if ($level == 0 || (isset($row['channel']) && $row['channel'])) {
                $string .= "<span style='font-weight:bold;'>" . str_repeat('&nbsp;&nbsp;', $level * 3);
            } else {
                $string .= '<span>' . str_repeat('&nbsp;&nbsp;', $level * 3);
            }
            $string .= $row[$name] . '</span>' . $this->_option_href($row[$idname], $row[$name], $controller, $extra, $row[$pidname]) . '</li>';
            if (isset($row[$fchildrens])) {
                $string .= $this->div_list($row[$fchildrens], $level + 1, $controller, $extra, $idname, $pidname, $name, $fchildrens);
            }
        }
        return $string;
    }


//SELECT下选分类 channel不可选
    function select_list($data, $idarr, $level = 0, $idname = 'catid', $pidname = 'upid', $name = 'name', $fchildrens = 'children') {
        if (!is_array($data)) {
            return '';
        }
        if (0 == $level) {
            $data = isset($data['tree']) ? $data['tree'] : $data;
        }
        $idarr = (array)$idarr;
        $string = '';
        foreach ($data as $row) {
            if ($row[$pidname] == 0) {
                $level = 0;
            }
            if (isset($row['channel']) && $row['channel']) {
                $string .= "<optgroup label=\"" . str_repeat('&nbsp;&nbsp;', $level) . "{$row[$name]}\"></optgroup>\n";
            } else {
                $selected = '';
                if (in_array($row[$idname], $idarr)) {
                    $selected = 'selected';
                }
                $string .= '<option value="' . $row[$idname] . '" ' . $selected . '>' . str_repeat('&nbsp;&nbsp;', $level) . $row[$name] . '</option>' . "\n";
            }
            if (isset($row[$fchildrens])) {
                $string .= $this->select_list($row[$fchildrens], $idarr, $level + 1, $idname, $pidname, $name, $fchildrens);
            }
        }
        return $string;
    }

//SELECT下选分类2 全部可选
    function select_list_one($data, $idarr, $level = 0, $idname = 'catid', $pidname = 'upid', $name = 'name', $fchildrens = 'children') {
        if (!is_array($data)) {
            return '';
        }
        if (0 == $level) {
            $data = isset($data['tree']) ? $data['tree'] : $data;
        }
        $idarr = (array)$idarr;
        $string = '';
        foreach ($data as $row) {
            if ($row[$pidname] == 0) {
                $level = 0;
            }
            $selected = '';
            if (in_array($row[$idname], $idarr)) {
                $selected = 'selected';
            }
            $string .= '<option value="' . $row[$idname] . '" ' . $selected . '>' . str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level) . $row[$name] . '</option>' . "\n";
            if (isset($row[$fchildrens])) {
                $string .= $this->select_list_one($row[$fchildrens], $idarr, $level + 1, $idname, $pidname, $name, $fchildrens);
            }
        }
        return $string;
    }

//CHECKBOX扩展分类 多选
    function select_list_muti($data, $extrafid = '', $level = 0, $idname = 'catid', $name = 'name', $fchildrens = 'childrens') {
        if (!is_array($data)) {
            return '';
        }
        if (0 == $level) {
            $data = isset($data['tree']) ? $data['tree'] : $data;
        }
        $string = '';
        foreach ($data as $row) {
            if ($row['upid'] == 0) {
                $level = 0;
            }
            if (isset($row['channel']) && $row['ischannel']) {
                $checkstr = ' disabled="true"';
            } else {
                $checkstr = '';
            }
            if (strexists($extrafid, "'" . $row[$idname] . "'")) {
                $checkstr = 'checked = "checked"' . $checkstr;
            }
            $string .= '<div style="height:30px;"><input name="extrafid[]" type="checkbox" value="' . $row[$idname] . '" ' . $checkstr . '/> ' . str_repeat('&nbsp;&nbsp;&nbsp;', $level) . $row[$name] . '</div>' . "\n";

            if (isset($row[$fchildrens])) {
                $string .= $this->select_list_muti($row[$fchildrens], $extrafid, $level + 1, $idname, $name, $fchildrens);
            }
        }
        return $string;
    }

//分类操作链接
    function _option_href($id, $classname, $controller, $extra, $pid) {
        $options = '&nbsp;&nbsp;';
        $options .= '&nbsp;&nbsp;<a href="' . url("$controller/add/id/$id") . '" title="新增下级分类" ' . $extra . '><i class="icon-plus"></i>新增</a>';
        $options .= ' <a href="' . url("{$controller}/edit/id/{$id}") . '" title="编辑该分类" ' . $extra . '><i class="icon-edit"></i>编辑</a> ';
        if ($pid) {
            $options .= ' <a href="' . url("{$controller}/del/id/{$id}") . '" onclick="return theList.confirm(this,\'确认认删除该分类吗？分类名称: ‘' . $classname . '’\')" title="删除该分类">删除</a>';
        }
        return $options;
    }

//分类操作链接
    function _post_href($id, $name, $ischannel) {
        if ($ischannel) {
            $options = '&nbsp;&nbsp;';
        } else {
            $options = '&nbsp;&nbsp;<a href="' . url("node/create/catid/{$id}") . '" title="' . $name . '">点击发布</a>';
        }
        return $options;
    }

//DIV帮助中心
    function div_faqs($data, $level = 0, $idname = 'id', $name = 'title', $fchildrens = 'children') {
        foreach ($data as $row) {
            if (0 == $row['fpid']) {
                $level = 0;
            }
            $string = str_repeat('|--', $level) . '<img src="static/img/icons/bullet.gif"/>(ID:' . $row[$idname] . ') ' . $row[$name] . $this->_faq_href($row[$idname], $row[$name]) . '<br/>';
            echo $string;
            if (isset($row[$fchildrens])) {
                $level += 1;
                $this->div_faqs($row[$fchildrens], $level);
            }
        }
    }

//SELECT帮助中心下拉
    function select_faqs($data, $fpid = 0, $level = 0, $idname = 'id', $name = 'title', $fchildrens = 'children') {
        if (!is_array($data)) {
            return '';
        }
        foreach ($data as $row) {
            if (0 == $row['fpid']) {
                $level = 0;
            }
            $selected = '';
            if ($row[$idname] == $fpid) {
                $selected = 'selected';
            }
            $string = '<option value="' . $row[$idname] . '" ' . $selected . '>' . str_repeat('|--', $level) . $row[$name] . '</option>' . "\n";
            echo $string;
            if (isset($row[$fchildrens])) {
                $level += 1;
                $this->select_faqs($row[$fchildrens], $fpid, $level);
            }
        }
    }

    //帮助中心操作链接
    function _faq_href($id, $classname) {
        $options = '&nbsp;&nbsp;<a href="' . url("faq/add/fpid/{$id}") . '" onclick="return theList.edit(0, this, \'info\')" title="新增下级">新增</a>';
        $options .= ' <a href="' . url("faq/edit/id/$id") . '" onclick="return theList.edit(0, this, \'info\')" title="编辑">编辑</a> ';
        $options .= ' <a href="' . url("faq/remove/id/{$id}") . '" onclick="return theList.confirm(this, \'确认认删除吗？标题: ‘' . $classname . '’\')" title="删除">删除</a>';
        return $options;
    }

}