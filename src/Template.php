<?php

namespace Xcs;

class Template
{
    private array $replaceCode = ['search' => [], 'replace' => []];
    private array $language = [];
    private string $tplDir = '';

    public function parse($cacheDir, $tplDir, $tplFile, $cacheFile, $file, $compress = true): void
    {
        $this->tplDir = $tplDir;

        $fp = fopen($this->tplDir . $tplFile, 'r');
        if (!$fp) {
            return;
        }
        $template = fread($fp, filesize($this->tplDir . $tplFile));
        fclose($fp);
        $var_regexp = "((\\\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*(\-\>)?[a-zA-Z0-9_\x7f-\xff]*)(\[[a-zA-Z0-9_\-\.\"\'\[\]\$\x7f-\xff]+\])*)";
        $const_regexp = "([A-Z_\x7f-\xff][A-Z0-9_\x7f-\xff]*)";

        for ($i = 1; $i <= 10; $i++) {
            if (str_contains($template, '{subtemplate')) {
                $template = preg_replace_callback("/[\n\r\t]*(\<\!\-\-)?\{subtemplate\s+([a-z\d_:\/]+)\}(\-\-\>)?[\n\r\t]*/", [$this, 'tag_subTemplate'], $template);
            }
        }
        $template = preg_replace("/([\n\r]+)\t+/s", "\\1", $template);
        $template = preg_replace("/\<\!\-\-\{(.+?)\}\-\-\>/s", "{\\1}", $template);
        $template = preg_replace_callback("/\{lang\s+(.+?)\}/", [$this, 'language_tags'], $template);

        $template = preg_replace_callback("/[\n\r\t]*\{url\s+(.+?)\}[\n\r\t]*/", [$this, 'url_tags'], $template);
        $template = preg_replace_callback("/[\n\r\t]*\{surl\s+(.+?)\}[\n\r\t]*/", [$this, 'surl_tags'], $template);
        $template = preg_replace_callback("/[\n\r\t]*\{config\s+(.+?)\}[\n\r\t]*/", [$this, 'config_tags'], $template);

        $template = preg_replace_callback("/[\n\r\t]*\{script\s+(.+?)\}[\n\r\t]*/", [$this, 'script_tags'], $template);

        $template = preg_replace_callback("/[\n\r\t]*\{date\s+(.+?)\|(.*?)\}[\n\r\t]*/", [$this, 'date_tags'], $template);
        $template = preg_replace_callback("/[\n\r\t]*\{date\s+(.+?)\}[\n\r\t]*/", [$this, 'date_tags'], $template);

        $template = preg_replace_callback("/[\n\r\t]*\{=(.+?)\((.+?)\)\}[\n\r\t]*/", [$this, 'function_tags'], $template);
        $template = preg_replace_callback("/[\n\r\t]*\{=(.+?)\(\)\}[\n\r\t]*/", [$this, 'function_tags'], $template);
        $template = preg_replace_callback("/[\n\r\t]*\{eval\s+(.+?)\s*\}[\n\r\t]*/s", [$this, 'eval_tags'], $template);

        $template = str_replace("{LF}", PHP_EOL, $template);

        $template = preg_replace_callback("/\{\\\$([\w\d_]+\.[\w\d_]+)\}/s", [$this, 'add_quote_exp'], $template);
        $template = preg_replace("/\{(\\\$[\w\d_\-\>\[\]\'\"\$\.\x7f-\xff]+)\}/s", "<?=\\1?>", $template);
        $template = preg_replace_callback("/$var_regexp/s", [$this, 'add_quote'], $template);
        $template = preg_replace_callback("/\<\?\=\<\?\=$var_regexp\?\>\?\>/s", [$this, 'add_quote'], $template);

        $template = preg_replace_callback("/[\n\r\t]*\{template\s+([a-z\d_:\/]+)\}[\n\r\t]*/", [$this, 'tag_template'], $template);
        $template = preg_replace_callback("/[\n\r\t]*\{echo\s+(.+?)\}[\n\r\t]*/", [$this, 'tag_echo'], $template);
        $template = preg_replace_callback("/[\n\r\t]*\{=(.+?)\}[\n\r\t]*/", [$this, 'tag_echo'], $template);

        $template = preg_replace_callback("/([\n\r\t]*)\{if\s+(.+?)\}([\n\r\t]*)/s", [$this, 'tag_if'], $template);
        $template = preg_replace_callback("/([\n\r\t]*)\{elseif\s+(.+?)\}([\n\r\t]*)/s", [$this, 'tag_elseif'], $template);
        $template = preg_replace("/\{else\}/i", "<?php } else { ?>", $template);
        $template = preg_replace("/\{\/if\}/i", "<?php } ?>", $template);

        $template = preg_replace_callback("/[\n\r\t]*\{loop\s+(\S+)\s+(\S+)\}[\n\r\t]*/s", [$this, 'tag_loop'], $template);
        $template = preg_replace_callback("/[\n\r\t]*\{loop\s+(\S+)\s+(\S+)\s+(\S+)\}[\n\r\t]*/s", [$this, 'tag_loop_as'], $template);
        $template = preg_replace("/\{\/loop\}/i", "<?php } } ?>", $template);

        $template = preg_replace("/\{$const_regexp\}/s", "<?=\\1?>", $template);

        if (!empty($this->replaceCode)) {
            $template = str_replace($this->replaceCode['search'], $this->replaceCode['replace'], $template);
        }

        $template = preg_replace("/ \?\>[\n\r]*\<\? /s", " ", $template);
        $template = preg_replace("/ \?\>[\n\r]*\<\?php /s", " ", $template);

        $template = preg_replace_callback("/\"(http)?[\w\.\/:]+\?[^\"]+?&[^\"]+?\"/", [$this, 'trans_amp'], $template);
        $template = preg_replace_callback("/[\n\r\t]*\{block\s+([\w\d_\[\]]+)\}(.+?)\{\/block\}/s", [$this, 'strip_block'], $template);

        $template = preg_replace("/\<\?(\s{1})/is", "<?php\\1", $template);
        $template = preg_replace("/\<\?\=(.+?)\?\>/is", "<?php echo \\1;?>", $template);

        if ($compress && getini('compress_html')) {
            $template = $this->compress_html($template);
        }

        if ($compress && getini('noise_label')) {
            $template = $this->noise_label($template);
        }

        $this->save($cacheDir . $cacheFile, $template, FILE_READ_MODE);
    }

