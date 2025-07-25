<?php

namespace Xcs\Helper;

use Xcs\Traits\Singleton;

class Xss
{

    use Singleton;

    /**
     * 非法文件名字符
     * @var    array
     */
    public array $filename_bad_chars = [
        '../', '<!--', '-->', '<', '>',
        "'", '"', '&', '$', '#',
        '{', '}', '[', ']', '=',
        ';', '?', '%20', '%22',
        '%3c', // <
        '%253c', // <
        '%3e', // >
        '%0e', // >
        '%28', // (
        '%29', // )
        '%2528', // (
        '%26', // &
        '%24', // $
        '%3f', // ?
        '%3b', // ;
        '%3d'  // =
    ];
    protected array $never_allowed_str = [
        'document.cookie' => '[del]',
        'document.write' => '[del]',
        '.parentNode' => '[del]',
        '.innerHTML' => '[del]',
        '-moz-binding' => '[del]',
        '<!--' => '&lt;!--',
        '-->' => '--&gt;',
        '<![CDATA[' => '&lt;![CDATA[',
        '<comment>' => '&lt;comment&gt;'
    ];
    protected array $never_allowed_regex = [
        'javascript\s*:',
        '(document|(document\.)?window)\.(location|on\w*)',
        'expression\s*(\(|&\#40;)', // CSS and IE
        'vbscript\s*:', // IE, surprise!
        'wscript\s*:', // IE
        'jscript\s*:', // IE
        'vbs\s*:', // IE
        'Redirect\s+30\d',
        "([\"'])?data\s*:[^\\1]*?base64[^\\1]*?,[^\\1]*?\\1?"
    ];

    protected $_xss_hash = null;

    public function remove_invisible_characters($str, $url_encoded = true)
    {
        $non_display_ables = [];
        if ($url_encoded) {
            $non_display_ables[] = '/%0[0-8bcef]/';
            $non_display_ables[] = '/%1[0-9a-f]/';
        }

        $non_display_ables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S';

        do {
            $str = preg_replace($non_display_ables, '', $str, -1, $count);
        } while ($count);

        return $str;
    }

    /**
     * @param mixed $str
     * @param bool $is_image
     * @return array|bool|string
     */
    public function clean($str, bool $is_image = false)
    {
        if (is_array($str)) {
            foreach ($str as $key) {
                $str[$key] = $this->clean($str[$key]);
            }
            return $str;
        }

        $str = $this->remove_invisible_characters($str);

        do {
            $str = rawurldecode($str);
        } while (preg_match('/%[\da-f]{2,}/i', $str));

        $str = preg_replace_callback("/[^a-z\d>]+[a-z\d]+=([\'\"]).*?\\1/si", [$this, '_convert_attribute'], $str);
        $str = preg_replace_callback('/<\w+.*/si', [$this, '_decode_entity'], $str);
        $str = $this->remove_invisible_characters($str);
        $str = str_replace("\t", ' ', $str);
        $converted_string = $str;
        $str = $this->_do_never_allowed($str);

        if ($is_image) {
            $str = preg_replace('/<\?(php)/i', '&lt;?\\1', $str);
        } else {
            $str = str_replace(['<?', '?' . '>'], ['&lt;?', '?&gt;'], $str);
        }

        $words = [
            'javascript', 'expression', 'vbscript', 'jscript', 'wscript',
            'vbs', 'script', 'base64', 'applet', 'alert', 'document',
            'write', 'cookie', 'window', 'confirm', 'prompt'
        ];

        foreach ($words as $word) {
            $word = implode('\s*', str_split($word)) . '\s*';
            $str = preg_replace_callback('#(' . substr($word, 0, -3) . ')(\W)#is', [$this, '_compact_exploded_words'], $str);
        }

        do {
            $original = $str;
            if (preg_match('/<a/i', $str)) {
                $str = preg_replace_callback('#<a[^a-z\d>]+([^>]*?)(?:>|$)#si', [$this, '_js_link_removal'], $str);
            }
            if (preg_match('/<img/i', $str)) {
                $str = preg_replace_callback('#<img[^a-z\d]+([^>]*?)(?:\s?/?>|$)#si', [$this, '_js_img_removal'], $str);
            }
            if (preg_match('/script|xss/i', $str)) {
                $str = preg_replace('#</*(?:script|xss).*?>#si', '[del]', $str);
            }
        } while ($original !== $str);

        unset($original);

        $str = $this->_remove_evil_attributes($str, $is_image);

        $naughty = 'alert|prompt|confirm|applet|audio|basefont|base|behavior|bgsound|blink|body|embed|expression|form|frameset|frame|head|html|ilayer|iframe|input|button|select|isindex|layer|link|meta|keygen|object|plaintext|style|script|textarea|title|math|video|svg|xml|xss';
        $str = preg_replace_callback('#<(/*\s*)(' . $naughty . ')([^><]*)([><]*)#is', [$this, '_sanitize_naughty_html'], $str);

        $str = preg_replace('#(alert|prompt|confirm|cmd|passthru|eval|exec|expression|system|fopen|fsockopen|file|file_get_contents|readfile|unlink)(\s*)\((.*?)\)#si', '\\1\\2&#40;\\3&#41;', $str);

        $str = $this->_do_never_allowed($str);

        if ($is_image) {
            return ($str === $converted_string);
        }

        return $str;
    }

