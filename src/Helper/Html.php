<?php

namespace Xcs\Helper;

class Html {

    /**
     * @param $name
     * @param $arr
     * @param null $selected
     * @param null $extra
     * @return string
     */
    public static function dropdown_list($name, $arr, $selected = null, $extra = null) {
        $str = "<select name=\"{$name}\" {$extra} >\n";
        foreach ($arr as $value => $title) {
            $str .= '<option value="' . htmlspecialchars($value) . '"';
            if ($selected == $value) {
                $str .= ' selected';
            }
            $str .= '>' . htmlspecialchars($title) . "&nbsp;&nbsp;</option>\n";
        }
        $str .= "</select>\n";
        return $str;
    }

    /**
     * @param $name
     * @param $arr
     * @param null $checked
     * @param string $separator
     * @param null $extra
     * @return string
     */
    public static function radio_group($name, $arr, $checked = null, $separator = '', $extra = null) {
        $ix = 0;
        $str = "";
        foreach ($arr as $value => $title) {
            $value_h = htmlspecialchars($value);
            $title = htmlspecialchars($title);
            $str .= "<input name=\"{$name}\" type=\"radio\" id=\"{$name}_{$ix}\" value=\"{$value_h}\" ";
            if ($value == $checked) {
                $str .= "checked=\"checked\"";
            }
            $str .= " {$extra} />";
            $str .= "<label for=\"{$name}_{$ix}\">{$title}</label>";
            $str .= $separator;
            $ix++;
            $str .= "\n";
        }
        return $str;
    }

    /**
     * @param $name
     * @param $arr
     * @param array $selected
     * @param string $separator
     * @param null $extra
     * @return string
     */
    public static function checkbox_group($name, $arr, $selected = array(), $separator = '', $extra = null) {
        $ix = 0;
        if (!is_array($selected)) {
            $selected = array($selected);
        }
        $str = "";
        foreach ($arr as $value => $title) {
            $value_h = htmlspecialchars_decode($value);
            $title = htmlspecialchars_decode($title);
            $str .= "<input name=\"{$name}[]\" type=\"checkbox\" id=\"{$name}_{$ix}\" value=\"{$value_h}\" ";
            if (in_array($value, $selected)) {
                $str .= "checked=\"checked\"";
            }
            $str .= " {$extra} />";
            $str .= "<label for=\"{$name}_{$ix}\">{$title}</label>";
            $str .= $separator;
            $ix++;
            $str .= "\n";
        }
        return $str;
    }

    /**
     * @param $name
     * @param int $value
     * @param bool $checked
     * @param string $label
     * @param null $extra
     * @return string
     */
    public static function checkbox($name, $value = 1, $checked = false, $label = '', $extra = null) {
        $str = "<input name=\"{$name}\" type=\"checkbox\" id=\"{$name}_1\" value=\"{$value}\"";
        if ($checked) {
            $str .= " checked";
        }
        $str .= " {$extra} />\n";
        if ($label) {
            $str .= "<label for=\"{$name}_1\">" . htmlspecialchars($label) . "</label>\n";
        }
        return $str;
    }

    /**
     * @param $name
     * @param string $value
     * @param null $width
     * @param null $maxLength
     * @param null $extra
     * @return string
     */
    public static function textbox($name, $value = '', $width = null, $maxLength = null, $extra = null) {
        $str = "<input name=\"{$name}\" type=\"text\" value=\"" . htmlspecialchars($value) . "\" ";
        if ($width) {
            $str .= "size=\"{$width}\" ";
        }
        if ($maxLength) {
            $str .= "maxlength=\"{$maxLength}\" ";
        }
        $str .= " {$extra} />\n";
        return $str;
    }

    /**
     * @param $name
     * @param string $value
     * @param null $width
     * @param null $maxLength
     * @param null $extra
     * @return string
     */
    public static function password($name, $value = '', $width = null, $maxLength = null, $extra = null) {
        $str = "<input name=\"{$name}\" type=\"password\" value=\"" . htmlspecialchars($value) . "\" ";
        if ($width) {
            $str .= "size=\"{$width}\" ";
        }
        if ($maxLength) {
            $str .= "maxlength=\"{$maxLength}\" ";
        }
        $str .= " {$extra} />\n";
        return $str;
    }

    /**
     * @param $name
     * @param string $value
     * @param null $width
     * @param null $height
     * @param null $extra
     * @return string
     */
    public static function textarea($name, $value = '', $width = null, $height = null, $extra = null) {
        $str = "<textarea name=\"{$name}\"";
        if ($width) {
            $str .= "cols=\"{$width}\" ";
        }
        if ($height) {
            $str .= "rows=\"{$height}\" ";
        }
        $str .= " {$extra} >";
        $str .= htmlspecialchars($value);
        $str .= "</textarea>\n";
        return $str;
    }

    /**
     * @param $name
     * @param string $value
     * @param null $extra
     * @return string
     */
    public static function hidden($name, $value = '', $extra = null) {
        $str = "<input name=\"{$name}\" type=\"hidden\" value=\"";
        $str .= htmlspecialchars($value);
        $str .= "\" {$extra} />\n";
        return $str;
    }

    /**
     * @param $name
     * @param null $width
     * @param null $extra
     * @return string
     */
    public static function filefield($name, $width = null, $extra = null) {
        $str = "<input name=\"{$name}\" type=\"file\"";
        if ($width) {
            $str .= " size=\"{$width}\"";
        }
        $str .= " {$extra} />\n";
        return $str;
    }

    /**
     * @param $name
     * @param $action
     * @param string $method
     * @param string $onsubmit
     * @param null $extra
     * @return string
     */
    public static function form_open($name, $action, $method = 'post', $onsubmit = '', $extra = null) {
        $str = "<form name=\"{$name}\" action=\"{$action}\" method=\"{$method}\" ";
        if ($onsubmit) {
            $str .= "onsubmit=\"{$onsubmit}\"";
        }
        $str .= " {$extra} >\n";
        return $str;
    }

    /**
     * @return string
     */
    public static function form_close() {
        return "</form>\n";
    }

}
