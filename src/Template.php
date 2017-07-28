<?php

namespace Xcs;

class Template {

    private $subtemplates = array();
    private $replacecode = array('search' => array(), 'replace' => array());
    private $blocks = array();
    private $language = array();
    private $tpldir = '';
    private $cachedir = '';
    private $file = '';

    public function parse($cachedir, $tpldir, $tplfile, $cachefile, $file) {
        $this->file = $file;
        $this->tpldir = $tpldir;
        $this->cachedir = $cachedir;

        $fp = fopen($this->tpldir . $tplfile, 'r');
        if (!$fp) {
            return;
        }
        $template = fread($fp, filesize($this->tpldir . $tplfile));
        fclose($fp);
        $var_regexp = "((\\\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*(\-\>)?[a-zA-Z0-9_\x7f-\xff]*)(\[[a-zA-Z0-9_\-\.\"\'\[\]\$\x7f-\xff]+\])*)";
        $const_regexp = "([A-Z_\x7f-\xff][A-Z0-9_\x7f-\xff]*)";

        $this->subtemplates = array();
        for ($i = 1; $i <= 5; $i++) {
            if (\Xcs\Util::strpos($template, '{subtemplate')) {
                $template = preg_replace_callback("/[\n\r\t]*(\<\!\-\-)?\{subtemplate\s+([a-z0-9_:\/]+)\}(\-\-\>)?[\n\r\t]*/", array($this, 'tag_subtemplate'), $template);
            }
        }
        $template = preg_replace("/([\n\r]+)\t+/s", "\\1", $template);
        $template = preg_replace("/\<\!\-\-\{(.+?)\}\-\-\>/s", "{\\1}", $template);
        $template = preg_replace_callback("/\{lang\s+(.+?)\}/", array($this, 'language_tags'), $template);

        $template = preg_replace_callback("/[\n\r\t]*\{url\s+(.+?)\}[\n\r\t]*/", array($this, 'url_tags'), $template);
        $template = preg_replace_callback("/[\n\r\t]*\{surl\s+(.+?)\}[\n\r\t]*/", array($this, 'surl_tags'), $template);
        $template = preg_replace_callback("/[\n\r\t]*\{config\s+(.+?)\}[\n\r\t]*/", array($this, 'config_tags'), $template);

        $template = preg_replace_callback("/[\n\r\t]*\{ad\s+(.+?)\/(.+?)\}[\n\r\t]*/", array($this, 'adv_tags'), $template);
        $template = preg_replace_callback("/[\n\r\t]*\{ad\s+(.+?)\}[\n\r\t]*/", array($this, 'adv_tags'), $template);

        $template = preg_replace_callback("/[\n\r\t]*\{script\s+(.+?)\}[\n\r\t]*/", array($this, 'script_tags'), $template);

        $template = preg_replace_callback("/[\n\r\t]*\{date\s+(.+?)\|(.*?)\}[\n\r\t]*/", array($this, 'date_tags'), $template);
        $template = preg_replace_callback("/[\n\r\t]*\{date\s+(.+?)\}[\n\r\t]*/", array($this, 'date_tags'), $template);

        $template = preg_replace_callback("/[\n\r\t]*\{=(.+?)\((.+?)\)\}[\n\r\t]*/", array($this, 'function_tags'), $template);
        $template = preg_replace_callback("/[\n\r\t]*\{=(.+?)\(\)\}[\n\r\t]*/", array($this, 'function_tags'), $template);
        $template = preg_replace_callback("/[\n\r\t]*\{eval\s+(.+?)\s*\}[\n\r\t]*/s", array($this, 'eval_tags'), $template);

        $template = str_replace("{LF}", PHP_EOL, $template);

        $template = preg_replace("/\{(\\\$[a-zA-Z0-9_\-\>\[\]\'\"\$\.\x7f-\xff]+)\}/s", "<?=\\1?>", $template);
        $template = preg_replace_callback("/$var_regexp/s", array($this, 'addquote'), $template);
        $template = preg_replace_callback("/\<\?\=\<\?\=$var_regexp\?\>\?\>/s", array($this, 'addquote'), $template);

        $headeradd = '';
        if (!empty($this->subtemplates)) {
            $first = true;
            foreach ($this->subtemplates as $fname) {
                $headeradd .= ($first ? "0 " : PHP_EOL) . "|| checktplrefresh('$tplfile', '$fname', " . time() . ", '$cachefile', '$file')";
                $first = false;
            }
            $headeradd .= ';' . PHP_EOL;
        }
        if (!empty($this->blocks)) {
            $headeradd .= "block_get('" . implode(',', $this->blocks) . "');" . PHP_EOL;
        }

        $template = "<?php " . PHP_EOL . "if(!defined('PSROOT')) exit('Access Denied');" . PHP_EOL . " {$headeradd}?>" . PHP_EOL . "$template";

        $template = preg_replace_callback("/[\n\r\t]*\{template\s+([a-z0-9_:\/]+)\}[\n\r\t]*/", array($this, 'tag_template'), $template);
        $template = preg_replace_callback("/[\n\r\t]*\{echo\s+(.+?)\}[\n\r\t]*/", array($this, 'tag_echo'), $template);

        $template = preg_replace_callback("/([\n\r\t]*)\{if\s+(.+?)\}([\n\r\t]*)/s", array($this, 'tag_if'), $template);
        $template = preg_replace_callback("/([\n\r\t]*)\{elseif\s+(.+?)\}([\n\r\t]*)/s", array($this, 'tag_elseif'), $template);
        $template = preg_replace("/\{else\}/i", "<?php } else { ?>", $template);
        $template = preg_replace("/\{\/if\}/i", "<?php } ?>", $template);

        $template = preg_replace_callback("/[\n\r\t]*\{loop\s+(\S+)\s+(\S+)\}[\n\r\t]*/s", array($this, 'tag_loop'), $template);
        $template = preg_replace_callback("/[\n\r\t]*\{loop\s+(\S+)\s+(\S+)\s+(\S+)\}[\n\r\t]*/s", array($this, 'tag_loopas'), $template);
        $template = preg_replace("/\{\/loop\}/i", "<?php } } ?>", $template);

        $template = preg_replace("/\{$const_regexp\}/s", "<?=\\1?>", $template);

        if (!empty($this->replacecode)) {
            $template = str_replace($this->replacecode['search'], $this->replacecode['replace'], $template);
        }

        $template = preg_replace("/ \?\>[\n\r]*\<\? /s", " ", $template);
        $template = preg_replace("/ \?\>[\n\r]*\<\?php /s", " ", $template);

        $template = preg_replace_callback("/\"(http)?[\w\.\/:]+\?[^\"]+?&[^\"]+?\"/", array($this, 'transamp'), $template);
        $template = preg_replace_callback("/[\n\r\t]*\{block\s+([a-zA-Z0-9_\[\]]+)\}(.+?)\{\/block\}/s", array($this, 'stripblock'), $template);

        $template = preg_replace("/\<\?(\s{1})/is", "<?php\\1", $template);
        $template = preg_replace("/\<\?\=(.+?)\?\>/is", "<?php echo \\1;?>", $template);

        $this->save($this->cachedir . $cachefile, $template, FILE_READ_MODE);
    }

