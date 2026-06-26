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
        $mpUrl = $pageInfo['udi'];
        if (isset($pageInfo['param'])) {
            if (str_contains($mpUrl, '?')) {
                $mpUrl .= $pageInfo['param'];
            } else {
                $mpUrl .= '?' . $pageInfo['param'];
            }
        }
        $autoGoto = true;
        $ajaxTarget = getgpc('g.target') ? " target=\"" . getgpc('g.target', '', 'char_output') . "\" " : '';
        $hrefName = '';
        if (str_contains($mpUrl, '#')) {
            $asTrs = explode('#', $mpUrl);
            $mpUrl = $asTrs[0];
            $hrefName = '#' . $asTrs[1];
        }
        $mpUrl .= str_contains($mpUrl, '?') ? '&' : '?';
        $offset = floor($pageInfo['showPage'] * 0.5);

        if ($pageInfo['showPage'] > $pageInfo['pages']) {
            $from = 1;
            $to = $pageInfo['pages'];
        } else {
            $from = $pageInfo['page'] - $offset;
            $to = $from + $pageInfo['showPage'] - 1;
            if ($from < 1) {
                $to = $pageInfo['page'] + 1 - $from;
                $from = 1;
                if ($to - $from < $pageInfo['showPage']) {
                    $to = $pageInfo['showPage'];
                }
            } elseif ($to > $pageInfo['pages']) {
                $from = $pageInfo['pages'] - $pageInfo['showPage'] + 1;
                $to = $pageInfo['pages'];
            }
        }

        $multiPage = ('<a href="' . $mpUrl . 'page=1' . $hrefName . '" class="first"' . $ajaxTarget . '>首页</a>') .
            ('<a href="' . $mpUrl . 'page=' . ($pageInfo['page'] - 1 > 0 ? $pageInfo['page'] - 1 : 1) . $hrefName . '" class="prev"' . $ajaxTarget . '>上一页</a>');
        for ($i = $from; $i <= $to; $i++) {
            $multiPage .= $i == $pageInfo['page'] ? '<a href="javascript:;" class="active">' . $i . '</a>' :
                '<a href="' . $mpUrl . 'page=' . $i . ($ajaxTarget && $i == $pageInfo['pages'] && $autoGoto ? '#' : $hrefName) . '"' . $ajaxTarget . ' class="hidden-xs">' . $i . '</a>';
        }
        $nextPage = min($pageInfo['page'] + 1, $pageInfo['pages']);
        $multiPage .= ('<a href="' . $mpUrl . 'page=' . $nextPage . $hrefName . '" class="nxt"' . $ajaxTarget . '>下一页</a>');
        $multiPage .= ('<a href="' . $mpUrl . 'page=' . $pageInfo['pages'] . $hrefName . '" class="last"' . $ajaxTarget . ' title="尾页">' . $pageInfo['page'] . '/' . $pageInfo['pages'] . '</a>');
        if (isset($pageInfo['showNum']) && $pageInfo['showNum']) {
            $multiPage .= ' <em>' . $pageInfo['total'] . '</em>';
        }
        return '<div class="pg">' . $multiPage . '</div>';
    }

}
