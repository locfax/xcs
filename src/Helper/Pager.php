<?php

namespace Xcs\Helper;

class Pager {

    /**
     * @param $pageinfo
     * @return string
     */
    public static function pagebar($pageinfo) {
        $totals = $pageinfo['totals'];
        $perpage = $pageinfo['length'];
        $curpage = $pageinfo['curpage'];
        $mpurl = url($pageinfo['udi']);
        if (isset($pageinfo['param'])) {
            $mpurl .= $pageinfo['param'];
        }
        $maxpages = isset($pageinfo['maxpages']) ? $pageinfo['maxpages'] : false; //最大页数限制
        $page = isset($pageinfo['showpage']) ? $pageinfo['showpage'] : false; //一次显示多少页码
        $shownum = isset($pageinfo['shownum']) ? $pageinfo['shownum'] : false;
        $showkbd = isset($pageinfo['showkbd']) ? $pageinfo['showkbd'] : false;
        $simple = isset($pageinfo['simple']) ? $pageinfo['simple'] : false;
        $autogoto = true;
        $ajaxtarget = getgpc('g.ajaxtarget') ? " ajaxtarget=\"" . getgpc('g.ajaxtarget', '', 'input_char') . "\" " : '';
        $aname = '';
        if (strexists($mpurl, '#')) {
            $astrs = explode('#', $mpurl);
            $mpurl = $astrs[0];
            $aname = '#' . $astrs[1];
        }
        $lang['prev'] = '上一页';
        $lang['next'] = '下一页';
        $mpurl .= strexists($mpurl, '?') ? '&' : '?';
        $offset = floor($page * 0.5);
        $realpages = ceil($totals / $perpage);
        $pages = $maxpages && $maxpages < $realpages ? $maxpages : $realpages;
        if ($page > $pages) {
            $from = 1;
            $to = $pages;
        } else {
            $from = $curpage - $offset;
            $to = $from + $page - 1;
            if ($from < 1) {
                $to = $curpage + 1 - $from;
                $from = 1;
                if ($to - $from < $page) {
                    $to = $page;
                }
            } elseif ($to > $pages) {
                $from = $pages - $page + 1;
                $to = $pages;
            }
        }
        $multipage = ($curpage - $offset > 1 && $pages > $page ? '<a href="' . $mpurl . 'page=1' . $aname . '" class="first"' . $ajaxtarget . '>1 ...</a>' : '') .
            ($curpage > 1 && !$simple ? '<a href="' . $mpurl . 'page=' . ($curpage - 1) . $aname . '" class="prev"' . $ajaxtarget . '>' . $lang['prev'] . '</a>' : '');
        for ($i = $from; $i <= $to; $i++) {
            $multipage .= $i == $curpage ? '<strong>' . $i . '</strong>' :
                '<a href="' . $mpurl . 'page=' . $i . ($ajaxtarget && $i == $pages && $autogoto ? '#' : $aname) . '"' . $ajaxtarget . '>' . $i . '</a>';
        }
        $multipage .= ($to < $pages ? '<a href="' . $mpurl . 'page=' . $pages . $aname . '" class="last"' . $ajaxtarget . '>... ' . $realpages . '</a>' : '') .
            ($curpage < $pages && !$simple ? '<a href="' . $mpurl . 'page=' . ($curpage + 1) . $aname . '" class="nxt"' . $ajaxtarget . '>' . $lang['next'] . '</a>' : '') .
            ($showkbd && !$simple && $pages > $page && !$ajaxtarget ? '<kbd><input type="text" name="custompage" size="3" onkeydown="if(event.keyCode==13) {window.location=\'' . $mpurl . 'page=\'+this.value; doane(event);}" /></kbd>' : '');
        $multipage = '<div class="pg">' . ($shownum && !$simple ? '<em>&nbsp;' . $totals . '&nbsp;</em>' : '') . $multipage . '</div>';
        //$maxpage = $realpages;
        return $multipage;
    }

    /**
     * @param $pageinfo
     * @return string
     */
    public static function simplepage($pageinfo) {
        //<ul class='pager'>
        //<li class="previous"><a href="{SITEPATH}list/分享发现/page1/">上一页</a></li><li class="pager-nums">2 / 4</li><li class='next'><a href='{SITEPATH}list/分享发现/lastest/page3/'>下一页</a></li>
        //</ul>
        $totals = $pageinfo['totals'];
        $perpage = $pageinfo['length'];
        $curpage = $pageinfo['curpage'];
        $mpurl = $pageinfo['udi'];
        $return = "<ul class='pager'>";
        $lang['next'] = '下一页';
        $lang['prev'] = '上一页';
        $realpages = ceil($totals / $perpage);
        if ($curpage > $realpages) {
            $curpage = $realpages;
        }
        $prev = $curpage > 1 ? '<li class="previous"><a href="' . $mpurl . 'page' . ($curpage - 1) . '.htm">' . $lang['prev'] . '</a></li>' : '';
        $next = $curpage < $realpages ? "<li class='next'><a href=\"" . $mpurl . 'page' . ($curpage + 1) . '.htm">' . $lang['next'] . '</a></li>' : '';
        $pagenum = "<li class=\"pager-nums\">{$curpage} / {$realpages}</li>";
        if ($next || $prev) {
            $return .= $prev . $pagenum . $next;
        } else {
            $return .= $pagenum;
        }
        $return .= "</ul>";
        return $return;
    }

}
