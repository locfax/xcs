<?php

namespace Xcs;

class Template
{
    private array $subTemplates = [];
    private array $replaceCode = ['search' => [], 'replace' => []];
    private array $language = [];
    private string $tplDir = '';

    public function parse($cacheDir, $tplDir, $tplFile, $cacheFile, $file): void
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

        $this->subTemplates = [];
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

        $headerAdd = '';
        if (!empty($this->subTemplates)) {
            $first = true;
            foreach ($this->subTemplates as $fName) {
                $headerAdd .= ($first ? "0 " : PHP_EOL) . "|| checkTplRefresh('$tplFile', '$fName', " . time() . ", '$cacheFile', '$file')";
                $first = false;
            }
            $headerAdd .= ';' . PHP_EOL;
        }

        if ($headerAdd) {
            $template = "<?php " . PHP_EOL . " {$headerAdd}?>" . PHP_EOL . "$template";
        }

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

        $this->save($cacheDir . $cacheFile, $template, FILE_READ_MODE);
    }

    /**
     * @param string $filename
     * @param string $content
     * @param mixed $mode
     * @return void
     */
    private function save(string $filename, string $content, mixed $mode): void
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
    private function language_tags(array $_var): mixed
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
            $lang = include DATA_LANG . getini('site/lang') . '/template.php';
            $this->language['inner'] = array_merge($this->language['inner'], $lang);
        }
        if ($isPlugin && !isset($this->language['plugin'][$vars[0]][$vars[1]])) {
            $this->language['plugin'][$vars[0]] = include DATA_LANG . getini('site/lang') . '/' . $vars[0] . '.php';
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
            $this->subTemplates[] = $tplFile;
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
    private function trans_amp(array $str): array|string
    {
        $str = $str[0];
        $str = str_replace('&amp;amp;', '&amp;', $str);
        return str_replace('\"', '"', $str);
    }

    /**
     * @param array $var
     * @return array|string|null
     */
    private function add_quote(array $var): array|string|null
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
    private function strip_tags(mixed $expr, string $statement = ''): string
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

}