    /**
     * @param string $filename
     * @param string $content
     * @param mixed $mode
     * @return void
     */
    private function save(string $filename, string $content, $mode): void
    {
        if (!is_file($filename)) {
            file_exists($filename) && unlink($filename);
            touch($filename) && chmod($filename, FILE_READ_MODE); //全读写
        }
        $ret = file_put_contents($filename, $content, LOCK_EX);

        if ($ret && FILE_READ_MODE != $mode) {
            chmod($filename, $mode);
        }
    }

    /**
     * @param array $_var
     * @return mixed|string
     */
    private function language_tags(array $_var)
    {
        $vars = explode(':', $_var[1]);
        $isPlugin = count($vars) == 2;
        if (!$isPlugin) {
            if (!isset($this->language['inner'])) {
                $this->language['inner'] = [];
            }
        } else {
            if (!isset($this->language['plugin'])) {
                $this->language['plugin'] = [];
            }
        }
        if (!$isPlugin && !isset($this->language['inner'][$vars[0]])) {
            $lang = include THEMES_LANG . getini('site/lang') . '/template.php';
            $this->language['inner'] = array_merge($this->language['inner'], $lang);
        }
        if ($isPlugin && !isset($this->language['plugin'][$vars[0]][$vars[1]])) {
            $this->language['plugin'][$vars[0]] = include THEMES_LANG . getini('site/lang') . '/' . $vars[0] . '.php';
        }

        if (!$isPlugin && isset($this->language['inner'][$vars[0]])) {
            return $this->language['inner'][$vars[0]];
        } elseif ($isPlugin && isset($this->language['plugin'][$vars[0]][$vars[1]])) {
            return $this->language['plugin'][$vars[0]][$vars[1]];
        } else {
            return $isPlugin ? '!' . $vars[1] . '!' : '!' . $vars[0] . '!';
        }
    }