    private function save($filename, $content, $mode) {
        if (!is_file($filename)) {
            file_exists($filename) && unlink($filename);
            touch($filename) && chmod($filename, FILE_WRITE_MODE); //全读写
        }
        $ret = file_put_contents($filename, $content, LOCK_EX);
        if ($ret && FILE_WRITE_MODE != $mode) {
            chmod($filename, $mode);
        }
        return $ret;
    }

    private function language_tags($_var) {
        $vars = explode(':', $_var[1]);
        $isplugin = count($vars) == 2;
        if (!$isplugin) {
            if (!isset($this->language['inner'])) {
                $this->language['inner'] = array();
            }
            $langvar = $this->language['inner'];
            $var = '';
        } else {
            if (!isset($this->language['plugin'][$vars[0]])) {
                $this->language['plugin'][$vars[0]] = array();
            }
            $langvar = $this->language['plugin'][$vars[0]];
            $var = $vars[1];
        }
        if (!isset($langvar[$var])) {
            $lang = array();
            include_once getini('data/lang') . 'lang_template.php';
            $this->language['inner'] = $lang;
            if (!$isplugin) {
                list($path) = explode('/', $this->file);
                include_once getini('data/lang') . $path . '/lang_template.php';
                $this->language['inner'] = array_merge($this->language['inner'], $lang);
            } else {
                $templatelang = array();
                include_once getini('data/lang') . 'plugin/' . $vars[0] . '.lang.php';
                $this->language['plugin'][$vars[0]] = $templatelang[$vars[0]];
            }
        }
        if (isset($langvar[$var])) {
            return $langvar[$var];
        } else {
            return '!' . $var . '!';
        }
    }

    private function url_tags($parameter) {
        $i = count($this->replacecode['search']);
        $this->replacecode['search'][$i] = $search = "<!--URL_TAG_$i-->";
        $this->replacecode['replace'][$i] = "<?php echo url(\"$parameter[1]\"); ?>";
        return $search;
    }

    private function surl_tags($parameter) {
        $i = count($this->replacecode['search']);
        $this->replacecode['search'][$i] = $search = "<!--SURL_TAG_$i-->";
        $this->replacecode['replace'][$i] = url("$parameter[1]");
        return $search;
    }

    private function adv_tags($parameter) {
        $i = count($this->replacecode['search']);
        if (!isset($parameter[2])) {
            $this->replacecode['search'][$i] = $search = "<!--AD_TAG_$i-->";
            $this->replacecode['replace'][$i] = "<?php echo ad_display(\"$parameter[1]\"); ?>";
        } else {
            $this->replacecode['search'][$i] = $search = "<!--AD_TAG2_$i-->";
            $this->replacecode['replace'][$i] = '<?php $' . $parameter[2] . " = ad_display(\"$parameter[1]\"); ?>";
        }
        return $search;
    }

    private function script_tags($parameter) {
        $tplfile = template($parameter[1], true);
        $content = implode('', file($this->tpldir . $tplfile));
        return $content;
    }

