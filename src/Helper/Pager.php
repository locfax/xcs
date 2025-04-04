<?php

namespace Xcs\Helper;

class Pager
{
    /**
     * @param array $pageInfo
     * @return string
     */
    public static function pageBar(array $pageInfo): string
    {
        $totals = $pageInfo['total'];
        $perPage = $pageInfo['length'];
        $curPage = $pageInfo['page'];
        $mpUrl = $pageInfo['udi'];
        if (isset($pageInfo['param'])) {
            if (self::strPos($mpUrl, '?')) {
                $mpUrl .= $pageInfo['param'];
            } else {
                $mpUrl .= '?' . $pageInfo['param'];
            }
        }
        $maxPages = $pageInfo['maxPages'] ?? 100; //最大页数限制
        $page = $pageInfo['showPage'] ?? 10; //一次显示多少页码
        $showNum = $pageInfo['showNum'] ?? true;
        $showKbd = $pageInfo['showKbd'] ?? true;
        $simple = $pageInfo['simple'] ?? false;
        $autoGoto = true;
        $ajaxTarget = getgpc('g.target') ? " target=\"" . getgpc('g.target', '', 'char_output') . "\" " : '';
        $hrefName = '';
        if (self::strPos($mpUrl, '#')) {
            $asTrs = explode('#', $mpUrl);
            $mpUrl = $asTrs[0];
            $hrefName = '#' . $asTrs[1];
        }
        $lang['prev'] = '上一页';
        $lang['next'] = '下一页';
        $mpUrl .= self::strPos($mpUrl, '?') ? '&' : '?';
        $offset = floor($page * 0.5);
        $realPages = ceil($totals / $perPage);
        $pages = $maxPages && $maxPages < $realPages ? $maxPages : $realPages;
        if ($page > $pages) {
            $from = 1;
            $to = $pages;
        } else {
            $from = $curPage - $offset;
            $to = $from + $page - 1;
            if ($from < 1) {
                $to = $curPage + 1 - $from;
                $from = 1;
                if ($to - $from < $page) {
                    $to = $page;
                }
            } elseif ($to > $pages) {
                $from = $pages - $page + 1;
                $to = $pages;
            }
        }

        $multiPage = ($curPage > 0 && $pages > $page ? '<a href="' . $mpUrl . 'page=1' . $hrefName . '" class="first"' . $ajaxTarget . '>1 ...</a>' : '') .
            ($curPage > 0 && !$simple ? '<a href="' . $mpUrl . 'page=' . ($curPage - 1 > 0 ? $curPage - 1 : 1) . $hrefName . '" class="prev"' . $ajaxTarget . '>' . $lang['prev'] . '</a>' : '');
        for ($i = $from; $i <= $to; $i++) {
            $multiPage .= $i == $curPage ? '<strong>' . $i . '</strong>' :
                '<a href="' . $mpUrl . 'page=' . $i . ($ajaxTarget && $i == $pages && $autoGoto ? '#' : $hrefName) . '"' . $ajaxTarget . '>' . $i . '</a>';
        }
        $multiPage .= ($to < $pages ? '<a href="' . $mpUrl . 'page=' . $pages . $hrefName . '" class="last"' . $ajaxTarget . '>... ' . $realPages . '</a>' : '') .
            ($curPage < $pages && !$simple ? '<a href="' . $mpUrl . 'page=' . ($curPage + 1) . $hrefName . '" class="nxt"' . $ajaxTarget . '>' . $lang['next'] . '</a>' : '') .
            ($showKbd && !$simple && $pages > $page && !$ajaxTarget ? '<kbd><input type="text" name="custompage" size="3" onkeydown="if(KeyboardEvent.keyCode===13) {window.location=\'' . $mpUrl . 'page=\'+this.value; doane(Event);}" /></kbd>' : '');
        return '<div class="pg">' . ($showNum && !$simple ? '<em>&nbsp;' . $totals . '&nbsp;</em>' : '') . $multiPage . '</div>';
    }

    /**
     * @param array $pageInfo
     * @return string
     */
    public static function simplePage(array $pageInfo): string
    {
        $totals = $pageInfo['total'];
        $perPage = $pageInfo['length'];
        $curPage = $pageInfo['page'];
        $mpUrl = $pageInfo['udi'];
        $return = "<ul class='pager'>";
        $lang['next'] = '下一页';
        $lang['prev'] = '上一页';
        $realPages = ceil($totals / $perPage);

        $curPage = $pageInfo['maxpages'] ? max(1, min($curPage, $realPages, $pageInfo['maxpages'])) : max(1, min($curPage, $realPages));

        $prev = $curPage > 1 ? '<li class="previous"><a href="' . $mpUrl . '?page=' . ($curPage - 1) . '">' . $lang['prev'] . '</a></li>' : '';
        $next = $curPage < $realPages ? "<li class='next'><a href=\"" . $mpUrl . '?page=' . ($curPage + 1) . '">' . $lang['next'] . '</a></li>' : '';
        $pageNum = "<li class=\"pager-nums\">{$curPage} / {$realPages}</li>";
        if ($next || $prev) {
            $return .= $prev . $pageNum . $next;
        } else {
            $return .= $pageNum;
        }
        $return .= "</ul>";
        return $return;
    }

    /**
     * @param string $str
     * @param string $needle
     * @return bool
     */
    private static function strPos(string $str, string $needle): bool
    {
        return str_contains($str, $needle);
    }
}