    /**
     * @param array $parameter
     * @return string
     */
    private function url_tags(array $parameter): string
    {
        $i = count($this->replaceCode['search']);
        $this->replaceCode['search'][$i] = $search = "<!--URL_TAG_$i-->";
        $this->replaceCode['replace'][$i] = "<?php echo url(\"$parameter[1]\"); ?>";
        return $search;
    }

    /**
     * @param array $parameter
     * @return string
     */
    private function surl_tags(array $parameter): string
    {
        $i = count($this->replaceCode['search']);
        $this->replaceCode['search'][$i] = $search = "<!--SURL_TAG_$i-->";
        $this->replaceCode['replace'][$i] = url("$parameter[1]");
        return $search;
    }

    /**
     * @param array $parameter
     * @return string
     */
    private function script_tags(array $parameter): string
    {
        $tplFile = template($parameter[1], [], true);
        return implode('', file($this->tplDir . $tplFile));
    }

    /**
     * @param array $parameter
     * @return string
     */
    private function date_tags(array $parameter): string
    {
        $i = count($this->replaceCode['search']);
        if (!isset($parameter[2])) {
            $this->replaceCode['search'][$i] = $search = "<!--DATE_TAG_$i-->";
            $this->replaceCode['replace'][$i] = "<?php echo dgmdate($parameter[1],'dt'); ?>";
        } else {
            $this->replaceCode['search'][$i] = $search = "<!--DATE_TAG2_$i-->";
            $this->replaceCode['replace'][$i] = "<?php echo dgmdate($parameter[1], $parameter[2]); ?>";
        }
        return $search;
    }

    /**
     * @param array $parameter
     * @return string
     */
    private function function_tags(array $parameter): string
    {
        $i = count($this->replaceCode['search']);
        if (!isset($parameter[2])) {
            $this->replaceCode['search'][$i] = $search = "<!--FUNCTION_TAG_$i-->";
            $this->replaceCode['replace'][$i] = "<?php echo $parameter[1](); ?>";
        } else {
            $this->replaceCode['search'][$i] = $search = "<!--FUNCTION_TAG2_$i-->";
            $this->replaceCode['replace'][$i] = "<?php echo $parameter[1]($parameter[2]); ?>";
        }
        return $search;
    }

    /**
     * @param array $php
     * @return string
     */
    private function eval_tags(array $php): string
    {
        $php = str_replace('\"', '"', $php[1]);
        $i = count($this->replaceCode['search']);
        $this->replaceCode['search'][$i] = $search = "<!--EVAL_TAG_$i-->";
        $this->replaceCode['replace'][$i] = "<?php $php ?>";
        return $search;
    }

    /**
     * @param array $parameter
     * @return string
     */
    private function config_tags(array $parameter): string
    {
        $i = count($this->replaceCode['search']);
        $this->replaceCode['search'][$i] = $search = "<!--CONFIG_TAG_$i-->";
        $this->replaceCode['replace'][$i] = (null != getini("settings/$parameter[1]")) ? getini("settings/$parameter[1]") : getini("$parameter[1]");
        return $search;
    }

    /**
     * @param array $file
     * @return string
     */
    private function tag_subTemplate(array $file): string
    {
        $tplFile = template($file[2], [], true);
        $content = implode('', file($this->tplDir . $tplFile));
        if ($content) {
            return $content;
        } else {
            return '<!--1 ' . $file[2] . ' 1-->';
        }
    }

    /**
     * @param array $parameter
     * @return string
     */
    private function tag_template(array $parameter): string
    {
        $return = "<?php template(\"$parameter[1]\"); ?>";
        return $this->strip_tags($return);
    }

    /**
     * @param array $parameter
     * @return string
     */
    private function tag_echo(array $parameter): string
    {
        $return = "<?php echo $parameter[1]; ?>";
        return $this->strip_tags($return);
    }

    /**
     * @param array $parameter
     * @return string
     */
    private function tag_if(array $parameter): string
    {
        $return = "$parameter[1]<?php if($parameter[2]) { ?>$parameter[3]";
        return $this->strip_tags($return);
    }