    private function date_tags($parameter) {
        $i = count($this->replacecode['search']);
        if (!isset($parameter[2])) {
            $this->replacecode['search'][$i] = $search = "<!--DATE_TAG_$i-->";
            $this->replacecode['replace'][$i] = "<?php echo dgmdate($parameter[1],'dt'); ?>";
        } else {
            $this->replacecode['search'][$i] = $search = "<!--DATE_TAG2_$i-->";
            $this->replacecode['replace'][$i] = "<?php echo dgmdate($parameter[1], $parameter[2]); ?>";
        }
        return $search;
    }

    private function function_tags($parameter) {
        $i = count($this->replacecode['search']);
        if (!isset($parameter[2])) {
            $this->replacecode['search'][$i] = $search = "<!--FUNCTION_TAG_$i-->";
            $this->replacecode['replace'][$i] = "<?php echo $parameter[1](); ?>";
        } else {
            $this->replacecode['search'][$i] = $search = "<!--FUNCTION_TAG2_$i-->";
            $this->replacecode['replace'][$i] = "<?php echo $parameter[1]($parameter[2]); ?>";
        }
        return $search;
    }

    private function eval_tags($php) {
        $php = str_replace('\"', '"', $php[1]);
        $i = count($this->replacecode['search']);
        $this->replacecode['search'][$i] = $search = "<!--EVAL_TAG_$i-->";
        $this->replacecode['replace'][$i] = "<?php $php ?>";
        return $search;
    }

    private function config_tags($parameter) {
        $i = count($this->replacecode['search']);
        $this->replacecode['search'][$i] = $search = "<!--CONFIG_TAG_$i-->";
        $this->replacecode['replace'][$i] = (null != getini("settings/$parameter[1]")) ? getini("settings/$parameter[1]") : getini("$parameter[1]");
        return $search;
    }

    private function tag_subtemplate($file) {
        $tplfile = template($file[2], true);
        $content = implode('', file($this->tpldir . $tplfile));
        if ($content) {
            $this->subtemplates[] = $tplfile;
            return $content;
        } else {
            return '<!--1 ' . $file[2] . ' 1-->';
        }
    }

    private function tag_template($parameter) {
        $return = "<?php include template(\"$parameter[1]\"); ?>";
        return $this->stripvtags($return);
    }

    private function tag_echo($parameter) {
        $return = "<?php echo $parameter[1]; ?>";
        return $this->stripvtags($return);
    }

    private function tag_if($parameter) {
        $return = "$parameter[1]<?php if($parameter[2]) { ?>$parameter[3]";
        return $this->stripvtags($return);
    }

    private function tag_elseif($parameter) {
        $return = "$parameter[1]<?php }elseif($parameter[2]) { ?>$parameter[3]";
        return $this->stripvtags($return);
    }

    private function tag_loop($parameter) {
        $return = "<?php if(!empty($parameter[1])){ foreach($parameter[1] as $parameter[2]) { ?>";
        return $this->stripvtags($return);
    }

    private function tag_loopas($parameter) {
        $return = "<?php if(!empty($parameter[1])){ foreach($parameter[1] as $parameter[2] => $parameter[3]) { ?>";
        return $this->stripvtags($return);
    }

    private function transamp($str) {
        $str = str_replace('&', '&amp;', $str[0]);
        $str = str_replace('&amp;amp;', '&amp;', $str);
        $str = str_replace('\"', '"', $str);
        return $str;
    }

    private function addquote($var) {
        $var = '<?=' . $var[1] . '?>';
        return str_replace("\\\"", "\"", preg_replace("/\[([a-zA-Z0-9_\-\.\x7f-\xff]+)\]/s", "['\\1']", $var));
    }

    private function stripvtags($expr, $statement = '') {
        $expr = str_replace("\\\"", "\"", preg_replace("/\<\?\=(\\\$.+?)\?\>/s", "\\1", $expr));
        $statement = str_replace("\\\"", "\"", $statement);
        return $expr . $statement;
    }

    private function stripblock($parameter) {
        $var = $parameter[1];
        $s = $parameter[2];
        $s = str_replace('\\"', '"', $s);
        $s = preg_replace("/<\?=\\\$(.+?)\?>/", "{\$\\1}", $s);
        preg_match_all("/<\?=(.+?)\?>/e", $s, $constary);
        $constadd = '';
        $constary[1] = array_unique($constary[1]);
        foreach ($constary[1] as $const) {
            $constadd .= '$__' . $const . ' = ' . $const . ';';
        }
        $s = preg_replace("/<\?=(.+?)\?>/", "{\$__\\1}", $s);
        $s = str_replace('?>', PHP_EOL . "\$$var .= <<<EOF" . PHP_EOL, $s);
        $s = str_replace('<?', PHP_EOL . 'EOF;' . PHP_EOL, $s);
        $s = str_replace("\nphp ", PHP_EOL, $s);
        return '<?' . PHP_EOL . "$constadd\$$var = <<<EOF" . PHP_EOL . $s . PHP_EOL . 'EOF;' . PHP_EOL . '?>';
    }

}