    protected function _remove_evil_attributes($str, $is_image)
    {
        $evil_attributes = ['on\w*', 'style', 'xmlns', 'formaction', 'form', 'xlink:href'];
        if ($is_image) {
            unset($evil_attributes[array_search('xmlns', $evil_attributes)]);
        }

        do {
            $count = 0;
            $attribs = [];
            preg_match_all('/(?<!\w)(' . implode('|', $evil_attributes) . ')\s*=\s*(\042|\047)([^\\\2]*?)(\\2)/is', $str, $matches, PREG_SET_ORDER);
            foreach ($matches as $attr) {
                $attribs[] = preg_quote($attr[0], '/');
            }
            preg_match_all('/(?<!\w)(' . implode('|', $evil_attributes) . ')\s*=\s*([^\s>]*)/is', $str, $matches, PREG_SET_ORDER);
            foreach ($matches as $attr) {
                $attribs[] = preg_quote($attr[0], '/');
            }
            if (count($attribs) > 0) {
                $str = preg_replace('/(<?)(\/?[^><]+?)([^A-Za-z<>\-])(.*?)(' . implode('|', $attribs) . ')(.*?)([\s><]?)([><]*)/i', '$1$2 $4$6$7$8', $str, -1, $count);
            }
        } while ($count);

        return $str;
    }

    protected function _sanitize_naughty_html($matches): string
    {
        return '&lt;' . $matches[1] . $matches[2] . $matches[3] . str_replace(['>', '<'], ['&gt;', '&lt;'], $matches[4]);
    }

    protected function _js_link_removal($match)
    {
        return str_replace($match[1], preg_replace('#href=.*?(?:(?:alert|prompt|confirm)(?:\(|&\#40;)|javascript:|livescript:|mocha:|charset=|window\.|document\.|\.cookie|<script|<xss|data\s*:)#si', '', $this->_filter_attributes(str_replace(['<', '>'], '', $match[1]))), $match[0]);
    }

    protected function _js_img_removal($match)
    {
        return str_replace($match[1], preg_replace('#src=.*?(?:(?:alert|prompt|confirm)(?:\(|&\#40;)|javascript:|livescript:|mocha:|charset=|window\.|document\.|\.cookie|<script|<xss|base64\s*,)#si', '', $this->_filter_attributes(str_replace(['<', '>'], '', $match[1]))), $match[0]);
    }

    protected function _compact_exploded_words($matches): string
    {
        return preg_replace('/\s+/s', '', $matches[1]) . $matches[2];
    }

    protected function _convert_attribute($match)
    {
        return str_replace(['>', '<', '\\'], ['&gt;', '&lt;', '\\\\'], $match[0]);
    }

    protected function _filter_attributes($str): string
    {
        $out = '';
        if (preg_match_all('#\s*[a-z\-]+\s*=\s*(\042|\047)([^\\\1]*?)\\1#is', $str, $matches)) {
            foreach ($matches[0] as $match) {
                $out .= preg_replace('#/\*.*?\*/#s', '', $match);
            }
        }
        return $out;
    }

    public function xss_hash(): string
    {
        if (null == $this->_xss_hash) {
            $rand = $this->get_random_bytes(16);
            $this->_xss_hash = (false === $rand) ? md5(uniqid(mt_rand(), true)) : bin2hex($rand);
        }
        return $this->_xss_hash;
    }

    public function get_random_bytes($length)
    {
        if (empty($length) || !ctype_digit((string)$length)) {
            return false;
        }

        if (function_exists('openssl_random_pseudo_bytes')) {
            return openssl_random_pseudo_bytes($length);
        }

        if (is_readable('/dev/urandom') && false !== ($fp = fopen('/dev/urandom', 'rb'))) {
            $output = fread($fp, $length);
            fclose($fp);
            if (false !== $output) {
                return $output;
            }
        }

        return false;
    }

    protected function _decode_entity($match)
    {
        $match = preg_replace('|\&([a-z\_\d\-]+)\=([a-z\_\d\-/]+)|i', $this->xss_hash() . '\\1=\\2', $match[0]);
        return str_replace($this->xss_hash(), '&', $this->entity_decode($match));
    }

    public function entity_decode($str, $charset = 'UTF-8')
    {
        if (!str_contains($str, '&')) {
            return $str;
        }
        static $_entities = null;
        $flag = intval(PHP_VERSION) ? ENT_COMPAT | ENT_HTML5 : ENT_COMPAT;
        do {
            $str_compare = $str;
            $c = preg_match_all('/&[a-z]{2,}(?![a-z;])/i', $str, $matches);
            if ($c) {
                if (!isset($_entities)) {
                    $_entities = array_map('strtolower', get_html_translation_table(HTML_ENTITIES, $flag));
                    if ($flag === ENT_COMPAT) {
                        $_entities[':'] = '&colon;';
                        $_entities['('] = '&lpar;';
                        $_entities[')'] = '&rpar';
                        $_entities["\n"] = '&newline;';
                        $_entities["\t"] = '&tab;';
                    }
                }
                $replace = [];
                $matches = array_unique(array_map('strtolower', $matches[0]));
                for ($i = 0; $i < $c; $i++) {
                    if (false !== ($char = array_search($matches[$i] . ';', $_entities, true))) {
                        $replace[$matches[$i]] = $char;
                    }
                }
                $str = str_ireplace(array_keys($replace), array_values($replace), $str);
            }
            $str = html_entity_decode(preg_replace('/(&#(?:x0*[\da-f]{2,5}(?![\da-f;]))|(?:0*\d{2,4}(?![\d;])))/iS', '$1;', $str), $flag, $charset);
        } while ($str_compare !== $str);
        return $str;
    }

    protected function _do_never_allowed($str)
    {
        $str = str_replace(array_keys($this->never_allowed_str), $this->never_allowed_str, $str);
        foreach ($this->never_allowed_regex as $regex) {
            $str = preg_replace('#' . $regex . '#is', '[del]', $str);
        }
        return $str;
    }

}