    /**
     * @param array $parameter
     * @return string
     */
    private function tag_elseif(array $parameter): string
    {
        $return = "$parameter[1]<?php }elseif($parameter[2]) { ?>$parameter[3]";
        return $this->strip_tags($return);
    }

    /**
     * @param array $parameter
     * @return string
     */
    private function tag_loop(array $parameter): string
    {
        $return = "<?php if(!empty($parameter[1])){ foreach($parameter[1] as $parameter[2]) { ?>";
        return $this->strip_tags($return);
    }

    /**
     * @param array $parameter
     * @return string
     */
    private function tag_loop_as(array $parameter): string
    {
        $return = "<?php if(!empty($parameter[1])){ foreach($parameter[1] as $parameter[2] => $parameter[3]) { ?>";
        return $this->strip_tags($return);
    }

    /**
     * @param array $str
     * @return array|string
     */
    private function trans_amp(array $str)
    {
        $str = $str[0];
        $str = str_replace('&amp;amp;', '&amp;', $str);
        return str_replace('\"', '"', $str);
    }

    /**
     * @param array $var
     * @return array|string|null
     */
    private function add_quote(array $var)
    {
        $var = '<?=' . $var[1] . '?>';
        return str_replace("\\\"", "\"", preg_replace("/\[([\w\d_\-\.\x7f-\xff]+)\]/s", "['\\1']", $var));
    }

    /**
     * @param array $var
     * @return string
     */
    private function add_quote_exp(array $var): string
    {
        $vars = explode('.', $var[1]);
        $var = array_shift($vars);
        return "<?=\${$var}[{$vars[0]}]?>";
    }

    /**
     * @param mixed $expr
     * @param string $statement
     * @return string
     */
    private function strip_tags($expr, string $statement = ''): string
    {
        $expr = str_replace("\\\"", "\"", preg_replace("/\<\?\=(\\\$.+?)\?\>/s", "\\1", $expr));
        $statement = str_replace("\\\"", "\"", $statement);
        return $expr . $statement;
    }

    /**
     * @param array $parameter
     * @return string
     */
    private function strip_block(array $parameter): string
    {
        $var = $parameter[1];
        $s = $parameter[2];
        $s = str_replace('\\"', '"', $s);
        $s = preg_replace("/<\?=\\\$(.+?)\?>/", "{\$\\1}", $s);
        preg_match_all("/<\?=(.+?)\?>/e", $s, $cometary);
        $constAdd = '';
        $cometary[1] = array_unique($cometary[1]);
        foreach ($cometary[1] as $const) {
            $constAdd .= '$__' . $const . ' = ' . $const . ';';
        }
        $s = preg_replace("/<\?=(.+?)\?>/", "{\$__\\1}", $s);
        $s = str_replace('?>', PHP_EOL . "\$$var .= <<<EOF" . PHP_EOL, $s);
        $s = str_replace('<?', PHP_EOL . 'EOF;' . PHP_EOL, $s);
        $s = str_replace("\nphp ", PHP_EOL, $s);
        return '<?' . PHP_EOL . "$constAdd\$$var = <<<EOF" . PHP_EOL . $s . PHP_EOL . 'EOF;' . PHP_EOL . '?>';
    }

    /**
     * 压缩html : 清除换行符,清除制表符,去掉注释标记
     * @param $string
     * @return压缩后的$string
     * */
    public function compress_html($html)
    {
        // 保护 pre/textarea 里的内容
        $pattern = '/<(pre|textarea|script)\b[^>]*>.*?<\/\1>/is';
        preg_match_all($pattern, $html, $matches);
        $placeholders = [];

        foreach ($matches[0] as $i => $match) {
            $key = "##PLACEHOLDER{$i}##";
            $placeholders[$key] = $match;
            $html = str_replace($match, $key, $html);
        }

        // 删除标签之间的空格/换行
        $html = preg_replace('/>\s+</', '><', $html);

        // 压缩多余空格和注释
        $html = preg_replace([
            '/\s{2,}/',                 // 多余空格压缩成一个
            '/<!--(?!\[if).*?-->/s'     // 删除注释（保留条件注释）
        ], [
            ' ',
            ''
        ], $html);

        // 压缩内联 CSS
        $html = preg_replace_callback(
            '/<style\b[^>]*>(.*?)<\/style>/is',
            function ($matches) {
                $css = $matches[1];
                $css = preg_replace('!/\*.*?\*/!s', '', $css); // 去掉注释
                $css = preg_replace('/\s*([{}:;,])\s*/', '$1', $css);
                $css = preg_replace('/\s+/', ' ', $css);
                return "<style>{$css}</style>";
            },
            $html
        );

        // 压缩内联 JS
        $html = preg_replace_callback(
            '/<script\b[^>]*>(.*?)<\/script>/is',
            function ($matches) {
                $js = $matches[1];
                if (trim($js) === '') return $matches[0];
                $js = preg_replace('!/\*.*?\*/!s', '', $js);  // 多行注释
                $js = preg_replace('/\/\/[^\n\r]*/', '', $js); // 单行注释
                $js = preg_replace('/\s*([{}():;,+<>=&|-])\s*/', '$1', $js);
                $js = preg_replace('/\s+/', ' ', $js);
                return "<script>{$js}</script>";
            },
            $html
        );

        // 恢复 pre/textarea/script 的原始内容
        $html = str_replace(array_keys($placeholders), array_values($placeholders), $html);

        return $html;
    }

    private function noise_label($html)
    {

        // 1. 在 <body> 后加噪声
        $html = preg_replace('/<body(.*?)>/i', '<body${1}>' . $this->getNoiseLabel(rand(1, 3)), $html);

        // 2. 在 </div>、</p> 后随机插入噪声
        $html = preg_replace_callback('/<\/(div|p)>/i', function ($matchs) {
            if (rand(0, 1)) {
                return $matchs[0] . $this->getNoiseLabel(rand(1, 2));
            }
            return $matchs[0];
        }, $html);

        // 3. 在 class 中随机添加 class
        $html = preg_replace_callback('/class="(.*?)"/i', function ($matchs) {
            return 'class="' . $matchs[1] . ' ' . $this->random_class_name('', 8) . '"';
        }, $html);

        // 4. 随机给 div/p/li/h2 加 data 属性
        $html = preg_replace_callback('/<(div|p|li|h2)(\s|>)/i', function ($matchs) {
            $attrs = ['data-x', 'data-y', 'data-z'];
            $rn = $attrs[array_rand($attrs)];
            $rv = \Xcs\Helper\StringHelper::random(6);
            return '<' . $matchs[1] . ' ' . $rn . '="' . $rv . '"' . $matchs[2];
        }, $html);

        // 5. 随机替换标签名称
        $html = $this->random_replace_tags($html);

        return $html;
    }

    private function getNoiseLabel($num = 2)
    {
        $samples = [
            '<span style="display:none">%s</span>',
            '<p style="display:none">%s</p>',
            '<i style="display:none">%s</i>',
            '<em style="display:none">%s</em>',
            '<b style="display:none">%s</b>',
        ];

        shuffle($samples);
        $_samples = array_slice($samples, 0, $num);

        $inHtml = '';
        foreach ($_samples as $tpl) {
            $inHtml .= sprintf($tpl, \Xcs\Helper\StringHelper::random(rand(8, 15)));
        }

        return $inHtml;
    }

    private function random_class_name($prefix = 'rcls_', $len = 8)
    {
        return $prefix . substr(md5(uniqid((string)mt_rand(), true)), 0, $len);
    }

    /**
     * 随机替换部分标签
     */
    private function random_replace_tags($html)
    {
        $map = [
            'div' => ['section', 'article'],
            'p' => ['div', 'span'],
        ];

        foreach ($map as $src => $alts) {
            if (rand(0, 1)) { // 50% 概率替换
                $alt = $alts[array_rand($alts)];
                // 开始标签
                $html = preg_replace('/<' . $src . '\b/i', '<' . $alt, $html);
                // 结束标签
                $html = preg_replace('/<\/' . $src . '>/i', '</' . $alt . '>', $html);
            }
        }

        return $html;
    }

}
